<?php

declare(strict_types=1);

namespace Admin\Http\Controllers\Api\V1\Admin;

use Admin\Domain\Admin\Actions\StaffLoginAction;
use Admin\Domain\Admin\Exceptions\StaffNotAllowedException;
use Admin\Domain\Admin\Models\StaffUser;
use Admin\Domain\Admin\Exceptions\InvalidCredentialsException;
use Admin\Http\Controllers\Controller;
use Admin\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Console login for the separate admin/support app.
 *
 * A staff account (admin / super_admin / support) signs in here. Accounts live
 * in the console database, the `staff` token is capped at 3 devices per user,
 * and `superseded` tells the client that an older session was replaced so it
 * can show the "signed in elsewhere" alert.
 */
final class StaffAuthController extends Controller
{
    public function __construct(
        private readonly StaffLoginAction $login,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->login->handle(
                identifier: $data['identifier'],
                password: $data['password'],
                // The whole request, not a user-agent string: the session record
                // needs the sign-in address and the client as well as the agent.
                request: $request,
            );
        } catch (InvalidCredentialsException) {
            return ApiResponse::error('INVALID_CREDENTIALS', 'Wrong username or password.', 401);
        } catch (StaffNotAllowedException $e) {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        }

        return ApiResponse::success([
            'user' => self::payload($result['user']),
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
            'superseded' => $result['superseded'],
            'active_devices' => $result['active_devices'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => self::payload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(['logged_out' => true]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::error(
                'INVALID_CURRENT_PASSWORD',
                'The current password is incorrect.',
                422,
                'current_password',
            );
        }

        $user->forceFill(['password' => $data['new_password']])->save();

        return ApiResponse::success(['message' => 'Password updated.']);
    }

    /**
     * @return array{id: int, username: string, display_name: string, role: string}
     */
    private static function payload(StaffUser $staff): array
    {
        return [
            'id' => (int) $staff->id,
            'username' => $staff->username,
            'display_name' => $staff->display_name,
            'role' => $staff->role,
        ];
    }
}
