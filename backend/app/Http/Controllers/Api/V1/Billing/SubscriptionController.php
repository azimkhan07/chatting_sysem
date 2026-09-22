<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Domain\Billing\Enums\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\ChoosePlanRequest;
use App\Http\Requests\Api\V1\Billing\PaySubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $service) {}

    public function tiers(): JsonResponse
    {
        return ApiResponse::success([
            'tiers' => $this->service->tiers(),
        ]);
    }

    public function verify(ChoosePlanRequest $request): JsonResponse
    {
        $subscription = $this->service->verify(
            (int) $request->user()->id,
            Plan::fromName($request->validated('plan')),
        );

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($subscription),
        ], status: 201);
    }

    public function checkout(ChoosePlanRequest $request): JsonResponse
    {
        $result = $this->service->checkout(
            (int) $request->user()->id,
            Plan::fromName($request->validated('plan')),
        );

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($result['subscription']),
            'gateway' => $result['gateway'],
            'client_token' => $result['client_token']->toString(),
            'amount_paisa' => $result['subscription']->amount_paisa,
        ]);
    }

    public function pay(PaySubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $updated = $this->service->recordPayment(
            $request->user(),
            $subscription,
            $request->validated('gateway'),
            $request->validated('token'),
        );

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($updated),
        ]);
    }

    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        $state = $this->service->stateFor($request->user(), $subscription);

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($state),
        ]);
    }

    public function destroy(Request $request, Subscription $subscription): JsonResponse
    {
        $cancelled = $this->service->cancel($request->user(), $subscription);

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($cancelled),
            'message' => 'Auto-renew turned off. Your badge stays until the paid period ends.',
        ]);
    }
}
