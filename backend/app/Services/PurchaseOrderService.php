<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    public function __construct(
        public PurchaseOrderRepository $repository,
        private InventoryService $inventoryService
    ) {
    }

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];
            $totalAmount = 0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = (float) $item['purchase_price'] * (int) $item['quantity'];
                $totalAmount += $subtotal;
            }

            $purchaseOrderNo = PurchaseOrder::generatePurchaseOrderNo();

            $purchaseOrder = $this->repository->create([
                'purchase_order_no' => $purchaseOrderNo,
                'supplier_name' => $data['supplier_name'],
                'supplier_contact' => $data['supplier_contact'] ?? null,
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'expected_arrival_date' => $data['expected_arrival_date'],
                'total_amount' => $totalAmount,
                'remark' => $data['remark'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = (float) $item['purchase_price'] * (int) $item['quantity'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'purchase_price' => $item['purchase_price'],
                    'quantity' => $item['quantity'],
                    'received_quantity' => 0,
                    'subtotal' => $subtotal,
                ]);
            }

            if (isset($data['submit']) && $data['submit'] === true) {
                $purchaseOrder = $this->submit($purchaseOrder);
            }

            Log::info('采购单创建成功', [
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_no' => $purchaseOrder->purchase_order_no,
                'total_amount' => $totalAmount,
            ]);

            return $purchaseOrder->load('items.product');
        });
    }

    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new \Exception('仅草稿状态的采购单可编辑');
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            $items = $data['items'] ?? null;
            $totalAmount = 0;

            if ($items) {
                $purchaseOrder->items()->delete();

                foreach ($items as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $subtotal = (float) $item['purchase_price'] * (int) $item['quantity'];
                    $totalAmount += $subtotal;

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'purchase_price' => $item['purchase_price'],
                        'quantity' => $item['quantity'],
                        'received_quantity' => 0,
                        'subtotal' => $subtotal,
                    ]);
                }
            } else {
                $totalAmount = $purchaseOrder->total_amount;
            }

            $updateData = [
                'supplier_name' => $data['supplier_name'] ?? $purchaseOrder->supplier_name,
                'supplier_contact' => $data['supplier_contact'] ?? $purchaseOrder->supplier_contact,
                'supplier_phone' => $data['supplier_phone'] ?? $purchaseOrder->supplier_phone,
                'expected_arrival_date' => $data['expected_arrival_date'] ?? $purchaseOrder->expected_arrival_date,
                'total_amount' => $totalAmount,
                'remark' => $data['remark'] ?? $purchaseOrder->remark,
            ];

            $purchaseOrder = $this->repository->update($purchaseOrder, $updateData);

            Log::info('采购单更新成功', [
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_no' => $purchaseOrder->purchase_order_no,
            ]);

            return $purchaseOrder->load('items.product');
        });
    }

    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (!$purchaseOrder->canTransitionTo(PurchaseOrder::STATUS_PENDING)) {
            throw new \Exception("采购单状态不能从 {$purchaseOrder->status} 提交为待入库");
        }

        if ($purchaseOrder->items->count() === 0) {
            throw new \Exception('采购单至少需要一个商品明细');
        }

        $purchaseOrder = $this->repository->update($purchaseOrder, [
            'status' => PurchaseOrder::STATUS_PENDING,
        ]);

        Log::info('采购单已提交', [
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_no' => $purchaseOrder->purchase_order_no,
        ]);

        return $purchaseOrder->load('items.product');
    }

    public function stockIn(PurchaseOrder $purchaseOrder, array $items): PurchaseOrder
    {
        if (!in_array($purchaseOrder->status, [PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_PARTIAL])) {
            throw new \Exception('仅待入库或部分入库状态的采购单可执行入库操作');
        }

        return DB::transaction(function () use ($purchaseOrder, $items) {
            $allFullyReceived = true;

            foreach ($items as $itemData) {
                $item = PurchaseOrderItem::findOrFail($itemData['id']);

                if ($item->purchase_order_id !== $purchaseOrder->id) {
                    throw new \Exception('商品明细不属于该采购单');
                }

                $quantity = (int) $itemData['quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $remaining = $item->remaining_quantity;
                if ($quantity > $remaining) {
                    throw new \Exception("商品 {$item->product_name} 入库数量不能超过剩余数量 {$remaining}");
                }

                $product = $item->product;
                $this->inventoryService->increaseStock(
                    $product,
                    $quantity,
                    null,
                    "采购入库，采购单号：{$purchaseOrder->purchase_order_no}"
                );

                $item->increment('received_quantity', $quantity);
                $item->refresh();

                if (!$item->isFullyReceived()) {
                    $allFullyReceived = false;
                }
            }

            $purchaseOrder->refresh();

            if ($allFullyReceived && $purchaseOrder->isFullyReceived()) {
                $newStatus = PurchaseOrder::STATUS_COMPLETED;
                $actualArrivalDate = now()->toDateString();
            } else {
                $newStatus = PurchaseOrder::STATUS_PARTIAL;
                $actualArrivalDate = $purchaseOrder->actual_arrival_date ?? now()->toDateString();
            }

            if (!$purchaseOrder->canTransitionTo($newStatus) && $purchaseOrder->status !== $newStatus) {
                throw new \Exception("采购单状态不能从 {$purchaseOrder->status} 变更为 {$newStatus}");
            }

            $purchaseOrder = $this->repository->update($purchaseOrder, [
                'status' => $newStatus,
                'actual_arrival_date' => $actualArrivalDate,
                'stock_in_by' => auth()->id(),
            ]);

            Log::info('采购单入库完成', [
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_no' => $purchaseOrder->purchase_order_no,
                'new_status' => $newStatus,
            ]);

            return $purchaseOrder->load('items.product');
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new \Exception('仅草稿状态的采购单可删除');
        }

        return DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $result = $this->repository->delete($purchaseOrder);

            Log::info('采购单已删除', [
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_no' => $purchaseOrder->purchase_order_no,
            ]);

            return $result;
        });
    }
}
