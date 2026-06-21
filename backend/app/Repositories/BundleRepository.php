<?php

namespace App\Repositories;

use App\Models\Bundle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BundleRepository
{
    public function create(array $data): Bundle
    {
        return Bundle::create($data);
    }

    public function update(Bundle $bundle, array $data): Bundle
    {
        $bundle->update($data);
        return $bundle->fresh();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Bundle::with('bundleItems.product');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('sku', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function all(array $filters = []): Collection
    {
        $query = Bundle::with('bundleItems.product');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function find(int $id): ?Bundle
    {
        return Bundle::with('bundleItems.product')->find($id);
    }

    public function existsBySku(string $sku, ?int $excludeId = null): bool
    {
        $query = Bundle::where('sku', $sku);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
