<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\ChatService;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Services\ChatDrawingProcessor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\UploadDrawingRequest;
use App\Support\ApiResponse;
use App\Support\Media\MediaUrl;
use Illuminate\Http\JsonResponse;

final class ChatDrawingController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ChatDrawingProcessor $drawings,
    ) {}

    /**
     * Stores the canvas PNG and hands back the URL to send as a `drawing`
     * message. The feature gate is on the route; membership is checked here.
     */
    public function store(UploadDrawingRequest $request, int $conversation): JsonResponse
    {
        $user = $request->user();

        $resolved = $this->chatService->conversationFor($user, $conversation);

        if ($resolved === null) {
            throw new ConversationNotFoundException('This conversation is not available.');
        }

        $stored = $this->drawings->process((int) $user->id, $request->file('drawing'));

        return ApiResponse::success(
            data: [
                'media_url' => MediaUrl::of($stored['file_path']),
                'mime' => $stored['mime'],
            ],
            status: 201,
        );
    }
}
