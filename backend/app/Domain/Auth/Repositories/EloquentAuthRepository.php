<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Data\RegisterData;
use App\Domain\Auth\Models\User;

final class EloquentAuthRepository implements AuthRepository
{
    public function create(RegisterData $data): User
    {
        // `status` and `is_verified` are intentionally absent: they are not
        // fillable, so a new account always starts active and unverified.
        return User::query()->create([
            'username' => $data->username,
            'display_name' => $data->displayName,
            'email' => $data->email,
            'mobile' => $data->mobile,
            'password' => $data->password,
        ]);
    }

    public function findByIdentifier(string $identifier): ?User
    {
        // Usernames are unique, emails and mobiles deliberately are not, so the
        // identifier types are probed in order of specificity and the oldest
        // matching account wins. An unordered OR would make login-by-email
        // resolve to a random row.
        if (($byUsername = User::query()->where('username', $identifier)->first()) !== null) {
            return $byUsername;
        }

        foreach (['email', 'mobile'] as $column) {
            $match = User::query()
                ->where($column, $identifier)
                ->orderBy('id')
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    public function usernameExists(string $username): bool
    {
        return User::query()->where('username', $username)->exists();
    }
}
