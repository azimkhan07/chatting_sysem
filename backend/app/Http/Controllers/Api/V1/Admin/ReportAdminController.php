<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Moderation\Enums\ReportStatus;
use App\Domain\Moderation\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveReportRequest;
use App\Http\Resources\ReportAdminResource;
use App\Support\ApiResponse;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff side of reports.
 *
 * Behind the `admin` middleware on the route. The UI for this is deliberately
 * not in the user app: a moderator's queue names accounts that reported
 * accounts, and that list should not be reachable from the app any user has
 * installed.
 */
final class ReportAdminController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
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
