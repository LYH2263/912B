<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_no',
        'product_id',
        'purchase_order_id',
        'qualified_quantity',
        'unqualified_quantity',
        'unqualified_reason',
        'inspector',
        'inspection_date',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'qualified_quantity' => 'integer',
        'unqualified_quantity' => 'integer',
        'inspection_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateBatchNo(): string
    {
        return 'QI' . date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->qualified_quantity + $this->unqualified_quantity;
    }

    public function getPassRateAttribute(): float
    {
        $total = $this->qualified_quantity + $this->unqualified_quantity;
        if ($total === 0) {
            return 0;
        }
        return round(($this->qualified_quantity / $total) * 100, 2);
    }
}
