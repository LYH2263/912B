<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'purchase_price' => (float) $this->purchase_price,
            'quantity' => (int) $this->quantity,
            'received_quantity' => (int) $this->received_quantity,
            'remaining_quantity' => (int) $this->remaining_quantity,
            'subtotal' => (float) $this->subtotal,
            'is_fully_received' => $this->isFullyReceived(),
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'stock_quantity' => (int) $this->product->stock_quantity,
                    'cost_price' => (float) $this->product->cost_price,
                ];
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
