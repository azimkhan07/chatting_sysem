<?php

declare(strict_types=1);

namespace App\Domain\Songs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $artist
 * @property-read string $url
 * @property-read int $duration
 * @property-read string $genre
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class Song extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'artist',
        'url',
        'duration',
        'genre',
    ];
}
