<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Contracts\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ForgotPasswordController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService) {}

    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return ApiResponse::success([
            'message' => 'If that email exists, a reset link is on its way.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $reset = $this->passwordResetService->reset(
            email: $request->validated('email'),
            token: $request->validated('token'),
            password: $request->validated('password'),
            passwordConfirmation: $request->input('password_confirmation'),
        );

        if (! $reset) {
            return ApiResponse::error(
                'INVALID_RESET_TOKEN',
                'This reset link is invalid or has expired.',
                422,
                'token',
            );
        }

        return ApiResponse::success(['message' => 'Your password has been updated.']);
    }
}
