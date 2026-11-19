<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Return the sales report for the requested period.
     */
    public function sales(SalesReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reportService->sales(
                $request->validated('from'),
                $request->validated('to'),
            ),
        ]);
    }
}
