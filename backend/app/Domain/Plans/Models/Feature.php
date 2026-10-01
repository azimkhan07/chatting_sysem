<?php

declare(strict_types=1);

namespace App\Domain\Plans\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A feature flag shown as a checkbox on the admin subscription form and
 * stored on {@see PlanPrice} as part of the `features` array.
 *
 * The rows are not hand-maintained. {@see \App\Domain\Chat\FeatureCatalogueSync}
 * writes this table from the `ChatFeature` enum on every boot, so adding a case
 * to the enum is the entire registration step and the console needs no rebuild.
 *
 * @property-read int $id
 * @property-read string $key
 * @property-read string $label
 * @property-read string|null $blurb
 * @property-read int $sort_order
 * @property-read bool $active
 * @property-read string $tier
 */
final class Feature extends Model
{
    /** Fallback for rows written before the `tier` column existed. */
    public const FREE_TIER = 'free';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'label',
        'blurb',
        'sort_order',
        'active',
        'tier',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * The catalogue the admin console's subscription form draws its checkboxes
     * from: every active feature, with the tier that says whether ticking it
     * means anything.
     *
     * Only premium features become checkboxes. A free feature is unlocked for
     * every account regardless of plan, so offering a box to tick it onto a paid
     * plan would be a control that changes nothing - and would sit next to the
     * ones that do, reading as "this is what you are buying".
     *
     * Returns the same shape as the console's own `Admin\Domain\App\Models\
     * Feature::catalogue()`, because the admin service calls that one and never
     * this one; the two are the same query against the same table and there is
     * no reason for them to answer differently.
     *
     * @return list<array{key: string, label: string, blurb: string|null, tier: string}>
     */
    public static function catalogue(): array
    {
        return self::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(static fn (Feature $feature): array => [
                'key' => $feature->key,
                'label' => $feature->label,
                'blurb' => $feature->blurb,
                'tier' => $feature->tier ?? self::FREE_TIER,
            ])
            ->all();
    }
}