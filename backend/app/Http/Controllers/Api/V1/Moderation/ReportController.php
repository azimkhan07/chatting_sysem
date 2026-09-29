<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Enums\ReportTargetType;
use App\Domain\Moderation\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Blocking and reporting, for the person doing them.
 *
 * The staff queue lives on ReportAdminController. Keeping them apart is not
 * tidiness: filing a report is something any authenticated user does, reading
 * the queue exposes other people's complaints and the accounts they name, and
 * the two should not sit behind the same controller where a future route edit
 * can blur them.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = $this->reports->report(
            reporter: $request->user(),
            targetType: ReportTargetType::from($request->validated('target_type')),
            targetId: (int) $request->validated('target_id'),
            reason: ReportReason::from($request->validated('reason')),
            details: $request->validated('details'),
        );

        return ApiResponse::success(
            data: ['report' => (new ReportResource($report))->resolve()],
            status: 201,
        );
    }

    /**
     * The reasons and target types the client renders, so the picker is driven
     * by the backend's list instead of a hardcoded copy in the frontend that
     * can offer a reason the server will reject.
     */
    public function reasons(): JsonResponse
    {
        return ApiResponse::success(data: [
            'reasons' => array_map(
                static fn (ReportReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                    'is_urgent' => $reason->isUrgent(),
                ],
                ReportReason::cases(),
            ),
            'target_types' => array_map(
                static fn (ReportTargetType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                ReportTargetType::cases(),
            ),
        ]);
    }
}
