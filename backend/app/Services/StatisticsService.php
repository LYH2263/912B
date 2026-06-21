<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    public function getDashboardSummary(): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $totalInventoryValue = (float) Product::query()
            ->selectRaw('COALESCE(SUM(price * stock_quantity), 0) as total')
            ->value('total');

        $member = null;
        $user = Auth::user();
        if ($user) {
            $memberData = Member::where('user_id', $user->id)->first();
            if ($memberData) {
                $member = [
                    'level' => $memberData->level,
                    'level_label' => $memberData->level_label,
                    'total_consumption' => (float) $memberData->total_consumption,
                    'points' => $memberData->points,
                    'next_threshold' => $memberData->getNextLevelThreshold(),
                ];
            } else {
                $member = [
                    'level' => Member::LEVEL_NORMAL,
                    'level_label' => Member::LEVEL_LABELS[Member::LEVEL_NORMAL],
                    'total_consumption' => 0,
                    'points' => 0,
                    'next_threshold' => Member::LEVEL_THRESHOLDS[Member::LEVEL_SILVER],
                ];
            }
        }

        return [
            'products' => [
                'total' => Product::count(),
                'active' => Product::where('status', 'active')->count(),
                'inactive' => Product::where('status', 'inactive')->count(),
                'out_of_stock' => Product::where('stock_quantity', 0)->count(),
            ],
            'orders' => [
                'today_count' => Order::whereDate('created_at', $today)->count(),
                'today_amount' => Order::whereDate('created_at', $today)->sum('final_amount'),
                'month_count' => Order::whereDate('created_at', '>=', $thisMonth)->count(),
                'month_amount' => Order::whereDate('created_at', '>=', $thisMonth)->sum('final_amount'),
                'pending' => Order::whereIn('status', ['pending', 'paid'])->count(),
            ],
            'inventory' => [
                'total_value' => $totalInventoryValue,
            ],
            'member' => $member,
        ];
    }

    /**
     * 获取订单趋势数据
     */
    public function getOrderTrends(int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        
        $orders = Order::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(final_amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $orders->map(function ($order) {
            return [
                'date' => $order->date,
                'count' => (int) $order->count,
                'amount' => (float) $order->amount,
            ];
        })->toArray();
    }

    /**
     * 获取商品销售排行
     */
    public function getProductSalesRanking(int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->selectRaw('products.id, products.name, SUM(order_items.quantity) as total_quantity, SUM(order_items.subtotal) as total_amount')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
