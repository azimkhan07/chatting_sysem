<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Contracts\AuthService as AuthServiceContract;
use App\Domain\Auth\Data\AuthUserResult;
use App\Domain\Auth\Data\LoginData;
use App\Domain\Settings\Services\AccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReactivateAccountRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Bringing a self-deactivated account back.
 *
 * Lives here rather than in AccountController because the caller is signed out:
 * deactivation revokes every token, so this is the one Account Center action
 * that authenticates from scratch.
 */
final class ReactivateAccountController extends Controller
{
    public function __construct(
        private readonly AccountService $account,
        private readonly AuthServiceContract $authService,
    ) {}

    public function __invoke(ReactivateAccountRequest $request): JsonResponse
    {
        $password = (string) $request->validated('password');

        // Reactivation clears the flag and mints a session in one step, so the
        // user does not have to sign in twice.
        $user = $this->account->reactivate((string) $request->validated('identifier'), $password);

        $result = $this->authService->login(new LoginData(
            identifier: $user->username,
            password: $password,
            device: $request->userAgent(),
        ));

        return ApiResponse::success([
            'reactivated' => true,
            'access_token' => $result->accessToken,
            'token_type' => AuthUserResult::TOKEN_TYPE,
            'expires_in' => $result->expiresInSeconds,
            'user' => (new UserResource($result->user))->resolve(),
        ]);
    }
}
