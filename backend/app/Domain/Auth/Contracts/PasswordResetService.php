<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

interface PasswordResetService
{
    /**
     * Sends a password reset link to the given email address.
     * Always behaves identically whether or not the account exists,
     * so the endpoint cannot be used to enumerate accounts.
     */
    public function sendResetLink(string $email): void;

    /**
     * Validates the reset token and replaces the password if valid.
     *
     * @return bool whether the reset succeeded
     */
    public function reset(string $email, string $token, string $password, string $passwordConfirmation): bool;
}
