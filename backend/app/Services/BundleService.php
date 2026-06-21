<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Product;
use App\Repositories\BundleRepository;
use Illuminate\Support\Facades\DB;

class BundleService
{
    public function __construct(
        private BundleRepository $repository
    ) {
    }

    public function create(array $data): Bundle
    {
        if ($this->repository->existsBySku($data['sku'])) {
            throw new \Exception('SKU 已存在，请使用其他 SKU');
        }

        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];

            if (count($items) < 2) {
                throw new \Exception('套餐至少需要包含 2 个子商品');
            }
            if (count($items) > 5) {
                throw new \Exception('套餐最多只能包含 5 个子商品');
            }

            $productIds = array_column($items, 'product_id');
            if (count($productIds) !== count(array_unique($productIds))) {
                throw new \Exception('套餐中不能包含重复的商品');
            }

            $originalTotal = 0;
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                if ($product->status !== 'active') {
                    throw new \Exception("商品 {$product->name} 未上架，不能加入套餐");
                }
                $qty = (int) ($item['quantity'] ?? 1);
                if ($qty < 1) {
                    throw new \Exception("商品 {$product->name} 的数量必须大于 0");
                }
                $originalTotal += (float) $product->price * $qty;
            }

            if ($data['total_price'] >= $originalTotal) {
                throw new \Exception("套餐总价（¥{$data['total_price']}）必须低于子商品单独购买总和（¥{$originalTotal}）");
            }

            $bundle = $this->repository->create([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'description' => $data['description'] ?? null,
                'image' => $data['image'] ?? null,
                'total_price' => $data['total_price'],
                'status' => $data['status'] ?? 'active',
            ]);

            foreach ($items as $item) {
                $bundle->bundleItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => (int) ($item['quantity'] ?? 1),
                ]);
            }

            return $bundle->load('bundleItems.product');
        });
    }

    public function update(Bundle $bundle, array $data): Bundle
    {
        if (isset($data['sku']) && $this->repository->existsBySku($data['sku'], $bundle->id)) {
            throw new \Exception('SKU 已存在，请使用其他 SKU');
        }

        return DB::transaction(function () use ($bundle, $data) {
            $items = $data['items'] ?? null;

            if ($items !== null) {
                if (count($items) < 2) {
                    throw new \Exception('套餐至少需要包含 2 个子商品');
                }
                if (count($items) > 5) {
                    throw new \Exception('套餐最多只能包含 5 个子商品');
                }

                $productIds = array_column($items, 'product_id');
                if (count($productIds) !== count(array_unique($productIds))) {
                    throw new \Exception('套餐中不能包含重复的商品');
                }
            }

            $totalPrice = $data['total_price'] ?? $bundle->total_price;

            $checkItems = $items !== null ? $items : $bundle->bundleItems->toArray();
            $originalTotal = 0;
            foreach ($checkItems as $item) {
                $productId = $item['product_id'];
                $product = Product::findOrFail($productId);
                if ($items !== null && $product->status !== 'active') {
                    throw new \Exception("商品 {$product->name} 未上架，不能加入套餐");
                }
                $qty = (int) ($item['quantity'] ?? 1);
                $originalTotal += (float) $product->price * $qty;
            }

            if ($totalPrice >= $originalTotal) {
                throw new \Exception("套餐总价（¥{$totalPrice}）必须低于子商品单独购买总和（¥{$originalTotal}）");
            }

            $updateData = [
                'total_price' => $totalPrice,
            ];
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            if (isset($data['sku'])) {
                $updateData['sku'] = $data['sku'];
            }
            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'];
            }
            if (array_key_exists('image', $data)) {
                $updateData['image'] = $data['image'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            $bundle = $this->repository->update($bundle, $updateData);

            if ($items !== null) {
                $bundle->bundleItems()->delete();
                foreach ($items as $item) {
                    $bundle->bundleItems()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => (int) ($item['quantity'] ?? 1),
                    ]);
                }
            }

            return $bundle->load('bundleItems.product');
        });
    }

    public function delete(Bundle $bundle): bool
    {
        if ($bundle->orderItems()->exists()) {
            return $bundle->delete();
        }

        $bundle->bundleItems()->delete();
        return $bundle->forceDelete();
    }

    public function toggleStatus(Bundle $bundle): Bundle
    {
        $status = $bundle->status === 'active' ? 'inactive' : 'active';
        return $this->repository->update($bundle, ['status' => $status]);
    }
}
