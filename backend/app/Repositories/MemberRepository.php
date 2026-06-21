<?php

namespace App\Repositories;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MemberRepository
{
    public function findByUserId(int $userId): ?Member
    {
        return Member::where('user_id', $userId)->first();
    }

    public function getByUserIdOrCreate(int $userId): Member
    {
        $member = $this->findByUserId($userId);
        if ($member) {
            return $member;
        }

        return Member::create([
            'user_id' => $userId,
            'level' => Member::LEVEL_NORMAL,
            'total_consumption' => 0,
            'points' => 0,
        ]);
    }

    public function update(Member $member, array $data): Member
    {
        $member->update($data);
        return $member->fresh();
    }

    public function updatePoints(Member $member, int $points): Member
    {
        $member->points = $points;
        $member->save();
        return $member->fresh();
    }

    public function addConsumption(Member $member, float $amount): Member
    {
        $member->total_consumption += $amount;
        $newLevel = $member->calculateLevelByConsumption((float) $member->total_consumption);
        if ($newLevel !== $member->level) {
            $member->level = $newLevel;
        }
        $member->save();
        return $member->fresh();
    }
}
