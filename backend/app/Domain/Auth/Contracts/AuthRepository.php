<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Data\RegisterData;
use App\Domain\Auth\Models\User;

interface AuthRepository
{
    public function create(RegisterData $data): User;

    public function findByIdentifier(string $identifier): ?User;

    public function usernameExists(string $username): bool;
}
