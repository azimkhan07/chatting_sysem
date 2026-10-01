<?php

declare(strict_types=1);

namespace Admin\Domain\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A feature flag shown as a checkbox on the admin subscription form and
 * stored on {@see PlanPrice} as part of the `features` array.
 *
 * Keeping this in the database instead of a client constant means a new
 * feature keyword can be registered here and immediately appear on the plan
 * editor - no admin-app rebuild.
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
    protected $connection = 'app';

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
                'tier' => $feature->tier,
            ])
            ->all();
    }
}
