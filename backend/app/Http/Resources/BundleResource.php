<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BundleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $originalTotal = 0;
        $canPurchase = true;

        if ($this->relationLoaded('bundleItems')) {
            foreach ($this->bundleItems as $item) {
                if ($item->product) {
                    $originalTotal += (float) $item->product->price * $item->quantity;
                    if ($item->product->stock_quantity < $item->quantity || $item->product->status !== 'active') {
                        $canPurchase = false;
                    }
                }
            }
        }

        $originalTotal = round($originalTotal, 2);
        $discountAmount = round($originalTotal - (float) $this->total_price, 2);
        $discountPercent = $originalTotal > 0 ? round($discountAmount / $originalTotal * 100, 1) : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'image' => $this->image,
            'total_price' => (float) $this->total_price,
            'original_total' => $originalTotal,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'status' => $this->status,
            'can_purchase' => $canPurchase && $this->status === 'active',
            'bundle_items' => BundleItemResource::collection($this->whenLoaded('bundleItems')),
            'item_count' => $this->whenLoaded('bundleItems', $this->bundleItems->count()),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
