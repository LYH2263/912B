<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportBuilderService
{
    const DIMENSION_DAY = 'day';
    const DIMENSION_CATEGORY = 'category';
    const DIMENSION_PRODUCT = 'product';

    const METRIC_ORDER_COUNT = 'order_count';
    const METRIC_SALES_AMOUNT = 'sales_amount';
    const METRIC_REFUND_AMOUNT = 'refund_amount';

    const DIMENSIONS = [
        self::DIMENSION_DAY => '按日',
        self::DIMENSION_CATEGORY => '按分类',
        self::DIMENSION_PRODUCT => '按商品',
    ];

    const METRICS = [
        self::METRIC_ORDER_COUNT => '订单数',
        self::METRIC_SALES_AMOUNT => '销售额',
        self::METRIC_REFUND_AMOUNT => '退款额',
    ];

    const PRESET_TEMPLATES = [
        [
            'id' => 'daily_sales',
            'name' => '每日销售报表',
            'description' => '按日统计订单数与销售额',
            'dimension' => self::DIMENSION_DAY,
            'metrics' => [self::METRIC_ORDER_COUNT, self::METRIC_SALES_AMOUNT],
            'default_days' => 30,
        ],
        [
            'id' => 'category_sales',
            'name' => '分类销售排行',
            'description' => '按分类统计销售额与订单数',
            'dimension' => self::DIMENSION_CATEGORY,
            'metrics' => [self::METRIC_SALES_AMOUNT, self::METRIC_ORDER_COUNT],
            'default_days' => 30,
        ],
        [
            'id' => 'product_top_sales',
            'name' => '商品销售TOP榜',
            'description' => '按商品统计销售额排行',
            'dimension' => self::DIMENSION_PRODUCT,
            'metrics' => [self::METRIC_SALES_AMOUNT, self::METRIC_ORDER_COUNT],
            'default_days' => 7,
        ],
    ];

    public function getPresetTemplates(): array
    {
        return self::PRESET_TEMPLATES;
    }

    public function buildReport(array $params): array
    {
        $dimension = $params['dimension'] ?? self::DIMENSION_DAY;
        $metrics = $params['metrics'] ?? [self::METRIC_ORDER_COUNT, self::METRIC_SALES_AMOUNT];
        $startDate = $params['start_date'] ?? now()->subDays(30)->toDateString();
        $endDate = $params['end_date'] ?? now()->toDateString();
        $limit = $params['limit'] ?? null;

        if (!isset(self::DIMENSIONS[$dimension])) {
            throw new InvalidArgumentException('无效的报表维度');
        }

        foreach ($metrics as $metric) {
            if (!isset(self::METRICS[$metric])) {
                throw new InvalidArgumentException("无效的指标: {$metric}");
            }
        }

        if (empty($metrics)) {
            throw new InvalidArgumentException('至少选择一个指标');
        }

        $query = $this->buildBaseQuery($dimension, $metrics, $startDate, $endDate);

        if ($limit) {
            $query->limit($limit);
        }

        $rows = $query->get()->toArray();

        return [
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSIONS[$dimension],
            'metrics' => $metrics,
            'metric_labels' => array_intersect_key(self::METRICS, array_flip($metrics)),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'columns' => $this->buildColumns($dimension, $metrics),
            'rows' => $rows,
            'totals' => $this->calculateTotals($rows, $metrics),
            'row_count' => count($rows),
        ];
    }

    protected function buildBaseQuery(string $dimension, array $metrics, string $startDate, string $endDate)
    {
        switch ($dimension) {
            case self::DIMENSION_DAY:
                return $this->buildDayQuery($metrics, $startDate, $endDate);
            case self::DIMENSION_CATEGORY:
                return $this->buildCategoryQuery($metrics, $startDate, $endDate);
            case self::DIMENSION_PRODUCT:
                return $this->buildProductQuery($metrics, $startDate, $endDate);
            default:
                throw new InvalidArgumentException('无效的报表维度');
        }
    }

    protected function buildDayQuery(array $metrics, string $startDate, string $endDate)
    {
        $selects = ['DATE(orders.created_at) as dimension_key', 'DATE(orders.created_at) as dimension_label'];

        foreach ($metrics as $metric) {
            $selects[] = $this->getMetricSelect($metric);
        }

        $hasItemMetrics = [self::METRIC_SALES_AMOUNT, self::METRIC_REFUND_AMOUNT];
        $needsItems = count(array_intersect($metrics, $hasItemMetrics)) > 0;

        if ($needsItems) {
            return DB::table('orders')
                ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
                ->selectRaw(implode(', ', $selects))
                ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('dimension_key', 'dimension_label')
                ->orderBy('dimension_key', 'asc');
        }

        return DB::table('orders')
            ->selectRaw(implode(', ', $selects))
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('dimension_key', 'dimension_label')
            ->orderBy('dimension_key', 'asc');
    }

    protected function buildCategoryQuery(array $metrics, string $startDate, string $endDate)
    {
        $selects = ['categories.id as dimension_key', 'categories.name as dimension_label'];

        foreach ($metrics as $metric) {
            $selects[] = $this->getMetricSelect($metric);
        }

        $query = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw(implode(', ', $selects))
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('dimension_key', 'dimension_label');

        if (in_array(self::METRIC_SALES_AMOUNT, $metrics)) {
            $query->orderByDesc('sales_amount');
        } else {
            $query->orderByDesc('order_count');
        }

        return $query;
    }

    protected function buildProductQuery(array $metrics, string $startDate, string $endDate)
    {
        $selects = ['products.id as dimension_key', 'products.name as dimension_label', 'products.sku as dimension_sub'];

        foreach ($metrics as $metric) {
            $selects[] = $this->getMetricSelect($metric);
        }

        $query = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw(implode(', ', $selects))
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('dimension_key', 'dimension_label', 'dimension_sub');

        if (in_array(self::METRIC_SALES_AMOUNT, $metrics)) {
            $query->orderByDesc('sales_amount');
        } else {
            $query->orderByDesc('order_count');
        }

        return $query;
    }

    protected function getMetricSelect(string $metric): string
    {
        switch ($metric) {
            case self::METRIC_ORDER_COUNT:
                return 'COUNT(DISTINCT orders.id) as order_count';
            case self::METRIC_SALES_AMOUNT:
                return 'COALESCE(SUM(order_items.subtotal), 0) as sales_amount';
            case self::METRIC_REFUND_AMOUNT:
                return "COALESCE(SUM(CASE WHEN orders.status = 'refunded' OR orders.status = 'cancelled' THEN order_items.subtotal ELSE 0 END), 0) as refund_amount";
            default:
                throw new InvalidArgumentException("无效的指标: {$metric}");
        }
    }

    protected function buildColumns(string $dimension, array $metrics): array
    {
        $columns = [
            [
                'key' => 'dimension_label',
                'label' => self::DIMENSIONS[$dimension],
                'type' => 'string',
            ],
        ];

        foreach ($metrics as $metric) {
            $columns[] = [
                'key' => $metric,
                'label' => self::METRICS[$metric],
                'type' => $metric === self::METRIC_ORDER_COUNT ? 'integer' : 'decimal',
            ];
        }

        return $columns;
    }

    protected function calculateTotals(array $rows, array $metrics): array
    {
        $totals = [];

        foreach ($metrics as $metric) {
            $totals[$metric] = array_reduce($rows, function ($sum, $row) use ($metric) {
                return $sum + ($row->$metric ?? 0);
            }, 0);
        }

        return $totals;
    }

    public function generateCsv(array $reportData): string
    {
        $columns = $reportData['columns'];
        $rows = $reportData['rows'];

        $headers = array_map(function ($col) {
            return $col['label'];
        }, $columns);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $line = [];
            foreach ($columns as $col) {
                $line[] = $rowArray[$col['key']] ?? '';
            }
            fputcsv($output, $line);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
