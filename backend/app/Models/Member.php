<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    const LEVEL_NORMAL = 'normal';
    const LEVEL_SILVER = 'silver';
    const LEVEL_GOLD = 'gold';

    const LEVEL_LABELS = [
        self::LEVEL_NORMAL => '普通会员',
        self::LEVEL_SILVER => '银卡会员',
        self::LEVEL_GOLD => '金卡会员',
    ];

    const LEVEL_THRESHOLDS = [
        self::LEVEL_NORMAL => 0,
        self::LEVEL_SILVER => 1000,
        self::LEVEL_GOLD => 5000,
    ];

    protected $fillable = [
        'user_id',
        'level',
        'total_consumption',
        'points',
    ];

    protected $casts = [
        'total_consumption' => 'decimal:2',
        'points' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(PointLog::class, 'user_id', 'user_id');
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVEL_LABELS[$this->level] ?? self::LEVEL_LABELS[self::LEVEL_NORMAL];
    }

    public function getNextLevelThreshold(): ?float
    {
        $levels = array_keys(self::LEVEL_THRESHOLDS);
        $currentIndex = array_search($this->level, $levels);

        if ($currentIndex === false || $currentIndex >= count($levels) - 1) {
            return null;
        }

        return self::LEVEL_THRESHOLDS[$levels[$currentIndex + 1]];
    }

    public function calculateLevelByConsumption(float $consumption): string
    {
        $level = self::LEVEL_NORMAL;
        foreach (self::LEVEL_THRESHOLDS as $lvl => $threshold) {
            if ($consumption >= $threshold) {
                $level = $lvl;
            }
        }
        return $level;
    }
}
