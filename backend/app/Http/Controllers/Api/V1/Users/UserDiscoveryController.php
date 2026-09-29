<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Moderation\Services\BlockService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\MatchContactsRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserDiscoveryController extends Controller
{
    public function __construct(
        private readonly BlockService $blocks,
    ) {}

    /**
     * "Top accounts" - the reach-ordered list that backs the side tab, so a new
     * user has somewhere to follow from before they know anyone. Ranked by
     * followers, then by posts, and never including the viewer or an account
     * they already follow (nothing to do there).
     */
    public function top(Request $request): JsonResponse
    {
        $viewerId = (int) $request->user()->id;
        $users = $this->blocks->hideBlockedFromQuery(
            User::query()
                ->where('status', UserStatus::Active->value)
                ->where('id', '!=', $viewerId)
                ->whereNotExists(function ($query) use ($viewerId): void {
                    $query->selectRaw('1')
                        ->from('follows')
                        ->whereColumn('follows.follower_id', $viewerId)
                        ->whereColumn('follows.following_id', 'users.id');
                }),
            $viewerId,
        )
            ->withCount(['followers', 'posts'])
            ->orderByDesc('followers_count')
            ->orderByDesc('posts_count')
            ->orderBy('id')
            ->limit(PageSize::clamp($request->integer('limit', 10), 10))
            ->get();

        $users->loadExists([
            'followers as is_followed_by_me' => fn ($query) => $query->where('follower_id', $viewerId),
        ]);

        return ApiResponse::success(data: ['users' => UserResource::collection($users)->resolve()]);
    }

    /**
     * Contact matching: given phone numbers the user chose to share, return the
     * accounts registered with them. Matching is on the *last 10 digits* so
     * `+91 98765 43210`, `919876543210` and `9876543210` all hit the same
     * account, and self is always dropped.
     */
    public function matchContacts(MatchContactsRequest $request): JsonResponse
    {
        $numbers = $request->validated('contacts');

        $digits = collect($numbers)
            ->map(fn (mixed $number): string => preg_replace('/\D+/', '', (string) $number) ?? '')
            ->map(fn (string $number): string => substr($number, -10))
            ->filter(fn (string $number): bool => strlen($number) === 10)
            ->unique()
            ->values();

        if ($digits->isEmpty()) {
            return ApiResponse::success(data: ['users' => [], 'matched' => 0, 'checked' => 0]);
        }

        // Both the bare and the +91-prefixed form are matched, wrapped in one
        // group so the active-status filter cannot be bypassed by the OR.
        $candidates = $digits
            ->flatMap(fn (string $number): array => [$number, '91'.$number])
            ->unique()
            ->values();

        $viewerId = (int) $request->user()->id;

        $users = $this->blocks->hideBlockedFromQuery(
            User::query()
                ->where('status', UserStatus::Active->value)
                ->where('id', '!=', $viewerId)
                ->whereIn('mobile', $candidates->all()),
            $viewerId,
        )
            ->withCount(['followers', 'posts'])
            ->orderByDesc('followers_count')
            ->limit(50)
            ->get();

        $users->loadExists([
            'followers as is_followed_by_me' => fn ($query) => $query->where('follower_id', $viewerId),
        ]);

        return ApiResponse::success(data: [
            'users' => UserResource::collection($users)->resolve(),
            'matched' => $users->count(),
            'checked' => $digits->count(),
        ]);
    }
}
