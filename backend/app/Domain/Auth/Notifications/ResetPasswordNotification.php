<?php

declare(strict_types=1);

namespace App\Domain\Auth\Notifications;

use App\Domain\Auth\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = URL::query(
            config('app.frontend_url').'/reset-password',
            [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ],
        );

        return (new MailMessage)
            ->subject('Reset your amteCHAT password')
            ->greeting('Hi '.($notifiable instanceof User ? $notifiable->display_name : ''))
            ->line('You requested a password reset for your amteCHAT account.')
            ->action('Reset password', $resetUrl)
            ->line('This link expires in 60 minutes. If you did not request a reset, you can ignore this email.')
            ->salutation('— the amteCHAT team');
    }
}
