<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\OrderRepository;
use App\Services\InventoryService;
use App\Services\MemberService;
use App\Services\PricingEngineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        public OrderRepository $repository,
        private InventoryService $inventoryService,
        private MemberService $memberService,
        private PricingEngineService $pricingEngine
    ) {
    }

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                if (!$product->hasEnoughStock($item['quantity'])) {
                    throw new \Exception("商品 {$product->name} 库存不足，当前库存：{$product->stock_quantity}");
                }
                if ($product->status !== 'active') {
                    throw new \Exception("商品 {$product->name} 已下架，无法购买");
                }
            }

            $orderNo = Order::generateOrderNo();

            $totalAmount = 0;
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $pricing = $this->pricingEngine->calculate($product);
                $subtotal = $pricing['final_price'] * $item['quantity'];
                $totalAmount += $subtotal;
            }

            $discountAmount = $data['discount_amount'] ?? 0;
            $pointsUsed = (int) ($data['points_used'] ?? 0);
            $userId = $data['user_id'] ?? null;

            if ($pointsUsed > 0 && !$userId) {
                throw new \Exception('未登录用户不能使用积分');
            }

            $pointsDiscountAmount = 0;
            if ($pointsUsed > 0 && $userId) {
                $maxAllowedPoints = $this->memberService->calculateMaxPointsDiscount($totalAmount);
                if ($pointsUsed > $maxAllowedPoints) {
                    throw new \Exception("积分抵扣超过上限，最多可使用 {$maxAllowedPoints} 积分");
                }
                $pointsDiscountAmount = $this->memberService->calculatePointsDiscountAmount($pointsUsed);
            }

            $finalAmount = max(0, $totalAmount - $discountAmount - $pointsDiscountAmount);

            $order = $this->repository->create([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'points_used' => $pointsUsed,
                'points_discount_amount' => $pointsDiscountAmount,
                'final_amount' => $finalAmount,
                'status' => 'pending',
                'shipping_address' => $data['shipping_address'] ?? null,
                'shipping_name' => $data['shipping_name'] ?? null,
                'shipping_phone' => $data['shipping_phone'] ?? null,
                'remark' => $data['remark'] ?? null,
            ]);

            if ($pointsUsed > 0 && $userId) {
                $this->memberService->usePoints($userId, $pointsUsed, $order);
            }

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $pricing = $this->pricingEngine->calculate($product);
                $subtotal = $pricing['final_price'] * $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'product_price' => $pricing['final_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ]);

                $this->inventoryService->decreaseStock($product, $item['quantity'], $order->id, '订单创建');
            }

            Log::info('订单创建成功', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'points_used' => $pointsUsed,
                'points_discount' => $pointsDiscountAmount,
            ]);

            return $order->load('orderItems');
        });
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $oldStatus = $order->status;

        $allowedTransitions = [
            'pending' => ['paid', 'cancelled'],
            'paid' => ['shipped', 'cancelled'],
            'shipped' => ['completed', 'cancelled'],
        ];

        if (!in_array($status, $allowedTransitions[$oldStatus] ?? [])) {
            throw new \Exception("订单状态不能从 {$oldStatus} 直接变更为 {$status}");
        }

        $updateData = ['status' => $status];

        switch ($status) {
            case 'paid':
                $updateData['paid_at'] = now();
                break;
            case 'shipped':
                $updateData['shipped_at'] = now();
                break;
            case 'completed':
                $updateData['completed_at'] = now();
                if ($order->user_id) {
                    $this->memberService->earnPointsAndUpgrade(
                        $order->user_id,
                        (float) $order->final_amount,
                        $order
                    );
                }
                break;
            case 'cancelled':
                $updateData['cancelled_at'] = now();
                $this->restoreInventory($order);
                if ($order->user_id && $order->points_used > 0) {
                    $this->memberService->refundPoints($order->user_id, $order->points_used, $order);
                }
                break;
        }

        $order = $this->repository->update($order, $updateData);

        Log::info('订单状态更新', [
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ]);

        return $order;
    }

    private function restoreInventory(Order $order): void
    {
        foreach ($order->orderItems as $item) {
            $product = Product::findOrFail($item->product_id);
            $this->inventoryService->increaseStock($product, $item->quantity, $order->id, '订单取消');
        }
    }
}
