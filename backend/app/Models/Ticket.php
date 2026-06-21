<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    const CATEGORY_LOGISTICS = 'logistics';
    const CATEGORY_QUALITY = 'quality';
    const CATEGORY_REFUND = 'refund';

    const CATEGORY_LABELS = [
        self::CATEGORY_LOGISTICS => '物流问题',
        self::CATEGORY_QUALITY => '质量问题',
        self::CATEGORY_REFUND => '退款咨询',
    ];

    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    const PRIORITY_LABELS = [
        self::PRIORITY_HIGH => '高',
        self::PRIORITY_MEDIUM => '中',
        self::PRIORITY_LOW => '低',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const STATUS_LABELS = [
        self::STATUS_PENDING => '待处理',
        self::STATUS_PROCESSING => '处理中',
        self::STATUS_RESOLVED => '已解决',
        self::STATUS_CLOSED => '已关闭',
    ];

    protected $fillable = [
        'ticket_no',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'created_by',
        'assigned_to',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = [
        'category_label',
        'priority_label',
        'status_label',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at', 'desc');
    }

    public static function generateTicketNo(): string
    {
        return 'TK' . date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}
