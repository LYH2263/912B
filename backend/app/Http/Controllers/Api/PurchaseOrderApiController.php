<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderApiController extends Controller
{
    public function __construct(
        private PurchaseOrderService $service,
        private \App\Repositories\PurchaseOrderRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'purchase_order_no', 'supplier_name', 'start_date', 'end_date']);
        $perPage = $request->get('per_page', 15);

        $purchaseOrders = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => PurchaseOrderResource::collection($purchaseOrders->items()),
            'meta' => [
                'current_page' => $purchaseOrders->currentPage(),
                'per_page' => $purchaseOrders->perPage(),
                'total' => $purchaseOrders->total(),
                'last_page' => $purchaseOrders->lastPage(),
            ],
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load('items.product', 'createdBy', 'stockInBy');
        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'supplier_contact' => 'nullable|string|max:100',
            'supplier_phone' => 'nullable|string|max:20',
            'expected_arrival_date' => 'required|date',
            'remark' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'submit' => 'nullable|boolean',
        ]);

        try {
            $purchaseOrder = $this->service->create($validated);
            return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'supplier_contact' => 'nullable|string|max:100',
            'supplier_phone' => 'nullable|string|max:20',
            'expected_arrival_date' => 'nullable|date',
            'remark' => 'nullable|string',
            'items' => 'nullable|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.purchase_price' => 'required_with:items|numeric|min:0',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        try {
            $purchaseOrder = $this->service->update($purchaseOrder, $validated);
            return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->service->delete($purchaseOrder);
            return response()->json(['message' => '删除成功']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function submit(PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $purchaseOrder = $this->service->submit($purchaseOrder);
            return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function stockIn(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        try {
            $purchaseOrder = $this->service->stockIn($purchaseOrder, $validated['items']);
            return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
