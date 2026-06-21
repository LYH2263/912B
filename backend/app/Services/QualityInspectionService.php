<?php

namespace App\Services;

use App\Models\Product;
use App\Models\QualityInspection;
use App\Repositories\QualityInspectionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityInspectionService
{
    public function __construct(
        public QualityInspectionRepository $repository,
        private InventoryService $inventoryService
    ) {
    }

    public function create(array $data): QualityInspection
    {
        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);

            $qualifiedQuantity = (int) ($data['qualified_quantity'] ?? 0);
            $unqualifiedQuantity = (int) ($data['unqualified_quantity'] ?? 0);

            if ($qualifiedQuantity < 0 || $unqualifiedQuantity < 0) {
                throw new \Exception('数量不能为负数');
            }

            if ($qualifiedQuantity === 0 && $unqualifiedQuantity === 0) {
                throw new \Exception('合格数量和不合格数量不能同时为0');
            }

            if ($unqualifiedQuantity > 0 && empty($data['unqualified_reason'])) {
                throw new \Exception('存在不合格商品时必须填写不合格原因');
            }

            $batchNo = QualityInspection::generateBatchNo();

            $inspection = $this->repository->create([
                'batch_no' => $batchNo,
                'product_id' => $product->id,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'qualified_quantity' => $qualifiedQuantity,
                'unqualified_quantity' => $unqualifiedQuantity,
                'unqualified_reason' => $data['unqualified_reason'] ?? null,
                'inspector' => $data['inspector'],
                'inspection_date' => $data['inspection_date'],
                'remark' => $data['remark'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($qualifiedQuantity > 0) {
                $remark = "质检入库，批次号：{$batchNo}，合格数量：{$qualifiedQuantity}";
                if (!empty($data['purchase_order_id'])) {
                    $remark .= "，采购单ID：{$data['purchase_order_id']}";
                }
                $this->inventoryService->increaseStock($product, $qualifiedQuantity, null, $remark);
            }

            Log::info('质检记录创建成功', [
                'inspection_id' => $inspection->id,
                'batch_no' => $batchNo,
                'product_id' => $product->id,
                'qualified_quantity' => $qualifiedQuantity,
                'unqualified_quantity' => $unqualifiedQuantity,
            ]);

            return $inspection->load('product', 'purchaseOrder', 'createdBy');
        });
    }

    public function update(QualityInspection $inspection, array $data): QualityInspection
    {
        return DB::transaction(function () use ($inspection, $data) {
            $oldQualifiedQuantity = $inspection->qualified_quantity;
            $newQualifiedQuantity = isset($data['qualified_quantity']) ? (int) $data['qualified_quantity'] : $oldQualifiedQuantity;
            $newUnqualifiedQuantity = isset($data['unqualified_quantity']) ? (int) $data['unqualified_quantity'] : $inspection->unqualified_quantity;

            if ($newQualifiedQuantity < 0 || $newUnqualifiedQuantity < 0) {
                throw new \Exception('数量不能为负数');
            }

            if ($newQualifiedQuantity === 0 && $newUnqualifiedQuantity === 0) {
                throw new \Exception('合格数量和不合格数量不能同时为0');
            }

            if ($newUnqualifiedQuantity > 0 && empty($data['unqualified_reason'] ?? $inspection->unqualified_reason)) {
                throw new \Exception('存在不合格商品时必须填写不合格原因');
            }

            $product = $inspection->product;

            $quantityDiff = $newQualifiedQuantity - $oldQualifiedQuantity;
            if ($quantityDiff !== 0) {
                if ($quantityDiff > 0) {
                    $remark = "质检记录修改-补充入库，批次号：{$inspection->batch_no}，增加合格数量：{$quantityDiff}";
                    $this->inventoryService->increaseStock($product, $quantityDiff, null, $remark);
                } else {
                    $absDiff = abs($quantityDiff);
                    if ($product->stock_quantity < $absDiff) {
                        throw new \Exception("库存不足，当前库存：{$product->stock_quantity}，无法减少 {$absDiff}");
                    }
                    $remark = "质检记录修改-减少入库，批次号：{$inspection->batch_no}，减少合格数量：{$absDiff}";
                    $this->inventoryService->decreaseStock($product, $absDiff, null, $remark);
                }
            }

            $updateData = [
                'product_id' => $data['product_id'] ?? $inspection->product_id,
                'purchase_order_id' => $data['purchase_order_id'] ?? $inspection->purchase_order_id,
                'qualified_quantity' => $newQualifiedQuantity,
                'unqualified_quantity' => $newUnqualifiedQuantity,
                'unqualified_reason' => $data['unqualified_reason'] ?? $inspection->unqualified_reason,
                'inspector' => $data['inspector'] ?? $inspection->inspector,
                'inspection_date' => $data['inspection_date'] ?? $inspection->inspection_date,
                'remark' => $data['remark'] ?? $inspection->remark,
            ];

            $inspection = $this->repository->update($inspection, $updateData);

            Log::info('质检记录更新成功', [
                'inspection_id' => $inspection->id,
                'batch_no' => $inspection->batch_no,
                'quantity_diff' => $quantityDiff,
            ]);

            return $inspection->load('product', 'purchaseOrder', 'createdBy');
        });
    }

    public function delete(QualityInspection $inspection): bool
    {
        return DB::transaction(function () use ($inspection) {
            $product = $inspection->product;
            $qualifiedQuantity = $inspection->qualified_quantity;

            if ($qualifiedQuantity > 0) {
                if ($product->stock_quantity < $qualifiedQuantity) {
                    throw new \Exception("库存不足，无法回退质检入库数量。当前库存：{$product->stock_quantity}，需回退：{$qualifiedQuantity}");
                }
                $remark = "质检记录删除-回退入库，批次号：{$inspection->batch_no}，回退数量：{$qualifiedQuantity}";
                $this->inventoryService->decreaseStock($product, $qualifiedQuantity, null, $remark);
            }

            $result = $this->repository->delete($inspection);

            Log::info('质检记录删除成功', [
                'inspection_id' => $inspection->id,
                'batch_no' => $inspection->batch_no,
            ]);

            return $result;
        });
    }
}
