<?php

declare(strict_types=1);

namespace Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavePlanPricingRequest extends FormRequest
{
    /**
     * @return array{plan: string[], country: string[], currency: string[], currency_symbol: string[], price_month_paisa: string[], features: string[]}
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'in:simple,standard,premium'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'max:8'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'price_month_paisa' => ['required', 'integer', 'min:0'],
            'features' => ['present', 'array'],
            // Every keyword on a plan has to be a live catalogue entry.
            //
            // This used to accept any string, which is how a plan ended up
            // selling `calls` - a keyword that appears in no code path and unlocks
            // nothing. `exists` closes that: a plan can only name a feature the
            // app actually implements.
            //
            // It is checked against the catalogue rather than a hard-coded list
            // so that adding a feature in code is still the only step. Retired
            // keywords are rejected rather than silently accepted, so the console
            // must strip them from a row before it re-saves that row - otherwise
            // the first edit of a plan that predates the retirement would be
            // impossible to save.
            'features.*' => [
                'string',
                'max:40',
                Rule::exists('app.features', 'key')->where('active', true),
            ],
        ];
    }
}
