<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'product_id',
        'flash_price',
        'activity_stock',
        'sold_count',
        'per_limit',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'flash_price' => 'decimal:2',
        'activity_stock' => 'integer',
        'sold_count' => 'integer',
        'per_limit' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function remainingStock(): int
    {
        return max(0, $this->activity_stock - $this->sold_count);
    }

    public function isNotStarted(): bool
    {
        return now()->lt($this->start_time);
    }

    public function isActive(): bool
    {
        return now()->gte($this->start_time) && now()->lte($this->end_time);
    }

    public function isEnded(): bool
    {
        return now()->gt($this->end_time);
    }

    public function syncStatus(): void
    {
        if ($this->isEnded() && $this->status !== 'ended') {
            $this->update(['status' => 'ended']);
        } elseif ($this->isActive() && $this->status !== 'active') {
            $this->update(['status' => 'active']);
        } elseif ($this->isNotStarted() && $this->status !== 'pending') {
            $this->update(['status' => 'pending']);
        }
    }
}
