<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PricingRule;
use Illuminate\Support\Facades\Cache;

class PricingEngineService
{
    private const CACHE_KEY = 'pricing_rules:active';
    private const CACHE_TTL = 300;

    public function calculate(Product $product): array
    {
        $originalPrice = (float) $product->price;
        $finalPrice = $originalPrice;
        $appliedRule = null;

        $rules = $this->getActiveRules();

        foreach ($rules as $rule) {
            if ($rule->matches($product)) {
                $finalPrice = $rule->apply($originalPrice);
                $appliedRule = $rule;
                break;
            }
        }

        return [
            'original_price' => $originalPrice,
            'final_price' => $finalPrice,
            'has_discount' => $finalPrice < $originalPrice,
            'has_markup' => $finalPrice > $originalPrice,
            'applied_rule_id' => $appliedRule?->id,
            'applied_rule_name' => $appliedRule?->name,
            'discount_amount' => max(0, round($originalPrice - $finalPrice, 2)),
            'discount_percent' => $originalPrice > 0
                ? round((($originalPrice - $finalPrice) / $originalPrice) * 100, 2)
                : 0,
        ];
    }

    public function calculateForProducts(iterable $products): array
    {
        $results = [];
        foreach ($products as $product) {
            $results[$product->id] = $this->calculate($product);
        }
        return $results;
    }

    public function getActiveRules()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return PricingRule::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'asc')
                ->get();
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
