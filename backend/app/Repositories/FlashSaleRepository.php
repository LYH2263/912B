<?php

namespace App\Repositories;

use App\Models\FlashSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FlashSaleRepository
{
    public function create(array $data): FlashSale
    {
        return FlashSale::create($data);
    }

    public function update(FlashSale $flashSale, array $data): FlashSale
    {
        $flashSale->update($data);
        return $flashSale->fresh();
    }

    public function delete(FlashSale $flashSale): bool
    {
        return $flashSale->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = FlashSale::with('product');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('start_time', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?FlashSale
    {
        return FlashSale::with('product')->find($id);
    }

    public function getActiveList(): \Illuminate\Database\Eloquent\Collection
    {
        return FlashSale::with('product')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereRaw('sold_count < activity_stock')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    public function getUpcomingList(): \Illuminate\Database\Eloquent\Collection
    {
        return FlashSale::with('product')
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->get();
    }
}
