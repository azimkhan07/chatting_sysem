<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The provided identifier or password is incorrect.');
    }
}
