<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\FlashSaleRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlashSaleService
{
    public function __construct(
        public FlashSaleRepository $repository
    ) {
    }

    public function create(array $data): FlashSale
    {
        if ($data['flash_price'] <= 0) {
            throw new \Exception('秒杀价必须大于0');
        }

        if ($data['activity_stock'] <= 0) {
            throw new \Exception('活动库存必须大于0');
        }

        if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
            throw new \Exception('开始时间必须早于结束时间');
        }

        $now = now();
        $startTime = $data['start_time'];
        if (strtotime($startTime) <= $now->timestamp) {
            $data['status'] = 'active';
        }

        return $this->repository->create($data);
    }

    public function update(FlashSale $flashSale, array $data): FlashSale
    {
        if ($flashSale->status === 'active' || $flashSale->status === 'ended') {
            throw new \Exception('进行中或已结束的活动不允许修改');
        }

        if (isset($data['flash_price']) && $data['flash_price'] <= 0) {
            throw new \Exception('秒杀价必须大于0');
        }

        if (isset($data['activity_stock']) && $data['activity_stock'] < $flashSale->sold_count) {
            throw new \Exception('活动库存不能小于已售数量');
        }

        return $this->repository->update($flashSale, $data);
    }

    public function delete(FlashSale $flashSale): bool
    {
        if ($flashSale->status === 'active') {
            throw new \Exception('进行中的活动不允许删除');
        }

        return $this->repository->delete($flashSale);
    }

    public function syncAllStatuses(): int
    {
        $count = 0;
        $flashSales = FlashSale::where('status', '!=', 'ended')->get();

        foreach ($flashSales as $flashSale) {
            $oldStatus = $flashSale->status;
            $flashSale->syncStatus();
            if ($flashSale->fresh()->status !== $oldStatus) {
                $count++;
            }
        }

        return $count;
    }

    public function placeOrder(int $flashSaleId, int $quantity, array $orderInfo = []): Order
    {
        return DB::transaction(function () use ($flashSaleId, $quantity, $orderInfo) {
            $flashSale = FlashSale::lockForUpdate()->findOrFail($flashSaleId);

            if (!$flashSale->isActive()) {
                throw new \Exception('活动未在进行中，无法下单');
            }

            if ($flashSale->remainingStock() < $quantity) {
                throw new \Exception('秒杀库存不足');
            }

            if ($quantity > $flashSale->per_limit) {
                throw new \Exception("每人限购{$flashSale->per_limit}件");
            }

            $flashSale->increment('sold_count', $quantity);

            $product = $flashSale->product;
            $subtotal = $flashSale->flash_price * $quantity;
            $finalAmount = $subtotal - ($orderInfo['discount_amount'] ?? 0);

            $order = Order::create([
                'order_no' => Order::generateOrderNo(),
                'user_id' => $orderInfo['user_id'] ?? null,
                'total_amount' => $subtotal,
                'discount_amount' => $orderInfo['discount_amount'] ?? 0,
                'final_amount' => $finalAmount,
                'status' => 'pending',
                'shipping_address' => $orderInfo['shipping_address'] ?? null,
                'shipping_name' => $orderInfo['shipping_name'] ?? null,
                'shipping_phone' => $orderInfo['shipping_phone'] ?? null,
                'remark' => ($orderInfo['remark'] ?? '') . ' [秒杀活动]',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_price' => $flashSale->flash_price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ]);

            Log::info('秒杀订单创建成功', [
                'order_id' => $order->id,
                'flash_sale_id' => $flashSale->id,
                'quantity' => $quantity,
            ]);

            return $order->load('orderItems');
        });
    }
}
