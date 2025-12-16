<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ResetPasswordNotification extends Notification
{
    public function __construct(
        public string $token
    ) {
        Log::info('ResetPasswordNotification created', ['token_length' => strlen($token)]);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);

        Log::info('Sending password reset email', [
            'email' => $notifiable->email,
            'reset_url' => $resetUrl,
        ]);

        return (new MailMessage)
            ->subject('Jelszó visszaállítása - FunctionalFit')
            ->greeting('Kedves ' . ($notifiable->name ?? 'Felhasználó') . '!')
            ->line('Azért kapod ezt az e-mailt, mert jelszó-visszaállítási kérelmet kaptunk a fiókodhoz.')
            ->action('Jelszó visszaállítása', $resetUrl)
            ->line('Ez a jelszó-visszaállítási link 60 percen belül lejár.')
            ->line('Ha nem te kérted a jelszó visszaállítását, nincs további teendőd.')
            ->salutation('Üdvözlettel, FunctionalFit csapata');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
