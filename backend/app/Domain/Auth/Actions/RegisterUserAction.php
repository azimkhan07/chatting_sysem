<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Contracts\AuthRepository;
use App\Domain\Auth\Data\AuthUserResult;
use App\Domain\Auth\Data\RegisterData;
use App\Domain\Auth\Exceptions\UsernameTakenException;
use App\Domain\Auth\Services\TokenIssuer;

final class RegisterUserAction
{
    public function __construct(
        private readonly AuthRepository $repository,
        private readonly TokenIssuer $tokenIssuer,
    ) {}

    public function handle(RegisterData $data): AuthUserResult
    {
        if ($this->repository->usernameExists($data->username)) {
            throw new UsernameTakenException($data->username);
        }

        $user = $this->repository->create($data);

        return new AuthUserResult(
            user: $user,
            accessToken: $this->tokenIssuer->issueFor($user),
            expiresInSeconds: $this->tokenIssuer->ttlSeconds(),
        );
    }
}
