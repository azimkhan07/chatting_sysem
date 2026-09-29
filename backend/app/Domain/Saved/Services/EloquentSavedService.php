<?php

declare(strict_types=1);

namespace App\Domain\Saved\Services;

use App\Domain\Posts\Models\Post;
use App\Domain\Saved\Contracts\SavedService;
use App\Domain\Saved\Models\SavedCollection;
use App\Domain\Saved\Models\SavedItem;
use App\Domain\Stories\Models\Story;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class EloquentSavedService implements SavedService
{
    /**
     * Eager-load the polymorphic target for a batch of saved items. For posts
     * the media preview and author are what the saved grid renders; for
     * stories the media url.
     */
    private function loadSaveables(Collection $items): Collection
    {
        $items->load(['saveable.user']);

        $postItems = $items->where('saveable_type', Post::class);
        $postItems->load('saveable.media');

        return $items;
    }

    public function overview(int $userId): array
    {
        $collections = SavedCollection::query()
            ->where('user_id', $userId)
            ->withCount('items')
            ->orderByDesc('id')
            ->get();

        $items = SavedItem::query()
            ->where('user_id', $userId)
            ->with('collections')
            ->latest('id')
            ->get();

        $this->loadSaveables($items);

        /** @var array<int, int> $collectionCounts */
        $collectionCounts = $collections
            ->mapWithKeys(fn (SavedCollection $collection): array => [(int) $collection->id => (int) $collection->items_count])
            ->all();

        return [
            'collections' => $collections,
            'items' => $items,
            'collection_counts' => $collectionCounts,
        ];
    }

    public function save(int $userId, string $type, int $id, ?int $collectionId = null): array
    {
        $saveable = $this->resolveSaveable($type, $id);

        $item = SavedItem::query()->firstOrCreate([
            'user_id' => $userId,
            'saveable_type' => $saveable->getMorphClass(),
            'saveable_id' => $saveable->getKey(),
        ]);

        $created = $item->wasRecentlyCreated;
        $collection = null;

        if ($collectionId !== null) {
            $collection = SavedCollection::query()
                ->where('user_id', $userId)
                ->findOrFail($collectionId);

            $collection->items()->syncWithoutDetaching($item->id);
            $item->refresh();
        }

        // The response renders the saveable target, so load needed relations
        // before the resource is built rather than hitting lazy loading.
        $with = ['saveable.user'];
        if ($saveable instanceof Post) {
            $with[] = 'saveable.media';
        }
        $with[] = 'collections';

        $item->load($with);

        return ['item' => $item, 'collection' => $collection, 'created' => $created];
    }

    public function remove(int $userId, string $type, int $id): bool
    {
        $item = SavedItem::query()
            ->where('user_id', $userId)
            ->where('saveable_type', $this->morphFor($type))
            ->where('saveable_id', $id)
            ->first();

        if ($item === null) {
            return false;
        }

        $item->delete();

        return true;
    }

    public function createCollection(int $userId, string $name): SavedCollection
    {
        return SavedCollection::query()->create([
            'user_id' => $userId,
            'name' => trim($name),
        ]);
    }

    public function deleteCollection(SavedCollection $collection): bool
    {
        return (bool) $collection->delete();
    }

    public function itemsForCollection(int $userId, int $collectionId): Collection
    {
        $items = SavedItem::query()
            ->where('user_id', $userId)
            ->whereHas('collections', fn ($query) => $query->whereKey($collectionId))
            ->with('collections')
            ->latest('id')
            ->get();

        return $this->loadSaveables($items);
    }

    private function resolveSaveable(string $type, int $id): Model
    {
        return match ($type) {
            'post' => Post::query()->findOrFail($id),
            'story' => Story::query()->findOrFail($id),
            default => throw new \InvalidArgumentException("Unknown saveable type [$type]."),
        };
    }

    private function morphFor(string $type): string
    {
        return match ($type) {
            'post' => (new Post)->getMorphClass(),
            'story' => (new Story)->getMorphClass(),
            default => throw new \InvalidArgumentException("Unknown saveable type [$type]."),
        };
    }
}
