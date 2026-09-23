<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Domain\Chat\Data\SendMessageData;
use App\Domain\Chat\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\MarkReadRequest;
use App\Http\Requests\Api\V1\Chat\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatMessageController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    public function index(Request $request, int $conversation): JsonResponse
    {
        $paginator = $this->chatService->messagesFor(
            $request->user(),
            $conversation,
            $request->integer('limit', 30),
            $request->query('cursor'),
        );

        return ApiResponse::success(
            data: [
                'messages' => MessageResource::collection($paginator->items()),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
            meta: [
                'has_more' => $paginator->hasMorePages(),
                'limit' => $paginator->perPage(),
            ],
        );
    }

    public function store(SendMessageRequest $request, int $conversation): JsonResponse
    {
        $message = $this->chatService->sendMessage(
            $request->user(),
            $conversation,
            new SendMessageData(
                type: MessageType::from($request->validated('type', 'text')),
                body: $request->validated('body'),
                clientId: $request->validated('client_id'),
            ),
        );

        return ApiResponse::success(
            data: ['message' => (new MessageResource($message))->resolve()],
            status: $message->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function read(MarkReadRequest $request, int $conversation): JsonResponse
    {
        return ApiResponse::success(
            $this->chatService->markRead(
                $request->user(),
                $conversation,
                (int) $request->validated('up_to_message_id'),
            ),
        );
    }

    public function typing(Request $request, int $conversation): JsonResponse
    {
        $this->chatService->typing($request->user(), $conversation);

        return ApiResponse::success(data: []);
    }

    public function destroy(Request $request, int $conversation, int $message): JsonResponse
    {
        $this->chatService->deleteMessage($request->user(), $conversation, $message);

        return ApiResponse::success(data: []);
    }
}
