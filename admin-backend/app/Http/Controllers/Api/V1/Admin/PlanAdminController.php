<?php

declare(strict_types=1);

namespace Admin\Http\Controllers\Api\V1\Admin;

use Admin\Domain\App\Models\Feature;
use Admin\Domain\App\Models\PlanPrice;
use Admin\Http\Controllers\Controller;
use Admin\Http\Requests\SavePlanPricingRequest;
use Admin\Support\ApiResponse;
use Admin\Support\Countries;
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
    /**
     * The full feature catalogue (key + label) seeded into the `features`
     * table. The admin subscription form renders one checkbox per row, so
     * adding a feature keyword in the DB surfaces it in the editor.
     */
    public function features(Request $request): JsonResponse
    {
        return ApiResponse::success(['features' => Feature::catalogue()]);
    }

    /**
     * The full country list + currency/symbol, so the subscription form and
     * user filters never hard-code a country table into the client, and picking
     * a country can autofill currency details.
     */
    public function countries(Request $request): JsonResponse
    {
        return ApiResponse::success(['countries' => Countries::all()]);
    }

    public function countriesForPlan(Request $request, string $plan): JsonResponse
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
        ]);
    }
}
