<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Billing\Contracts\SubscriptionRepository;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionAdminController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $paginator = $this->subscriptions->all(
            (int) $request->integer('limit', 20),
            $status !== null ? SubscriptionStatus::tryFrom((string) $status) : null,
        );

        return ApiResponse::success([
            'subscriptions' => SubscriptionResource::collection($paginator->items()),
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

        $approved = $this->service->approve(
            (int) $request->user()->id,
            $subscription,
        );

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($approved),
            'message' => 'Verification approved. The blue badge is now live on the profile.',
        ]);
    }

    public function reject(Request $request, int $subscriptionId): JsonResponse
    {
        $subscription = $this->subscriptions->find($subscriptionId);

        if ($subscription === null) {
            return ApiResponse::error('NOT_FOUND', 'Subscription not found.', 404);
        }

        $rejected = $this->service->reject(
            (int) $request->user()->id,
            $subscription,
        );

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($rejected),
            'message' => 'Verification rejected and payment refunded.',
        ]);
    }
}
