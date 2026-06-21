<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PARTIAL = 'partial';
    const STATUS_COMPLETED = 'completed';

    const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PENDING],
        self::STATUS_PENDING => [self::STATUS_PARTIAL, self::STATUS_COMPLETED],
        self::STATUS_PARTIAL => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
    ];

    const STATUS_TEXT = [
        self::STATUS_DRAFT => '草稿',
        self::STATUS_PENDING => '待入库',
        self::STATUS_PARTIAL => '部分入库',
        self::STATUS_COMPLETED => '已完成',
    ];

    protected $fillable = [
        'purchase_order_no',
        'supplier_name',
        'supplier_contact',
        'supplier_phone',
        'expected_arrival_date',
        'actual_arrival_date',
        'total_amount',
        'remark',
        'status',
        'created_by',
        'stock_in_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'expected_arrival_date' => 'date',
        'actual_arrival_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stock_in_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? []);
    }

    public static function generatePurchaseOrderNo(): string
    {
        return 'PO' . date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function isFullyReceived(): bool
    {
        foreach ($this->items as $item) {
            if ($item->received_quantity < $item->quantity) {
                return false;
            }
        }
        return true;
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getTotalReceivedQuantityAttribute(): int
    {
        return $this->items->sum('received_quantity');
    }
}
