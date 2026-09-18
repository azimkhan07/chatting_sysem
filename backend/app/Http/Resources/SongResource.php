<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Songs\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Song */
final class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'artist' => $this->artist,
            'url' => $this->url,
            'duration' => $this->duration,
        ];
    }
}
