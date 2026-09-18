<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\AddMemberRequest;
use App\Http\Resources\ConversationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatMemberController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    public function store(AddMemberRequest $request, int $conversation): JsonResponse
    {
        $conversation = $this->chatService->addMember(
            $request->user(),
            $conversation,
            (int) $request->validated('user_id'),
        );

        return ApiResponse::success(data: ['conversation' => (new ConversationResource($conversation))->resolve()]);
    }

    public function destroy(Request $request, int $conversation, int $member): JsonResponse
    {
        $this->chatService->removeMember($request->user(), $conversation, $member);

        return ApiResponse::success(data: []);
    }
}
