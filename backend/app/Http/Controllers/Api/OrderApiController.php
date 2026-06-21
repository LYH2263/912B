<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderSplitMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderApiController extends Controller
{
    public function __construct(
        private OrderService $service,
        private OrderSplitMergeService $splitMergeService,
        private \App\Repositories\OrderRepository $repository
    ) {
    }

    /**
     * 获取订单列表
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'order_no', 'start_date', 'end_date']);
        $perPage = $request->get('per_page', 15);

        $orders = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * 获取订单详情
     */
    public function show(Order $order): JsonResponse
    {
        $order->load('orderItems.product');
        return response()->json(['data' => new OrderResource($order)]);
    }

    /**
     * 创建订单
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'discount_amount' => 'nullable|numeric|min:0',
            'points_used' => 'nullable|integer|min:0',
            'user_id' => 'nullable|integer|exists:users,id',
            'shipping_address' => 'nullable|string',
            'shipping_name' => 'nullable|string|max:100',
            'shipping_phone' => 'nullable|string|max:20',
            'remark' => 'nullable|string',
        ]);

        try {
            $order = $this->service->create($validated);
            return response()->json(['data' => new OrderResource($order)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * 更新订单状态
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,shipped,completed,cancelled',
        ]);

        try {
            $order = $this->service->updateStatus($order, $validated['status']);
            return response()->json(['data' => new OrderResource($order)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function split(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'split_item_ids' => 'required|array|min:1',
            'split_item_ids.*' => 'required|integer|exists:order_items,id',
        ]);

        try {
            $result = $this->splitMergeService->split($order, $validated['split_item_ids']);
            return response()->json([
                'data' => [
                    'order_1' => new OrderResource($result['order_1']),
                    'order_2' => new OrderResource($result['order_2']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id_1' => 'required|integer|exists:orders,id',
            'order_id_2' => 'required|integer|exists:orders,id|different:order_id_1',
        ]);

        try {
            $order1 = Order::findOrFail($validated['order_id_1']);
            $order2 = Order::findOrFail($validated['order_id_2']);
            $mergedOrder = $this->splitMergeService->merge($order1, $order2);
            return response()->json(['data' => new OrderResource($mergedOrder)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function mergeCandidates(Request $request): JsonResponse
    {
        $orderId = $request->get('order_id');
        $order = Order::findOrFail($orderId);

        if ($order->status !== 'pending') {
            return response()->json(['data' => []]);
        }

        $candidates = Order::where('user_id', $order->user_id)
            ->where('id', '!=', $order->id)
            ->where('status', 'pending')
            ->whereNotNull('user_id')
            ->with('orderItems')
            ->get();

        return response()->json(['data' => OrderResource::collection($candidates)]);
    }
}
