<?php

declare(strict_types=1);

namespace App\Domain\Auth\Data;

final readonly class LoginData
{
    public function __construct(
        public string $identifier,
        public string $password,
    ) {}
}
