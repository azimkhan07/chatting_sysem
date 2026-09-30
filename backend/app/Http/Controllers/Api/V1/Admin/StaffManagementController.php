<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Models\Role;
use App\Domain\Auth\Enums\AccountType;
use App\Domain\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Team management for the admin console. Only an admin can create staff
 * accounts; support agents use the change-password endpoint to rotate their
 * own credentials.
 */
final class StaffManagementController extends Controller
{
    private const CREATABLE_ROLES = ['admin', 'super_admin', 'support'];

    public function index(Request $request): JsonResponse
    {
        $staff = User::query()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::CREATABLE_ROLES))
            ->orderBy('created_at')
            ->get()
            ->map(function (User $u) {
                $deviceCount = (int) $u->tokens()
                    ->where('name', 'like', 'staff · %')
                    ->count();

                return array_merge((new UserResource($u))->resolve(), [
                    'role' => $u->roles->first()?->name,
                    'active_devices' => $deviceCount,
                ]);
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
                Rule::unique('users', 'username')->whereNull('deleted_at'),
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'role' => ['required', 'string', Rule::in(self::CREATABLE_ROLES)],
        ]);

        $role = Role::query()->where('name', $data['role'])->first();

        if ($role === null) {
            return ApiResponse::error('INVALID_ROLE', 'That role does not exist.', 422, 'role');
        }

        $user = User::create([
            'username' => mb_strtolower(trim($data['username'])),
            'display_name' => trim($data['display_name']),
            'password' => $data['password'],
            'account_type' => AccountType::Professional,
        ]);

        $user->roles()->attach($role->id);

        return ApiResponse::success(
            array_merge((new UserResource($user->fresh('roles')))->resolve(), [
                'role' => $data['role'],
                'staff_id' => $user->id,
            ]),
            status: 201,
        );
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $user = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::CREATABLE_ROLES))
            ->findOrFail($userId);

        if ((int) $request->user()->id === $user->id) {
            return ApiResponse::error('INVALID_OPERATION', 'You cannot remove your own account.', 422);
        }

        $roleName = (string) $user->roles()->first()?->name;

        if (in_array($roleName, ['admin', 'super_admin'], true)) {
            return ApiResponse::error(
                'INVALID_OPERATION',
                'Admin accounts are protected. Only support accounts can be removed.',
                422,
            );
        }

        $user->roles()->detach();
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(['removed' => true]);
    }
}