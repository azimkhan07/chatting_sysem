<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Domain\Saved\Contracts\SavedService;
use App\Domain\Saved\Models\SavedCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\SavedCollectionResource;
use App\Http\Resources\SavedItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SavedController extends Controller
{
    public function __construct(
        private readonly SavedService $saved,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $overview = $this->saved->overview($userId);

        return ApiResponse::success([
            'collections' => SavedCollectionResource::collection($overview['collections']),
            'items' => SavedItemResource::collection($overview['items']),
            'collection_counts' => $overview['collection_counts'],
        ]);
    }

    public function showCollection(Request $request, int $collection): JsonResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        $collectionModel = SavedCollection::query()
            ->where('user_id', $userId)
            ->findOrFail($collection);

        $items = $this->saved->itemsForCollection($userId, (int) $collectionModel->id);

        return ApiResponse::success([
            'collection' => new SavedCollectionResource($collectionModel),
            'items' => SavedItemResource::collection($items),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        $data = $request->validate([
            'saveable_type' => ['required', Rule::in(['post', 'story'])],
            'saveable_id' => ['required', 'integer'],
            'collection_id' => ['nullable', 'integer'],
        ]);

        $result = $this->saved->save(
            $userId,
            (string) $data['saveable_type'],
            (int) $data['saveable_id'],
            isset($data['collection_id']) ? (int) $data['collection_id'] : null,
        );

        return ApiResponse::success([
            'saved' => new SavedItemResource($result['item']),
            'created' => $result['created'],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        $data = $request->validate([
            'saveable_type' => ['required', Rule::in(['post', 'story'])],
            'saveable_id' => ['required', 'integer'],
        ]);

        $removed = $this->saved->remove($userId, (string) $data['saveable_type'], (int) $data['saveable_id']);

        return ApiResponse::success(['removed' => $removed]);
    }

    public function storeCollection(Request $request): JsonResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $collection = $this->saved->createCollection($userId, (string) $data['name']);

        return ApiResponse::success([
            'collection' => new SavedCollectionResource($collection),
        ], status: 201);
    }

    public function destroyCollection(Request $request, int $collection): JsonResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        $collectionModel = SavedCollection::query()
            ->where('user_id', $userId)
            ->findOrFail($collection);

        $this->saved->deleteCollection($collectionModel);

        return ApiResponse::success(['deleted' => true, 'id' => $collectionModel->id]);
    }
}
