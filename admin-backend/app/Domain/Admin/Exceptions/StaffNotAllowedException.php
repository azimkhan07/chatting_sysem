<?php

declare(strict_types=1);

namespace Admin\Domain\Admin\Exceptions;

use RuntimeException;

final class StaffNotAllowedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A staff account is required for the console.');
    }
}
