<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

final class UsernameTakenException extends RuntimeException
{
    public function __construct(string $username)
    {
        parent::__construct("The username \"{$username}\" is already taken.");
    }
}
