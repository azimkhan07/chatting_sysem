<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PostLike extends Model
{
    /**
     * The model's default table name is post_likes; override because the
     * migration uses the shorter "likes" table for DML ergonomics.
     */
    protected $table = 'likes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
