<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QualityInspectionResource;
use App\Models\Product;
use App\Models\QualityInspection;
use App\Services\QualityInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityInspectionApiController extends Controller
{
    public function __construct(
        private QualityInspectionService $service,
        private \App\Repositories\QualityInspectionRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'batch_no',
            'product_id',
            'purchase_order_id',
            'inspector',
            'has_unqualified',
            'start_date',
            'end_date',
        ]);
        $perPage = $request->get('per_page', 15);

        $inspections = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => QualityInspectionResource::collection($inspections->items()),
            'meta' => [
                'current_page' => $inspections->currentPage(),
                'per_page' => $inspections->perPage(),
                'total' => $inspections->total(),
                'last_page' => $inspections->lastPage(),
            ],
        ]);
    }

    public function show(QualityInspection $qualityInspection): JsonResponse
    {
        $qualityInspection->load('product', 'purchaseOrder', 'createdBy');
        return response()->json(['data' => new QualityInspectionResource($qualityInspection)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'qualified_quantity' => 'required|integer|min:0',
            'unqualified_quantity' => 'required|integer|min:0',
            'unqualified_reason' => 'nullable|string',
            'inspector' => 'required|string|max:100',
            'inspection_date' => 'required|date',
            'remark' => 'nullable|string',
        ]);

        try {
            $inspection = $this->service->create($validated);
            return response()->json(['data' => new QualityInspectionResource($inspection)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, QualityInspection $qualityInspection): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'qualified_quantity' => 'sometimes|integer|min:0',
            'unqualified_quantity' => 'sometimes|integer|min:0',
            'unqualified_reason' => 'nullable|string',
            'inspector' => 'sometimes|string|max:100',
            'inspection_date' => 'sometimes|date',
            'remark' => 'nullable|string',
        ]);

        try {
            $inspection = $this->service->update($qualityInspection, $validated);
            return response()->json(['data' => new QualityInspectionResource($inspection)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(QualityInspection $qualityInspection): JsonResponse
    {
        try {
            $this->service->delete($qualityInspection);
            return response()->json(['message' => '质检记录删除成功']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function productInspections(Request $request, Product $product): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $inspections = $this->repository->getByProductId($product->id, $perPage);

        return response()->json([
            'data' => QualityInspectionResource::collection($inspections->items()),
            'meta' => [
                'current_page' => $inspections->currentPage(),
                'per_page' => $inspections->perPage(),
                'total' => $inspections->total(),
                'last_page' => $inspections->lastPage(),
            ],
        ]);
    }
}
