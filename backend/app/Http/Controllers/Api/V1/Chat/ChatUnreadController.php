<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatUnreadController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    public function total(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread' => $this->chatService->unreadTotal($request->user()),
        ]);
    }
}
