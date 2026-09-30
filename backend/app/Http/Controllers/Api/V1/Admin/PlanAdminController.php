<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Plans\Models\PlanPrice;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavePlanPricingRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Country-wise plan pricing + feature unlocks.
 *
 * `GET countries` feeds the admin form's existing values; `POST pricing` upserts
 * the row for (plan, country), storing currency + symbol verbatim from the
 * request (the admin UI auto-fills them when a country is picked).
 */
final class PlanAdminController extends Controller
{
    public function countries(Request $request, string $plan): JsonResponse
    {
        $rows = PlanPrice::query()
            ->where('plan', $plan)
            ->get()
            ->map(static fn (PlanPrice $p) => [
                'id' => $p->id,
                'country' => $p->country,
                'currency' => $p->currency,
                'currency_symbol' => $p->currency_symbol,
                'price_month_paisa' => $p->price_month_paisa,
                'features' => $p->features ?? [],
            ]);

        return ApiResponse::success(['rows' => $rows->values()]);
    }

    public function save(SavePlanPricingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $row = PlanPrice::query()->updateOrCreate(
            ['plan' => $data['plan'], 'country' => strtoupper($data['country'])],
            [
                'currency' => $data['currency'],
                'currency_symbol' => $data['currency_symbol'],
                'price_month_paisa' => $data['price_month_paisa'],
                'features' => array_values(array_unique($data['features'])),
                'active' => true,
            ],
        );

        return ApiResponse::success([
            'saved' => true,
            'row' => [
                'id' => $row->id,
                'plan' => $row->plan,
                'country' => $row->country,
                'currency' => $row->currency,
                'currency_symbol' => $row->currency_symbol,
                'price_month_paisa' => $row->price_month_paisa,
                'features' => $row->features ?? [],
            ],
        ], status: 201);
    }
}