<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin user index: 4 summary cards (all / today / month / year) plus a paged,
 * filterable list. Filters: status, country, free-text search (username, name
 * or email).
 *
 * Staff accounts (admin / super_admin / support) are excluded everywhere: the
 * Users tab is for the product's users, and the support team has its own
 * screen.
 *
 * `suspicious` is a Phase 2 concept (flagging is not implemented in the app
 * yet). The filter is accepted but matches nothing until flag data exists.
 */
final class UserAdminController extends Controller
{
    /**
     * @return list<string>
     */
    private const STAFF_ROLES = ['admin', 'super_admin', 'support'];

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'all');
        $country = (string) $request->query('country', '');
        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->integer('page', 1));
        $limit = (int) $request->integer('limit', 25);

        $now = Carbon::now();

        $query = User::query()
            ->withTrashed()
            ->with('roles')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', self::STAFF_ROLES));

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
                    'country' => $u->country,
                    'role' => $u->roles->isEmpty() ? null : $u->roles->first()->name,
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
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', self::STAFF_ROLES))
            ->when($scope !== null, $scope)
            ->count();
    }
}