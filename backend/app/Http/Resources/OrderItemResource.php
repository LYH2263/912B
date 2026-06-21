<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'item_type' => $this->item_type,
            'bundle_id' => $this->bundle_id,
            'bundle_detail' => $this->bundle_detail,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'product_price' => (float) $this->product_price,
            'quantity' => $this->quantity,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
