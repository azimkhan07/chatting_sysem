<?php

declare(strict_types=1);

namespace App\Domain\Auth\Data;

final readonly class RegisterData
{
    public function __construct(
        public string $username,
        public string $displayName,
        public string $password,
        public ?string $email = null,
        public ?string $mobile = null,
    ) {}
}
