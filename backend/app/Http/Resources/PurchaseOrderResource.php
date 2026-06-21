<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_no' => $this->purchase_order_no,
            'supplier_name' => $this->supplier_name,
            'supplier_contact' => $this->supplier_contact,
            'supplier_phone' => $this->supplier_phone,
            'expected_arrival_date' => $this->expected_arrival_date?->toDateString(),
            'actual_arrival_date' => $this->actual_arrival_date?->toDateString(),
            'total_amount' => (float) $this->total_amount,
            'remark' => $this->remark,
            'status' => $this->status,
            'status_text' => \App\Models\PurchaseOrder::STATUS_TEXT[$this->status] ?? $this->status,
            'total_quantity' => (int) $this->total_quantity,
            'total_received_quantity' => (int) $this->total_received_quantity,
            'is_fully_received' => $this->isFullyReceived(),
            'can_submit' => $this->canTransitionTo(\App\Models\PurchaseOrder::STATUS_PENDING),
            'can_stock_in' => in_array($this->status, [
                \App\Models\PurchaseOrder::STATUS_PENDING,
                \App\Models\PurchaseOrder::STATUS_PARTIAL,
            ]),
            'can_edit' => $this->status === \App\Models\PurchaseOrder::STATUS_DRAFT,
            'can_delete' => $this->status === \App\Models\PurchaseOrder::STATUS_DRAFT,
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                    'email' => $this->createdBy->email,
                ];
            }),
            'stock_in_by' => $this->whenLoaded('stockInBy', function () {
                return [
                    'id' => $this->stockInBy->id,
                    'name' => $this->stockInBy->name,
                    'email' => $this->stockInBy->email,
                ];
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
