<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\StaffLoginAction;
use App\Domain\Admin\Exceptions\StaffNotAllowedException;
use App\Domain\Auth\Exceptions\AccountDeactivatedException;
use App\Domain\Auth\Exceptions\AccountDisabledException;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Console login for the separate admin/support app.
 *
 * A staff account (admin / super_admin / support) signs in here. The `staff`
 * token is capped at 3 devices per user; `superseded` tells the client that an
 * older session was replaced so it can show the "signed in elsewhere" alert.
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
                device: (string) $request->userAgent(),
            );
        } catch (InvalidCredentialsException) {
            return ApiResponse::error('INVALID_CREDENTIALS', 'Wrong username/email or password.', 401);
        } catch (AccountDisabledException $e) {
            return ApiResponse::error('ACCOUNT_DISABLED', $e->getMessage(), 403);
        } catch (AccountDeactivatedException $e) {
            return ApiResponse::error('ACCOUNT_DEACTIVATED', $e->getMessage(), 403);
        } catch (StaffNotAllowedException $e) {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        }

        return ApiResponse::success([
            'user' => (new UserResource($result['user']))->resolve() + [
                'role' => $result['user']->roles->first()?->name,
            ],
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
            'superseded' => $result['superseded'],
            'active_devices' => $result['active_devices'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve() + [
                'role' => $user->roles->first()?->name,
            ],
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

        if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password)) {
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
}