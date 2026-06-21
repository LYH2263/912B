<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Order;
use App\Models\PointLog;
use App\Repositories\MemberRepository;
use App\Repositories\PointLogRepository;
use Illuminate\Support\Facades\Log;

class MemberService
{
    public const POINTS_PER_YUAN = 100;
    public const POINTS_EARN_RATE = 0.01;
    public const MAX_POINTS_DISCOUNT_RATIO = 0.2;

    public function __construct(
        public MemberRepository $memberRepository,
        public PointLogRepository $pointLogRepository
    ) {
    }

    public function getMemberByUserId(int $userId): Member
    {
        return $this->memberRepository->getByUserIdOrCreate($userId);
    }

    public function calculateMaxPointsDiscount(float $orderTotalAmount): int
    {
        $maxDiscountAmount = $orderTotalAmount * self::MAX_POINTS_DISCOUNT_RATIO;
        return (int) floor($maxDiscountAmount * self::POINTS_PER_YUAN);
    }

    public function calculatePointsDiscountAmount(int $points): float
    {
        return $points / self::POINTS_PER_YUAN;
    }

    public function calculateEarnedPoints(float $finalAmount): int
    {
        return (int) floor($finalAmount * self::POINTS_EARN_RATE * self::POINTS_PER_YUAN);
    }

    public function usePoints(int $userId, int $points, Order $order): void
    {
        if ($points <= 0) {
            return;
        }

        $member = $this->memberRepository->getByUserIdOrCreate($userId);

        if ($member->points < $points) {
            throw new \Exception('积分不足');
        }

        $newBalance = $member->points - $points;
        $this->memberRepository->updatePoints($member, $newBalance);

        $this->pointLogRepository->create([
            'user_id' => $userId,
            'type' => PointLog::TYPE_SPEND,
            'points' => -$points,
            'balance_after' => $newBalance,
            'description' => '订单抵扣消费',
            'related_order_id' => $order->id,
        ]);

        Log::info('积分抵扣使用', [
            'user_id' => $userId,
            'order_id' => $order->id,
            'points' => $points,
            'balance_after' => $newBalance,
        ]);
    }

    public function refundPoints(int $userId, int $points, Order $order, string $description = '订单取消退回积分'): void
    {
        if ($points <= 0) {
            return;
        }

        $member = $this->memberRepository->getByUserIdOrCreate($userId);
        $newBalance = $member->points + $points;
        $this->memberRepository->updatePoints($member, $newBalance);

        $this->pointLogRepository->create([
            'user_id' => $userId,
            'type' => PointLog::TYPE_REFUND,
            'points' => $points,
            'balance_after' => $newBalance,
            'description' => $description,
            'related_order_id' => $order->id,
        ]);

        Log::info('积分退回', [
            'user_id' => $userId,
            'order_id' => $order->id,
            'points' => $points,
            'balance_after' => $newBalance,
        ]);
    }

    public function earnPointsAndUpgrade(int $userId, float $consumptionAmount, Order $order): array
    {
        $member = $this->memberRepository->getByUserIdOrCreate($userId);

        $oldLevel = $member->level;
        $earnedPoints = (int) floor($consumptionAmount * self::POINTS_EARN_RATE * self::POINTS_PER_YUAN);

        $newBalance = $member->points + $earnedPoints;
        $this->memberRepository->updatePoints($member, $newBalance);

        $member = $this->memberRepository->addConsumption($member, $consumptionAmount);
        $newLevel = $member->level;

        if ($earnedPoints > 0) {
            $this->pointLogRepository->create([
                'user_id' => $userId,
                'type' => PointLog::TYPE_EARN,
                'points' => $earnedPoints,
                'balance_after' => $member->points,
                'description' => '订单消费返积分',
                'related_order_id' => $order->id,
            ]);
        }

        $levelUpgraded = $oldLevel !== $newLevel;
        if ($levelUpgraded) {
            Log::info('会员等级升级', [
                'user_id' => $userId,
                'order_id' => $order->id,
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
                'total_consumption' => $member->total_consumption,
            ]);
        }

        return [
            'earned_points' => $earnedPoints,
            'level_upgraded' => $levelUpgraded,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'member' => $member,
        ];
    }

    public function getPointLogs(int $userId, array $filters = [], int $perPage = 15)
    {
        return $this->pointLogRepository->paginateByUserId($userId, $filters, $perPage);
    }
}
