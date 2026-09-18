<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Contracts\AuthService as AuthServiceContract;
use App\Domain\Auth\Data\AuthUserResult;
use App\Domain\Auth\Data\LoginData;
use App\Domain\Auth\Data\RegisterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthServiceContract $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(new RegisterData(
            username: $request->validated('username'),
            displayName: $request->validated('display_name'),
            password: $request->validated('password'),
            email: $request->validated('email'),
            mobile: $request->validated('mobile'),
        ));

        return ApiResponse::success($this->authPayload($result), status: 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(new LoginData(
            identifier: $request->validated('identifier'),
            password: $request->validated('password'),
        ));

        return ApiResponse::success($this->authPayload($result));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => (new UserResource($request->user()))->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(['logged_out' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(AuthUserResult $result): array
    {
        return [
            'access_token' => $result->accessToken,
            'token_type' => AuthUserResult::TOKEN_TYPE,
            'expires_in' => $result->expiresInSeconds,
            'user' => (new UserResource($result->user))->resolve(),
        ];
    }
}
