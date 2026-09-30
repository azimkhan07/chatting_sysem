<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;
use App\Domain\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dashboard counters.
 *
 * The range filter is normalized server-side:
 *   - today / month / year  → the matching UTC window
 *   - custom                → `from` / `to` (YYYY-MM-DD), clamped to <= today
 * A client can never run a query into the future; lookups beyond today simply
 * return zero.
 */
final class DashboardAdminController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $now = Carbon::today();
        $range = (string) $request->query('range', 'month');

        $from = $now->copy()->startOfDay();
        $to = $now->copy()->endOfDay();

        match ($range) {
            'today' => null,
            'year' => $from = $now->copy()->startOfYear(),
            'custom' => [
                $from = $this->clampFrom($request->query('from'), $now),
                $to = $this->clampTo($request->query('to'), $now, $from),
            ],
            default => $from = $now->copy()->startOfMonth(),
        };

        return ApiResponse::success([
            'users_in_range' => (int) User::withoutTrashed()
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->count(),
            'users' => (int) User::withoutTrashed()->count(),
            'active_subscriptions' => (int) Subscription::query()
                ->where('status', 'active')
                ->count(),
            'active_subscriptions_in_range' => (int) Subscription::query()
                ->where('status', 'active')
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->count(),
            'suspended_users' => (int) User::withoutTrashed()
                ->where('status', UserStatus::Suspended)
                ->count(),
            'verified_users' => (int) User::withoutTrashed()
                ->where('is_verified', true)
                ->count(),
            'revenue_paisa' => (int) Subscription::query()
                ->whereIn('status', ['active', 'expired'])
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->sum('amount_paisa'),
        ]);
    }

    private function clampFrom(mixed $value, Carbon $now): Carbon
    {
        $date = $this->toCarbon($value, $now->copy()->startOfMonth());

        return $date->gt($now) ? $now->copy()->startOfDay() : $date;
    }

    private function clampTo(mixed $value, Carbon $now, Carbon $from): Carbon
    {
        $date = $this->toCarbon($value, $now)->endOfDay();

        if ($date->gt($now->endOfDay())) {
            $date = $now->copy()->endOfDay();
        }

        return $date->lt($from) ? $from->copy()->endOfDay() : $date;
    }

    private function toCarbon(mixed $value, Carbon $fallback): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return Carbon::parse($value)->startOfDay();
        }

        return $fallback;
    }
}