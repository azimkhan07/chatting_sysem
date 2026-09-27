<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\ConversationMessage;
use App\Domain\Chat\Services\MessagePinService;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pinned messages for one conversation. The whole surface is premium; a locked
 * request returns the standard FEATURE_LOCKED error with the feature key.
 */
final class ChatPinController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly MessagePinService $pins,
    ) {}

    public function index(Request $request, int $conversation): JsonResponse
    {
        $conversationModel = $this->chat->conversationFor($request->user(), $conversation);

        if ($conversationModel === null) {
            return ApiResponse::error('NOT_FOUND', 'Conversation not found.', 404);
        }

        $messages = $this->pins->forConversation($conversationModel);

        return ApiResponse::success(data: [
            'pinned' => MessageResource::collection($messages)->resolve(),
        ]);
    }

    public function store(Request $request, int $conversation, int $message): JsonResponse
    {
        $conversationModel = $this->chat->conversationFor($request->user(), $conversation);

        if ($conversationModel === null) {
            return ApiResponse::error('NOT_FOUND', 'Conversation not found.', 404);
        }

        $target = $this->messageOrFail($conversationModel, $message);

        $this->pins->pin($request->user(), $conversationModel, $target);

        return ApiResponse::success(data: [
            'message' => (new MessageResource($target->refresh()))->resolve(),
        ]);
    }

    public function destroy(Request $request, int $conversation, int $message): JsonResponse
    {
        $conversationModel = $this->chat->conversationFor($request->user(), $conversation);

        if ($conversationModel === null) {
            return ApiResponse::error('NOT_FOUND', 'Conversation not found.', 404);
        }

        $target = $this->messageOrFail($conversationModel, $message);

        $this->pins->unpin($request->user(), $conversationModel, $target);

        return ApiResponse::success(data: [
            'message' => (new MessageResource($target->refresh()))->resolve(),
        ]);
    }

    private function messageOrFail(Conversation $conversation, int $messageId): ConversationMessage
    {
        /** @var ConversationMessage|null $message */
        $message = $conversation->messages()->whereKey($messageId)->first();

        if ($message === null) {
            throw new ConversationNotFoundException('Message not found.');
        }

        return $message;
    }
}
