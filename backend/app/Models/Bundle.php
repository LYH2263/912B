<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bundle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'image',
        'total_price',
        'status',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
    ];

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function calculateOriginalTotal(): float
    {
        $total = 0;
        foreach ($this->bundleItems as $item) {
            if ($item->product) {
                $total += (float) $item->product->price * $item->quantity;
            }
        }
        return round($total, 2);
    }

    public function calculateDiscountAmount(): float
    {
        return round($this->calculateOriginalTotal() - (float) $this->total_price, 2);
    }

    public function hasEnoughStock(int $quantity = 1): bool
    {
        foreach ($this->bundleItems as $item) {
            if (!$item->product) {
                return false;
            }
            $required = $item->quantity * $quantity;
            if ($item->product->stock_quantity < $required) {
                return false;
            }
        }
        return true;
    }
}
