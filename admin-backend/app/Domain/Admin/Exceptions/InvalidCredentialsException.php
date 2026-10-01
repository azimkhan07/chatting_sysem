<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Exceptions;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Those credentials do not match our records.');
    }
}
