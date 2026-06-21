<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'priority',
        'is_active',
        'conditions',
        'action_type',
        'action_value',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'action_value' => 'decimal:2',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function matches(Product $product): bool
    {
        if (!$this->isCurrentlyActive()) {
            return false;
        }

        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $product)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateCondition(array $condition, Product $product): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if ($field === null) {
            return false;
        }

        $productValue = $this->getProductFieldValue($product, $field);

        return match ($operator) {
            '=' => $productValue == $value,
            '!=' => $productValue != $value,
            '>' => $productValue > $value,
            '>=' => $productValue >= $value,
            '<' => $productValue < $value,
            '<=' => $productValue <= $value,
            'in' => is_array($value) && in_array($productValue, $value),
            'not_in' => is_array($value) && !in_array($productValue, $value),
            default => false,
        };
    }

    private function getProductFieldValue(Product $product, string $field)
    {
        return match ($field) {
            'category_id' => $product->category_id,
            'stock_quantity' => $product->stock_quantity,
            'price' => (float) $product->price,
            'cost_price' => $product->cost_price ? (float) $product->cost_price : null,
            'status' => $product->status,
            'low_stock' => $product->isLowStock(),
            'out_of_stock' => $product->isOutOfStock(),
            default => $product->{$field} ?? null,
        };
    }

    public function apply(float $originalPrice): float
    {
        $price = match ($this->action_type) {
            'discount_percent' => $originalPrice * (1 - ($this->action_value / 100)),
            'markup_percent' => $originalPrice * (1 + ($this->action_value / 100)),
            'fixed_price' => (float) $this->action_value,
            default => $originalPrice,
        };

        return max(0, round($price, 2));
    }
}
