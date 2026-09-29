<?php

declare(strict_types=1);

namespace App\Domain\Saved\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named folder that holds a subset of the user's saved items, e.g. "Lunes"
 * or "Ideas for later".
 *
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $name
 */
final class SavedCollection extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * @return BelongsToMany<SavedItem, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            SavedItem::class,
            'saved_collection_items',
            'collection_id',
            'saved_item_id',
        );
    }
}
