<?php

declare(strict_types=1);

namespace App\Domain\Saved\Models;

use App\Domain\Posts\Models\Post;
use App\Domain\Stories\Models\Story;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A post/story the user put in their "All saved" list. `saveable` is
 * polymorphic (posts and stories), and the item can be attached to any number
 * of named collections through `collections()`.
 *
 * @property-read int $id
 * @property-read int $user_id
 * @property-read Post|Story $saveable
 */
final class SavedItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'saveable_type',
        'saveable_id',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function saveable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsToMany<SavedCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            SavedCollection::class,
            'saved_collection_items',
            'saved_item_id',
            'collection_id',
        );
    }
}
