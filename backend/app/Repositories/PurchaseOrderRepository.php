<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository
{
    public function create(array $data): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }

    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        $purchaseOrder->update($data);
        return $purchaseOrder->fresh();
    }

    public function delete(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::with('items.product', 'createdBy', 'stockInBy');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['purchase_order_no'])) {
            $query->where('purchase_order_no', 'like', "%{$filters['purchase_order_no']}%");
        }

        if (isset($filters['supplier_name'])) {
            $query->where('supplier_name', 'like', "%{$filters['supplier_name']}%");
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?PurchaseOrder
    {
        return PurchaseOrder::with('items.product', 'createdBy', 'stockInBy')->find($id);
    }

    public function findByNo(string $purchaseOrderNo): ?PurchaseOrder
    {
        return PurchaseOrder::with('items.product', 'createdBy', 'stockInBy')
            ->where('purchase_order_no', $purchaseOrderNo)
            ->first();
    }
}
