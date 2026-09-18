<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\PasswordResetService;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBroker;

final class LaravelPasswordResetService implements PasswordResetService
{
    public function __construct(private readonly PasswordBroker $broker) {}

    public function sendResetLink(string $email): void
    {
        $this->broker->sendResetLink(
            ['email' => $email],
            static function (User $user, string $token): void {
                $user->notify(new ResetPasswordNotification($token));
            },
        );
    }

    public function reset(string $email, string $token, string $password, string $passwordConfirmation): bool
    {
        $status = $this->broker->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            static function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        return $status === PasswordBroker::PASSWORD_RESET;
    }
}
