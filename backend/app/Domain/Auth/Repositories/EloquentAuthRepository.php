<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Data\RegisterData;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\User;

final class EloquentAuthRepository implements AuthRepository
{
    public function create(RegisterData $data): User
    {
        return User::query()->create([
            'username' => $data->username,
            'display_name' => $data->displayName,
            'email' => $data->email,
            'mobile' => $data->mobile,
            'password' => $data->password,
            'status' => UserStatus::Active->value,
            'is_verified' => false,
        ]);
    }

    public function findByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('mobile', $identifier)
            ->first();
    }

    public function usernameExists(string $username): bool
    {
        return User::query()->where('username', $username)->exists();
    }
}
