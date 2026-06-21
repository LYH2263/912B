<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportApiController extends Controller
{
    public function __construct(
        private ReportBuilderService $service
    ) {
    }

    /**
     * 获取预设报表模板
     */
    public function templates(): JsonResponse
    {
        $templates = $this->service->getPresetTemplates();

        return response()->json(['data' => $templates]);
    }

    /**
     * 生成报表
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dimension' => 'required|string|in:day,category,product',
            'metrics' => 'required|array|min:1',
            'metrics.*' => 'string|in:order_count,sales_amount,refund_amount',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $reportData = $this->service->buildReport($validated);

        return response()->json(['data' => $reportData]);
    }

    /**
     * 导出 CSV
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'dimension' => 'required|string|in:day,category,product',
            'metrics' => 'required|array|min:1',
            'metrics.*' => 'string|in:order_count,sales_amount,refund_amount',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:10000',
        ]);

        $reportData = $this->service->buildReport($validated);
        $csv = $this->service->generateCsv($reportData);

        $filename = 'report_' . date('Ymd_His') . '.csv';

        return response()->stream(function () use ($csv) {
            echo "\xEF\xBB\xBF";
            echo $csv;
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
        ]);
    }

    /**
     * 获取可用维度和指标选项
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'dimensions' => ReportBuilderService::DIMENSIONS,
                'metrics' => ReportBuilderService::METRICS,
            ],
        ]);
    }
}
