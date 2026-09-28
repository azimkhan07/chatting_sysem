<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Hashtags\Models\Hashtag;
use App\Domain\Songs\Models\Song;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $body
 * @property-read string|null $location
 * @property-read int|null $song_id
 * @property-read int $likes_count
 * @property-read int $comments_count
 * @property-read int $shares_count
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Carbon|null $deleted_at
 */
final class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'body',
        'location',
        'song_id',
    ];

    /**
     * Bound explicitly: model lives in the Domain namespace.
     */
    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PostMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('sort_order');
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class)->withTimestamps();
    }

    /**
     * Accounts this post says it is about.
     *
     * Distinct from `hashtags`: a tag is a word with no owner, a mention is a
     * person who may want to know they were tagged.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_mentions')->withTimestamps();
    }

    /** The soundtrack, when the author picked one. */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class, 'post_id');
    }
}
