<?php

declare(strict_types=1);

namespace App\Domain\Auth\Data;

final readonly class LoginData
{
    public function __construct(
        public string $identifier,
        public string $password,
        /**
         * The caller's user agent, kept out of the request so the domain never
         * has to reach for the HTTP layer. It only ever becomes part of the
         * session label shown in Account Center > Security.
         */
        public ?string $device = null,
    ) {}
}
