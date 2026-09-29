<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Saved\Models\SavedCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SavedCollection */
final class SavedCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'item_count' => (int) ($this->items_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
