<?php

namespace App\Services;

use App\Models\PricingRule;
use App\Repositories\PricingRuleRepository;

class PricingRuleService
{
    public function __construct(
        private PricingRuleRepository $repository,
        private PricingEngineService $pricingEngine
    ) {
    }

    public function create(array $data): PricingRule
    {
        $this->validateAction($data);

        $rule = $this->repository->create($data);
        $this->pricingEngine->clearCache();

        return $rule;
    }

    public function update(PricingRule $rule, array $data): PricingRule
    {
        if (isset($data['action_type']) || isset($data['action_value'])) {
            $merged = array_merge([
                'action_type' => $rule->action_type,
                'action_value' => $rule->action_value,
            ], $data);
            $this->validateAction($merged);
        }

        $rule = $this->repository->update($rule, $data);
        $this->pricingEngine->clearCache();

        return $rule;
    }

    public function delete(PricingRule $rule): bool
    {
        $result = $this->repository->delete($rule);
        $this->pricingEngine->clearCache();

        return $result;
    }

    public function toggleActive(PricingRule $rule): PricingRule
    {
        $rule = $this->repository->toggleActive($rule);
        $this->pricingEngine->clearCache();

        return $rule;
    }

    private function validateAction(array $data): void
    {
        $actionType = $data['action_type'] ?? null;
        $actionValue = $data['action_value'] ?? null;

        if ($actionValue === null) {
            throw new \Exception('请输入规则值');
        }

        if ($actionType === 'discount_percent') {
            if ($actionValue <= 0 || $actionValue >= 100) {
                throw new \Exception('折扣百分比必须在 0 到 100 之间（不包含边界）');
            }
        }

        if ($actionType === 'markup_percent') {
            if ($actionValue <= 0) {
                throw new \Exception('加价百分比必须大于 0');
            }
        }

        if ($actionType === 'fixed_price') {
            if ($actionValue < 0) {
                throw new \Exception('固定价格不能小于 0');
            }
        }
    }
}
