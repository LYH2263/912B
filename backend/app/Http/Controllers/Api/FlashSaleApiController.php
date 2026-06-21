<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FlashSaleResource;
use App\Http\Resources\OrderResource;
use App\Models\FlashSale;
use App\Services\FlashSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashSaleApiController extends Controller
{
    public function __construct(
        private FlashSaleService $service,
        private \App\Repositories\FlashSaleRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search']);
        $perPage = $request->get('per_page', 15);

        $this->service->syncAllStatuses();

        $flashSales = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => FlashSaleResource::collection($flashSales->items()),
            'meta' => [
                'current_page' => $flashSales->currentPage(),
                'per_page' => $flashSales->perPage(),
                'total' => $flashSales->total(),
                'last_page' => $flashSales->lastPage(),
            ],
        ]);
    }

    public function show(FlashSale $flashSale): JsonResponse
    {
        $flashSale->syncStatus();
        $flashSale->load('product');
        return response()->json(['data' => new FlashSaleResource($flashSale)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'product_id' => 'required|exists:products,id',
            'flash_price' => 'required|numeric|min:0.01',
            'activity_stock' => 'required|integer|min:1',
            'per_limit' => 'nullable|integer|min:1',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        try {
            $flashSale = $this->service->create($validated);
            return response()->json(['data' => new FlashSaleResource($flashSale->load('product'))], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'product_id' => 'sometimes|exists:products,id',
            'flash_price' => 'sometimes|numeric|min:0.01',
            'activity_stock' => 'sometimes|integer|min:1',
            'per_limit' => 'nullable|integer|min:1',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',
        ]);

        try {
            $flashSale = $this->service->update($flashSale, $validated);
            return response()->json(['data' => new FlashSaleResource($flashSale->load('product'))]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(FlashSale $flashSale): JsonResponse
    {
        try {
            $this->service->delete($flashSale);
            return response()->json(['message' => '秒杀活动删除成功']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function activeList(): JsonResponse
    {
        $this->service->syncAllStatuses();
        $activeList = $this->repository->getActiveList();
        $upcomingList = $this->repository->getUpcomingList();

        return response()->json([
            'data' => [
                'active' => FlashSaleResource::collection($activeList),
                'upcoming' => FlashSaleResource::collection($upcomingList),
            ],
        ]);
    }

    public function placeOrder(Request $request, FlashSale $flashSale): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'shipping_name' => 'nullable|string|max:100',
            'shipping_phone' => 'nullable|string|max:20',
            'shipping_address' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        try {
            $order = $this->service->placeOrder(
                $flashSale->id,
                $validated['quantity'],
                $request->only(['shipping_name', 'shipping_phone', 'shipping_address', 'remark'])
            );
            return response()->json(['data' => new OrderResource($order)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
