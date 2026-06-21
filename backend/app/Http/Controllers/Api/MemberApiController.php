<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberApiController extends Controller
{
    public function __construct(
        private MemberService $service
    ) {
    }

    public function me(): JsonResponse
    {
        $user = Auth::user();
        $member = $this->service->getMemberByUserId($user->id);

        return response()->json([
            'data' => [
                'id' => $member->id,
                'level' => $member->level,
                'level_label' => $member->level_label,
                'total_consumption' => (float) $member->total_consumption,
                'points' => $member->points,
                'next_threshold' => $member->getNextLevelThreshold(),
                'levels' => Member::LEVEL_LABELS,
                'thresholds' => Member::LEVEL_THRESHOLDS,
            ],
        ]);
    }

    public function pointLogs(Request $request): JsonResponse
    {
        $user = Auth::user();
        $filters = $request->only(['type', 'start_date', 'end_date']);
        $perPage = $request->get('per_page', 15);

        $logs = $this->service->getPointLogs($user->id, $filters, $perPage);

        $data = $logs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'type' => $log->type,
                'type_label' => $log->type_label,
                'points' => $log->points,
                'balance_after' => $log->balance_after,
                'description' => $log->description,
                'related_order_id' => $log->related_order_id,
                'related_order_no' => $log->relatedOrder?->order_no,
                'created_at' => $log->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function calculateDiscount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'total_amount' => 'required|numeric|min:0',
            'points' => 'required|integer|min:0',
        ]);

        $user = Auth::user();
        $member = $this->service->getMemberByUserId($user->id);

        $availablePoints = $member->points;
        $maxAllowedPoints = $this->service->calculateMaxPointsDiscount((float) $validated['total_amount']);
        $maxPoints = min($availablePoints, $maxAllowedPoints);

        $usePoints = min((int) $validated['points'], $maxPoints);
        $discountAmount = $this->service->calculatePointsDiscountAmount($usePoints);

        return response()->json([
            'data' => [
                'available_points' => $availablePoints,
                'max_allowed_points' => $maxAllowedPoints,
                'max_points' => $maxPoints,
                'use_points' => $usePoints,
                'discount_amount' => $discountAmount,
                'points_per_yuan' => MemberService::POINTS_PER_YUAN,
                'max_discount_ratio' => MemberService::MAX_POINTS_DISCOUNT_RATIO,
            ],
        ]);
    }
}
