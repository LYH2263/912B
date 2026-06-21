<?php

namespace App\Http\Resources;

use App\Services\PricingEngineService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricing = app(PricingEngineService::class)->calculate($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'description' => $this->description,
            'original_price' => $pricing['original_price'],
            'price' => $pricing['final_price'],
            'final_price' => $pricing['final_price'],
            'has_discount' => $pricing['has_discount'],
            'has_markup' => $pricing['has_markup'],
            'discount_amount' => $pricing['discount_amount'],
            'discount_percent' => $pricing['discount_percent'],
            'applied_rule_id' => $pricing['applied_rule_id'],
            'applied_rule_name' => $pricing['applied_rule_name'],
            'cost_price' => $this->cost_price ? (float) $this->cost_price : null,
            'image' => $this->image,
            'images' => $this->images,
            'status' => $this->status,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'weight' => $this->weight ? (float) $this->weight : null,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
