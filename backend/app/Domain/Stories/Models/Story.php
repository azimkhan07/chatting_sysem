<?php

declare(strict_types=1);

namespace App\Domain\Stories\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Songs\Models\Song;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read Song|null $song
 * @property-read string $media_path
 * @property-read string $media_url
 * @property-read string $type
 * @property-read string $mime
 * @property-read int|null $width
 * @property-read int|null $height
 * @property-read string $caption
 * @property-read string $effects
 * @property-read array $text_style
 * @property-read int|null $song_id
 * @property-read Carbon|null $expires_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class Story extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'media_path',
        'media_url',
        'type',
        'mime',
        'width',
        'height',
        'caption',
        'effects',
        'text_style',
        'song_id',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'text_style' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
