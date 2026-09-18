<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Social\Contracts\NotificationRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notifications->listFor(
            $request->user()->id,
            $request->integer('limit', 30),
            $request->query('cursor'),
        );

        return ApiResponse::success(
            data: [
                'notifications' => NotificationResource::collection($paginator->items()),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
            meta: [
                'has_more' => $paginator->hasMorePages(),
                'unread' => $this->notifications->unreadCount($request->user()->id),
                'limit' => $paginator->perPage(),
            ],
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread' => $this->notifications->unreadCount($request->user()->id),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->notifications->markAllRead($request->user()->id);

        return ApiResponse::success(['updated' => $updated]);
    }
}
