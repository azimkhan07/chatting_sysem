<?php

declare(strict_types=1);

namespace App\Domain\Songs\Contracts;

use App\Domain\Songs\Models\Song;
use Illuminate\Support\Collection;

interface SongRepository
{
    /**
     * All songs in the library.
     *
     * @return Collection<int, Song>
     */
    public function all(): Collection;
}
