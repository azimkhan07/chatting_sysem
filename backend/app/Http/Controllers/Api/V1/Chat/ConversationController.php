<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Domain\Chat\Enums\ConversationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\CreateConversationRequest;
use App\Http\Requests\Api\V1\Chat\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConversationController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'conversations' => ConversationResource::collection($this->chatService->conversationsFor($request->user()))->resolve(),
        ]);
    }

    public function store(CreateConversationRequest $request): JsonResponse
    {
        $isDm = $request->validated('type') === ConversationType::Dm->value;

        $conversation = $isDm
            ? $this->chatService->startDm($request->user(), (int) $request->validated('user_id'))
            : $this->chatService->createGroup(
                $request->user(),
                (string) $request->validated('name'),
                (array) $request->validated('member_ids', []),
            );

        $conversation->setAttribute('unread_count', 0);

        return ApiResponse::success(
            data: ['conversation' => (new ConversationResource($conversation))->resolve()],
            status: 201,
        );
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->chatService->conversationFor($request->user(), $conversation);

        return $conversation !== null
            ? ApiResponse::success(data: ['conversation' => (new ConversationResource($conversation))->resolve()])
            : ApiResponse::error('NOT_FOUND', 'This conversation is not available.', 404);
    }

    public function update(UpdateConversationRequest $request, int $conversation): JsonResponse
    {
        $conversation = $this->chatService->setMuted(
            $request->user(),
            $conversation,
            (bool) $request->validated('muted'),
        );

        return ApiResponse::success(data: ['conversation' => (new ConversationResource($conversation))->resolve()]);
    }
}
