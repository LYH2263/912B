<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderSplitMergeService
{
    public function __construct(
        private InventoryService $inventoryService,
        private MemberService $memberService
    ) {
    }

    public function split(Order $order, array $splitItemIds): array
    {
        if (!in_array($order->status, ['pending', 'paid'])) {
            throw new \Exception('仅「待支付」或「已支付」订单可拆分');
        }

        $order->load('orderItems');
        $allItemIds = $order->orderItems->pluck('id')->toArray();

        if (count($splitItemIds) < 1 || count($splitItemIds) >= count($allItemIds)) {
            throw new \Exception('拆分商品行数量必须介于1和总商品行数之间');
        }

        foreach ($splitItemIds as $id) {
            if (!in_array($id, $allItemIds)) {
                throw new \Exception("商品行ID {$id} 不属于该订单");
            }
        }

        return DB::transaction(function () use ($order, $splitItemIds, $allItemIds) {
            $remainingItemIds = array_diff($allItemIds, $splitItemIds);

            $splitItems = $order->orderItems->whereIn('id', $splitItemIds);
            $remainingItems = $order->orderItems->whereIn('id', $remainingItemIds);

            $splitSubtotal = $splitItems->sum('subtotal');
            $remainingSubtotal = $remainingItems->sum('subtotal');
            $originalTotal = $order->total_amount;

            $splitRatio = $originalTotal > 0 ? $splitSubtotal / $originalTotal : 0;
            $remainingRatio = 1 - $splitRatio;

            $splitDiscount = round($order->discount_amount * $splitRatio, 2);
            $remainingDiscount = $order->discount_amount - $splitDiscount;

            $splitPointsUsed = (int) round($order->points_used * $splitRatio);
            $remainingPointsUsed = $order->points_used - $splitPointsUsed;

            $splitPointsDiscount = round($order->points_discount_amount * $splitRatio, 2);
            $remainingPointsDiscount = $order->points_discount_amount - $splitPointsDiscount;

            $splitFinalAmount = max(0, $splitSubtotal - $splitDiscount - $splitPointsDiscount);
            $remainingFinalAmount = max(0, $remainingSubtotal - $remainingDiscount - $remainingPointsDiscount);

            if ($order->points_used > 0 && $order->user_id) {
                $this->memberService->refundPoints($order->user_id, $order->points_used, $order, '订单拆分退回积分');
            }

            $order1 = $this->createOrderFromSplit($order, $splitItems, $splitSubtotal, $splitDiscount, $splitPointsUsed, $splitPointsDiscount, $splitFinalAmount);
            $order2 = $this->createOrderFromSplit($order, $remainingItems, $remainingSubtotal, $remainingDiscount, $remainingPointsUsed, $remainingPointsDiscount, $remainingFinalAmount);

            $this->migrateInventoryLogs($order, $order1, $splitItems);
            $this->migrateInventoryLogs($order, $order2, $remainingItems);

            if ($order->points_used > 0 && $order->user_id) {
                if ($splitPointsUsed > 0) {
                    $this->memberService->usePoints($order->user_id, $splitPointsUsed, $order1);
                }
                if ($remainingPointsUsed > 0) {
                    $this->memberService->usePoints($order->user_id, $remainingPointsUsed, $order2);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Log::info('订单拆分完成', [
                'original_order_id' => $order->id,
                'order_1_id' => $order1->id,
                'order_2_id' => $order2->id,
            ]);

            return [
                'order_1' => $order1->load('orderItems'),
                'order_2' => $order2->load('orderItems'),
            ];
        });
    }

    public function merge(Order $order1, Order $order2): Order
    {
        if ($order1->status !== 'pending' || $order2->status !== 'pending') {
            throw new \Exception('仅「待支付」订单可合并');
        }

        if ($order1->user_id !== $order2->user_id) {
            throw new \Exception('仅同一客户的订单可合并');
        }

        if (!$order1->user_id) {
            throw new \Exception('订单需关联客户才能合并');
        }

        return DB::transaction(function () use ($order1, $order2) {
            $totalAmount = $order1->total_amount + $order2->total_amount;
            $discountAmount = $order1->discount_amount + $order2->discount_amount;
            $pointsUsed = $order1->points_used + $order2->points_used;
            $pointsDiscountAmount = $order1->points_discount_amount + $order2->points_discount_amount;
            $finalAmount = max(0, $totalAmount - $discountAmount - $pointsDiscountAmount);

            if ($pointsUsed > 0 && $order1->user_id) {
                $this->memberService->refundPoints($order1->user_id, $order1->points_used, $order1, '订单合并退回积分');
                if ($order2->points_used > 0) {
                    $this->memberService->refundPoints($order2->user_id, $order2->points_used, $order2, '订单合并退回积分');
                }
            }

            $mergedOrder = Order::create([
                'order_no' => Order::generateOrderNo(),
                'user_id' => $order1->user_id,
                'source_order_id' => $order1->id,
                'split_merge_type' => 'merge',
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'points_used' => $pointsUsed,
                'points_discount_amount' => $pointsDiscountAmount,
                'final_amount' => $finalAmount,
                'status' => 'pending',
                'shipping_address' => $order1->shipping_address,
                'shipping_name' => $order1->shipping_name,
                'shipping_phone' => $order1->shipping_phone,
                'remark' => '合并订单：' . $order1->order_no . ' + ' . $order2->order_no,
            ]);

            $order1->load('orderItems');
            $order2->load('orderItems');

            foreach ($order1->orderItems as $item) {
                OrderItem::create([
                    'order_id' => $mergedOrder->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'product_price' => $item->product_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ]);
            }

            foreach ($order2->orderItems as $item) {
                OrderItem::create([
                    'order_id' => $mergedOrder->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'product_price' => $item->product_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $this->migrateInventoryLogs($order1, $mergedOrder, $order1->orderItems);
            $this->migrateInventoryLogs($order2, $mergedOrder, $order2->orderItems);

            if ($pointsUsed > 0 && $order1->user_id) {
                $this->memberService->usePoints($order1->user_id, $pointsUsed, $mergedOrder);
            }

            $order1->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            $order2->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Log::info('订单合并完成', [
                'order_1_id' => $order1->id,
                'order_2_id' => $order2->id,
                'merged_order_id' => $mergedOrder->id,
            ]);

            return $mergedOrder->load('orderItems');
        });
    }

    private function createOrderFromSplit(
        Order $originalOrder,
        $items,
        float $totalAmount,
        float $discountAmount,
        int $pointsUsed,
        float $pointsDiscountAmount,
        float $finalAmount
    ): Order {
        $order = Order::create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $originalOrder->user_id,
            'source_order_id' => $originalOrder->id,
            'split_merge_type' => 'split',
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'points_used' => $pointsUsed,
            'points_discount_amount' => $pointsDiscountAmount,
            'final_amount' => $finalAmount,
            'status' => $originalOrder->status,
            'shipping_address' => $originalOrder->shipping_address,
            'shipping_name' => $originalOrder->shipping_name,
            'shipping_phone' => $originalOrder->shipping_phone,
            'remark' => '拆分自订单：' . $originalOrder->order_no,
            'paid_at' => $originalOrder->status === 'paid' ? $originalOrder->paid_at : null,
        ]);

        foreach ($items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'product_price' => $item->product_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ]);
        }

        return $order;
    }

    private function migrateInventoryLogs(Order $fromOrder, Order $toOrder, $items): void
    {
        $productIds = $items->pluck('product_id')->toArray();

        InventoryLog::where('related_order_id', $fromOrder->id)
            ->whereIn('product_id', $productIds)
            ->update(['related_order_id' => $toOrder->id]);
    }
}
