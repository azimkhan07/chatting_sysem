<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Auth\Actions\LoginUserAction;
use App\Domain\Auth\Actions\LogoutUserAction;
use App\Domain\Auth\Actions\RegisterUserAction;
use App\Domain\Auth\Contracts\AuthService as AuthServiceContract;
use App\Domain\Auth\Data\AuthUserResult;
use App\Domain\Auth\Data\LoginData;
use App\Domain\Auth\Data\RegisterData;
use App\Domain\Auth\Models\User;

final class AuthService implements AuthServiceContract
{
    public function __construct(
        private readonly RegisterUserAction $registerUserAction,
        private readonly LoginUserAction $loginUserAction,
        private readonly LogoutUserAction $logoutUserAction,
    ) {}

    public function register(RegisterData $data): AuthUserResult
    {
        return $this->registerUserAction->handle($data);
    }

    public function login(LoginData $data): AuthUserResult
    {
        return $this->loginUserAction->handle($data);
    }

    public function logout(User $user): void
    {
        $this->logoutUserAction->handle($user);
    }
}
