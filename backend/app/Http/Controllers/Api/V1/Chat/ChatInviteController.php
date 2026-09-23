<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatInviteController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    public function show(Request $request, int $conversation): JsonResponse
    {
        $invite = $this->chatService->currentInvite($request->user(), $conversation);

        return ApiResponse::success(data: [
            'invite' => $invite === null ? null : [
                'conversation_id' => $invite->conversation_id,
                'code' => $invite->code,
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'created_by' => $invite->creator->only(['id', 'display_name', 'username']),
            ],
        ]);
    }

    public function store(Request $request, int $conversation): JsonResponse
    {
        $invite = $this->chatService->inviteFor($request->user(), $conversation);

        return ApiResponse::success(data: [
            'invite' => [
                'conversation_id' => $invite->conversation_id,
                'code' => $invite->code,
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'created_by' => $invite->creator->only(['id', 'display_name', 'username']),
            ],
        ]);
    }

    public function destroy(Request $request, int $conversation): JsonResponse
    {
        $this->chatService->revokeInvite($request->user(), $conversation);

        return ApiResponse::success(data: []);
    }

    public function join(Request $request, string $code): JsonResponse
    {
        $conversation = $this->chatService->joinViaInvite($request->user(), $code);

        return ApiResponse::success(data: [
            'conversation' => (new ConversationResource($conversation))->resolve(),
        ]);
    }
}
