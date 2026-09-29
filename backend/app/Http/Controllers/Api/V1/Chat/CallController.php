<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Domain\Chat\Contracts\CallService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CallController extends Controller
{
    public function __construct(private readonly CallService $calls) {}

    public function start(Request $request, int $conversation): JsonResponse
    {
        $kind = $request->validate([
            'kind' => ['required', 'in:audio,video'],
        ])['kind'];

        $started = $this->calls->start($request->user(), $conversation, $kind);

        return ApiResponse::success([
            'call' => $started['call'],
            'token' => $started['token'],
            'room' => $started['room'],
            'server_url' => $started['server_url'],
        ], status: 201);
    }

    public function answer(Request $request, int $call): JsonResponse
    {
        $conversationId = $request->input('conversation_id');

        if (! is_int($conversationId) && ! ctype_digit((string) $conversationId)) {
            return ApiResponse::error(
                'validation',
                'conversation_id is required.',
                422,
                'conversation_id',
            );
        }

        $answered = $this->calls->answer($request->user(), (int) $conversationId, $call);

        return ApiResponse::success([
            'call' => $answered['call'],
            'token' => $answered['token'],
            'room' => $answered['room'],
            'server_url' => $answered['server_url'],
        ]);
    }

    public function reject(Request $request, int $call): JsonResponse
    {
        $conversationId = (int) $request->input('conversation_id');

        $this->calls->reject($request->user(), $conversationId, $call);

        return ApiResponse::success(['call_id' => $call]);
    }

    public function end(Request $request, int $call): JsonResponse
    {
        $conversationId = (int) $request->input('conversation_id');

        $this->calls->end($request->user(), $conversationId, $call);

        return ApiResponse::success(['call_id' => $call]);
    }
}
