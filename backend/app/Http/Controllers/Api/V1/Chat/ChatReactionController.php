<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Domain\Chat\Enums\MessageReactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\MessageReactionRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatReactionController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    public function store(MessageReactionRequest $request, int $conversation, int $message): JsonResponse
    {
        $reaction = MessageReactionType::from($request->validated('reaction'));

        $result = $this->chatService->toggleMessageReaction(
            $request->user(),
            $conversation,
            $message,
            $reaction,
        );

        return ApiResponse::success(data: $result);
    }

    public function destroy(Request $request, int $conversation, int $message): JsonResponse
    {
        $result = $this->chatService->removeMessageReaction($request->user(), $conversation, $message);

        return ApiResponse::success(data: $result);
    }
}
