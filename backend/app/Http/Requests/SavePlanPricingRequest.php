<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'features.*' => ['string', 'max:40'],
        ];
    }
}