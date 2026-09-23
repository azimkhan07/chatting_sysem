<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $post_id
 * @property-read int $user_id
 * @property-read Post $post
 * @property-read User $user
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class PostShare extends Model
{
    protected $table = 'post_shares';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
