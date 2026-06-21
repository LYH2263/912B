<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointLog extends Model
{
    use HasFactory;

    const TYPE_EARN = 'earn';
    const TYPE_SPEND = 'spend';
    const TYPE_REFUND = 'refund';

    const TYPE_LABELS = [
        self::TYPE_EARN => '获得',
        self::TYPE_SPEND => '消费',
        self::TYPE_REFUND => '退回',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'points',
        'balance_after',
        'description',
        'related_order_id',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
