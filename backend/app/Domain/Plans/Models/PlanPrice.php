<?php

declare(strict_types=1);

namespace App\Domain\Plans\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Country-wise price + feature unlocks for a subscription plan.
 *
 * @property int $id
 * @property string $plan
 * @property string $country
 * @property string $currency
 * @property string $currency_symbol
 * @property int $price_month_paisa
 * @property array<string>|null $features
 * @property bool $active
 */
final class PlanPrice extends Model
{
    protected $fillable = [
        'plan',
        'country',
        'currency',
        'currency_symbol',
        'price_month_paisa',
        'features',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_month_paisa' => 'integer',
            'features' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * Feature keys this plan unlocks for the given user's country.
     *
     * Returns the full known list only when a user has no country set (pre
     * Phase 2 geocode) so nothing is ever locked out by missing data.
     *
     * @return list<string>
     */
    public static function unlockedFor(string $plan, ?string $country = null): array
    {
        if ($country !== null) {
            $price = self::query()->where('plan', $plan)->where('country', $country)->first();

            if ($price !== null && is_array($price->features)) {
                return $price->features;
            }
        }

        $fallback = self::query()->where('plan', $plan)->where('country', 'IN')->first();

        return $fallback !== null && is_array($fallback->features) ? $fallback->features : [];
    }
}