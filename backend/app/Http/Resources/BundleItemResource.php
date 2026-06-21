<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BundleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'product' => $this->whenLoaded('product', function () {
                if (!$this->product) {
                    return null;
                }
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'price' => (float) $this->product->price,
                    'stock_quantity' => $this->product->stock_quantity,
                    'status' => $this->product->status,
                ];
            }),
        ];
    }
}
