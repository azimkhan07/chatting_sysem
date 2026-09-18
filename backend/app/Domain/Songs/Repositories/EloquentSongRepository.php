<?php

declare(strict_types=1);

namespace App\Domain\Songs\Repositories;

use App\Domain\Songs\Contracts\SongRepository;
use App\Domain\Songs\Models\Song;
use Illuminate\Support\Collection;

final class EloquentSongRepository implements SongRepository
{
    public function all(): Collection
    {
        return Song::query()
            ->orderBy('id')
            ->get();
    }
}
