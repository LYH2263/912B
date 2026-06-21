<?php

namespace App\Repositories;

use App\Models\QualityInspection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QualityInspectionRepository
{
    public function create(array $data): QualityInspection
    {
        return QualityInspection::create($data);
    }

    public function update(QualityInspection $inspection, array $data): QualityInspection
    {
        $inspection->update($data);
        return $inspection->fresh();
    }

    public function delete(QualityInspection $inspection): bool
    {
        return $inspection->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = QualityInspection::with('product', 'purchaseOrder', 'createdBy');

        if (isset($filters['batch_no'])) {
            $query->where('batch_no', 'like', "%{$filters['batch_no']}%");
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['purchase_order_id'])) {
            $query->where('purchase_order_id', $filters['purchase_order_id']);
        }

        if (isset($filters['inspector'])) {
            $query->where('inspector', 'like', "%{$filters['inspector']}%");
        }

        if (isset($filters['has_unqualified']) && $filters['has_unqualified'] === 'true') {
            $query->where('unqualified_quantity', '>', 0);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('inspection_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('inspection_date', '<=', $filters['end_date']);
        }

        return $query->orderBy('inspection_date', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?QualityInspection
    {
        return QualityInspection::with('product', 'purchaseOrder', 'createdBy')->find($id);
    }

    public function findByBatchNo(string $batchNo): ?QualityInspection
    {
        return QualityInspection::with('product', 'purchaseOrder', 'createdBy')
            ->where('batch_no', $batchNo)
            ->first();
    }

    public function getByProductId(int $productId, int $perPage = 15): LengthAwarePaginator
    {
        return QualityInspection::with('purchaseOrder', 'createdBy')
            ->where('product_id', $productId)
            ->orderBy('inspection_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
