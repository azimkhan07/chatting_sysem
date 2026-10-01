<?php

declare(strict_types=1);

namespace Admin\Http\Controllers\Api\V1\Admin;

use Admin\Domain\App\Enums\SubscriptionStatus;
use Admin\Domain\App\Services\SubscriptionReviewService;
use Admin\Http\Controllers\Controller;
use Admin\Http\Resources\SubscriptionReviewResource;
use Admin\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionAdminController extends Controller
{
    public function __construct(
        private readonly SubscriptionReviewService $subscriptions,
    ) {}

    public function stats(): JsonResponse
    {
        return ApiResponse::success([
            'stats' => $this->subscriptions->stats(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $paginator = $this->subscriptions->all(
            (int) $request->integer('limit', 20),
            $status !== null ? SubscriptionStatus::tryFrom((string) $status) : null,
        );

        return ApiResponse::success([
            'subscriptions' => SubscriptionReviewResource::collection($paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    public function approve(Request $request, int $subscriptionId): JsonResponse
    {
        $subscription = $this->subscriptions->find($subscriptionId);

        if ($subscription === null) {
            return ApiResponse::error('NOT_FOUND', 'Subscription not found.', 404);
        }

        $staff = $request->user();
        $approved = $this->subscriptions->approve(
            (int) $staff->id,
            $subscription,
            (string) $staff->display_name,
        );

        return ApiResponse::success([
            'subscription' => new SubscriptionReviewResource($approved),
            'message' => 'Verification approved. The blue badge is now live on the profile.',
        ]);
    }

    public function reject(Request $request, int $subscriptionId): JsonResponse
    {
        $subscription = $this->subscriptions->find($subscriptionId);

        if ($subscription === null) {
            return ApiResponse::error('NOT_FOUND', 'Subscription not found.', 404);
        }

        $staff = $request->user();
        $rejected = $this->subscriptions->reject(
            (int) $staff->id,
            $subscription,
            (string) $staff->display_name,
        );

        return ApiResponse::success([
            'subscription' => new SubscriptionReviewResource($rejected),
            // Says what actually happened. The row is marked refunded, but no
            // money moves: this console does not call the payment gateway, and
            // the original backend/ behaviour is the same. Telling a member of
            // staff that a refund was issued when it was not is the kind of
            // message that gets a support ticket answered with "I was told it
            // was refunded" and no bank statement to show for it. The refund
            // itself is raised against the gateway separately.
            'message' => 'Verification rejected. The subscription is marked refunded and no badge was issued; the payment refund is raised with the gateway separately.',
        ]);
    }
}
