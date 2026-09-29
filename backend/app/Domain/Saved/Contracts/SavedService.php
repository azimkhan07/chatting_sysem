<?php

declare(strict_types=1);

namespace App\Domain\Saved\Contracts;

use App\Domain\Saved\Models\SavedCollection;
use App\Domain\Saved\Models\SavedItem;
use Illuminate\Database\Eloquent\Collection;

interface SavedService
{
    /**
     * @return array{collections: Collection<int, SavedCollection>, items: Collection<int, SavedItem>, collection_counts: array<int, int>}
     */
    public function overview(int $userId): array;

    /**
     * @return array{item: SavedItem, collection: SavedCollection|null, created: bool}
     */
    public function save(int $userId, string $type, int $id, ?int $collectionId = null): array;

    public function remove(int $userId, string $type, int $id): bool;

    public function createCollection(int $userId, string $name): SavedCollection;

    public function deleteCollection(SavedCollection $collection): bool;

    /**
     * @return Collection<int, SavedItem>
     */
    public function itemsForCollection(int $userId, int $collectionId): Collection;
}
