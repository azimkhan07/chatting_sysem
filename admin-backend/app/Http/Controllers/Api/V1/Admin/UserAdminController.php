<?php

declare(strict_types=1);

namespace Admin\Http\Controllers\Api\V1\Admin;

use Admin\Domain\App\Enums\UserStatus;
use Admin\Domain\App\Models\User;
use Admin\Http\Controllers\Controller;
use Admin\Http\Resources\UserResource;
use Admin\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin user index: 4 summary cards (all / today / month / year) plus a paged,
 * filterable list. Filters: status, country, free-text search (username, name
 * or email).
 *
 * Console staff are not in this table and cannot be. They live in this app's own
 * database, and an app user row has no way to authenticate here — so there is
 * no staff filter to apply. The `roles`/`role_user` tables in the app database
 * are a leftover from when staff were app users; filtering on them would hide a
 * real account from moderators for holding a label that grants it nothing.
 *
 * `suspicious` is a Phase 2 concept (flagging is not implemented in the app
 * yet). The filter is accepted but matches nothing until flag data exists.
 */
final class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'all');
        $country = (string) $request->query('country', '');
        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->integer('page', 1));
        $limit = (int) $request->integer('limit', 25);

        $now = Carbon::now();

        $query = User::query()->withTrashed();

        if ($status === 'suspended') {
            $query->where('status', UserStatus::Suspended);
        } elseif ($status === 'deactivated') {
            $query->whereNotNull('deactivated_at');
        } elseif ($status === 'banned') {
            $query->where('status', UserStatus::Banned);
        } elseif ($status === 'suspicious') {
            // Flagging is Phase 2 in the app; nothing is flagged yet.
            $query->whereRaw('1 = 0');
        }

        if ($country !== '') {
            $query->where('country', strtoupper($country));
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('username', 'like', "%{$search}%")
                ->orWhere('display_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $paginator = $query->orderByDesc('created_at')->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::success([
            'summary' => [
                'all' => $this->userCount(),
                'today' => $this->userCount(fn ($q) => $q->whereDate('created_at', $now->toDateString())),
                'month' => $this->userCount(fn ($q) => $q->where('created_at', '>=', $now->copy()->startOfMonth())),
                'year' => $this->userCount(fn ($q) => $q->where('created_at', '>=', $now->copy()->startOfYear())),
            ],
            'users' => collect($paginator->items())
                ->map(fn (User $u) => array_merge((new UserResource($u))->resolve(), [
                    'status' => $u->deleted_at !== null
                        ? 'deleted'
                        : ($u->deactivated_at !== null ? 'deactivated' : $u->status->value),
                ]))
                ->values(),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    private function userCount(?callable $scope = null): int
    {
        return (int) User::query()
            ->withTrashed()
            ->when($scope !== null, $scope)
            ->count();
    }
}
