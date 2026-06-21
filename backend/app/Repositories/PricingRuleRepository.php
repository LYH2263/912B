<?php

namespace App\Repositories;

use App\Models\PricingRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PricingRuleRepository
{
    public function create(array $data): PricingRule
    {
        return PricingRule::create($data);
    }

    public function update(PricingRule $rule, array $data): PricingRule
    {
        $rule->update($data);
        return $rule->fresh();
    }

    public function delete(PricingRule $rule): bool
    {
        return $rule->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PricingRule::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);
    }

    public function all(array $filters = []): Collection
    {
        $query = PricingRule::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function find(int $id): ?PricingRule
    {
        return PricingRule::find($id);
    }

    public function toggleActive(PricingRule $rule): PricingRule
    {
        $rule->is_active = !$rule->is_active;
        $rule->save();
        return $rule->fresh();
    }
}
