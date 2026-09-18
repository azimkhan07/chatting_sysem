<?php

declare(strict_types=1);

namespace App\Domain\Stories\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Story extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'media_path',
        'type',
        'mime',
        'width',
        'height',
        'caption',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
