<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued: sending the email must not make the response slower for an existing
 * account than for an unknown email.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $expiresInMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe famApp')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Vous recevez cet email car une réinitialisation du mot de passe de votre compte famApp a été demandée.')
            ->action('Choisir un nouveau mot de passe', $this->resetUrl($notifiable))
            ->line("Ce lien expirera dans {$expiresInMinutes} minutes.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe reste inchangé.")
            ->salutation('À bientôt sur famApp');
    }

    private function resetUrl(User $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
