<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PricingRuleResource;
use App\Models\PricingRule;
use App\Services\PricingRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingRuleApiController extends Controller
{
    public function __construct(
        private PricingRuleService $service,
        private \App\Repositories\PricingRuleRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'search']);
        $perPage = $request->get('per_page', 15);

        $rules = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => PricingRuleResource::collection($rules->items()),
            'meta' => [
                'current_page' => $rules->currentPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
                'last_page' => $rules->lastPage(),
            ],
        ]);
    }

    public function show(PricingRule $pricingRule): JsonResponse
    {
        return response()->json(['data' => new PricingRuleResource($pricingRule)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'conditions' => 'required|array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string|in:=,!=,>,>=,<,<=,in,not_in',
            'conditions.*.value' => 'present',
            'action_type' => 'required|string|in:discount_percent,markup_percent,fixed_price',
            'action_value' => 'required|numeric',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        try {
            $rule = $this->service->create($validated);
            return response()->json(['data' => new PricingRuleResource($rule)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, PricingRule $pricingRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'conditions' => 'sometimes|array',
            'conditions.*.field' => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string|in:=,!=,>,>=,<,<=,in,not_in',
            'conditions.*.value' => 'present_with:conditions',
            'action_type' => 'sometimes|string|in:discount_percent,markup_percent,fixed_price',
            'action_value' => 'sometimes|numeric',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        try {
            $rule = $this->service->update($pricingRule, $validated);
            return response()->json(['data' => new PricingRuleResource($rule)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(PricingRule $pricingRule): JsonResponse
    {
        try {
            $this->service->delete($pricingRule);
            return response()->json(['message' => '定价规则删除成功']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function toggleActive(PricingRule $pricingRule): JsonResponse
    {
        try {
            $rule = $this->service->toggleActive($pricingRule);
            return response()->json(['data' => new PricingRuleResource($rule)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
