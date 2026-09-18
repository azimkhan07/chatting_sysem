<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Data\AuthUserResult;
use App\Domain\Auth\Data\LoginData;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\TokenIssuer;
use Illuminate\Support\Facades\Hash;

final class LoginUserAction
{
    public function __construct(
        private readonly AuthRepository $repository,
        private readonly TokenIssuer $tokenIssuer,
    ) {}

    public function handle(LoginData $data): AuthUserResult
    {
        $user = $this->repository->findByIdentifier($data->identifier);

        $this->assertCredentialsValid($user, $data->password);

        return new AuthUserResult(
            user: $user,
            accessToken: $this->tokenIssuer->issueFor($user),
            expiresInSeconds: $this->tokenIssuer->ttlSeconds(),
        );
    }

    private function assertCredentialsValid(?User $user, string $password): void
    {
        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }
    }
}
