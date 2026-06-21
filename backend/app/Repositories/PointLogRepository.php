<?php

namespace App\Repositories;

use App\Models\PointLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PointLogRepository
{
    public function create(array $data): PointLog
    {
        return PointLog::create($data);
    }

    public function paginateByUserId(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PointLog::where('user_id', $userId)->with('relatedOrder');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
