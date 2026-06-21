<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BundleResource;
use App\Models\Bundle;
use App\Services\BundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleApiController extends Controller
{
    public function __construct(
        private BundleService $service,
        private \App\Repositories\BundleRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search']);
        $perPage = $request->get('per_page', 15);

        $bundles = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => BundleResource::collection($bundles->items()),
            'meta' => [
                'current_page' => $bundles->currentPage(),
                'per_page' => $bundles->perPage(),
                'total' => $bundles->total(),
                'last_page' => $bundles->lastPage(),
            ],
        ]);
    }

    public function show(Bundle $bundle): JsonResponse
    {
        $bundle->load('bundleItems.product');
        return response()->json(['data' => new BundleResource($bundle)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'sku' => 'required|string|max:100',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'total_price' => 'required|numeric|min:0',
            'status' => 'sometimes|in:active,inactive',
            'items' => 'required|array|min:2|max:5',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $bundle = $this->service->create($validated);
            return response()->json(['data' => new BundleResource($bundle)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, Bundle $bundle): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'sku' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'total_price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,inactive',
            'items' => 'sometimes|array|min:2|max:5',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        try {
            $bundle = $this->service->update($bundle, $validated);
            return response()->json(['data' => new BundleResource($bundle)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Bundle $bundle): JsonResponse
    {
        try {
            $this->service->delete($bundle);
            return response()->json(['message' => '套餐删除成功']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function toggleActive(Request $request, Bundle $bundle): JsonResponse
    {
        try {
            $bundle = $this->service->toggleStatus($bundle);
            return response()->json(['data' => new BundleResource($bundle)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
