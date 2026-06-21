<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QualityInspectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'batch_no' => $this->batch_no,
            'product_id' => $this->product_id,
            'purchase_order_id' => $this->purchase_order_id,
            'qualified_quantity' => (int) $this->qualified_quantity,
            'unqualified_quantity' => (int) $this->unqualified_quantity,
            'total_quantity' => (int) $this->total_quantity,
            'pass_rate' => (float) $this->pass_rate,
            'unqualified_reason' => $this->unqualified_reason,
            'inspector' => $this->inspector,
            'inspection_date' => $this->inspection_date?->toDateString(),
            'remark' => $this->remark,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'image' => $this->product->image,
                ];
            }),
            'purchase_order' => $this->whenLoaded('purchaseOrder', function () {
                return $this->purchaseOrder ? [
                    'id' => $this->purchaseOrder->id,
                    'purchase_order_no' => $this->purchaseOrder->purchase_order_no,
                    'supplier_name' => $this->purchaseOrder->supplier_name,
                ] : null;
            }),
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                    'email' => $this->createdBy->email,
                ];
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
