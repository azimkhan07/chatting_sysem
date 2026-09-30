<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Models\StaffUser;
use App\Domain\Admin\Models\SupportStaff;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Team management for the admin console. Only an admin can create staff
 * accounts; support agents use the change-password endpoint to rotate their
 * own credentials.
 *
 * Accounts are created in the console database. Support accounts are written
 * to both `staff_users` and `support_staff` so the Support screen can list the
 * desk independently of the roles defined in the app database.
 */
final class StaffManagementController extends Controller
{
    private const CREATABLE_ROLES = [
        StaffUser::ROLE_ADMIN,
        StaffUser::ROLE_SUPER_ADMIN,
        StaffUser::ROLE_SUPPORT,
    ];

    private const STAFF_ROLES = [...self::CREATABLE_ROLES];

    public function index(Request $request): JsonResponse
    {
        $staff = StaffUser::query()
            ->whereIn('role', self::STAFF_ROLES)
            ->orderBy('created_at')
            ->get()
            ->map(function (StaffUser $u): array {
                $deviceCount = (int) $u->tokens()
                    ->where('name', 'like', 'staff · %')
                    ->count();

                return [
                    'id' => (int) $u->id,
                    'username' => $u->username,
                    'display_name' => $u->display_name,
                    'role' => $u->role,
                    'active_devices' => $deviceCount,
                    'created_at' => $u->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return ApiResponse::success(['staff' => $staff]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^(?!\d+$)[a-zA-Z0-9._]+$/',
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'role' => ['required', 'string', Rule::in(self::CREATABLE_ROLES)],
        ]);

        $username = mb_strtolower(trim($data['username']));

        if (StaffUser::query()->where('username', $username)->exists()) {
            return ApiResponse::error('USERNAME_TAKEN', 'That username is already taken.', 409, 'username');
        }

        $staff = StaffUser::query()->create([
            'username' => $username,
            'display_name' => trim($data['display_name']),
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        // A support account also gets a row in the support desk table, so the
        // Support tab lists the desk without depending on the app roles pivot.
        if ($data['role'] === StaffUser::ROLE_SUPPORT) {
            SupportStaff::query()->create(['staff_user_id' => $staff->id]);
        }

        return ApiResponse::success([
            'id' => (int) $staff->id,
            'username' => $staff->username,
            'display_name' => $staff->display_name,
            'role' => $staff->role,
            'active_devices' => 0,
            'staff_id' => (int) $staff->id,
            'created_at' => $staff->created_at?->toIso8601String(),
        ], status: 201);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $staff = StaffUser::query()
            ->whereIn('role', self::STAFF_ROLES)
            ->find($userId);

        if ($staff === null) {
            return ApiResponse::error('NOT_FOUND', 'Staff account not found.', 404);
        }

        if ((int) $request->user()->id === (int) $staff->id) {
            return ApiResponse::error('INVALID_OPERATION', 'You cannot remove your own account.', 422);
        }

        if (in_array($staff->role, [StaffUser::ROLE_ADMIN, StaffUser::ROLE_SUPER_ADMIN], true)) {
            return ApiResponse::error(
                'INVALID_OPERATION',
                'Admin accounts are protected. Only support accounts can be removed.',
                422,
            );
        }

        $staff->tokens()->delete();
        SupportStaff::query()->where('staff_user_id', $staff->id)->delete();
        $staff->delete();

        return ApiResponse::success(['removed' => true]);
    }
}