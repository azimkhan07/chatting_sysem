<?php

declare(strict_types=1);

namespace Admin\Http\Controllers\Api\V1\Admin;

use Admin\Domain\App\Enums\ReportStatus;
use Admin\Domain\App\Services\ReportQueueService;
use Admin\Http\Controllers\Controller;
use Admin\Http\Requests\ResolveReportRequest;
use Admin\Http\Resources\ReportAdminResource;
use Admin\Support\ApiResponse;
use Admin\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff side of reports.
 *
 * This endpoint exists only in this app. A report queue names the accounts that
 * reported other accounts, which is not something that should be reachable
 * from the deployment the support team works on.
 */
final class ReportAdminController extends Controller
{
    public function __construct(
        private readonly ReportQueueService $reports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->reports->queue(
            limit: PageSize::clamp($request->integer('limit', 25), 25),
            cursor: $request->query('cursor'),
        );

        return ApiResponse::success(
            data: [
                'reports' => ReportAdminResource::collection($paginator->items())->resolve(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
            meta: [
                'has_more' => $paginator->hasMorePages(),
                'limit' => $paginator->perPage(),
            ],
        );
    }

    public function update(ResolveReportRequest $request, int $reportId): JsonResponse
    {
        $report = $this->reports->resolve(
            handlerId: (int) $request->user()->id,
            reportId: $reportId,
            status: ReportStatus::from($request->validated('status')),
            resolution: $request->validated('resolution'),
        );

        return ApiResponse::success(data: ['report' => (new ReportAdminResource($report))->resolve()]);
    }
}
