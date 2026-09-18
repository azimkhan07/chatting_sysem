<?php

declare(strict_types=1);

namespace App\Domain\Songs\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];
}
