<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Data\AuthUserResult;
use App\Domain\Auth\Data\LoginData;
use App\Domain\Auth\Data\RegisterData;
use App\Domain\Auth\Models\User;

interface AuthService
{
    public function register(RegisterData $data): AuthUserResult;

    /**
     * Accepts username, email or mobile number as the identifier.
     */
    public function login(LoginData $data): AuthUserResult;

    public function logout(User $user): void;
}
