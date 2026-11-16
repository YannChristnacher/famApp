<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcomes a member whose account an administrator has just created, with a
 * password reset link to choose their first password. Sent once the account
 * creation is committed.
 */
class SetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
        $this->afterCommit();
    }

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
            ->subject('Bienvenue sur famApp, définissez votre mot de passe')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Un compte famApp vient d\'être créé pour vous : vous pourrez y retrouver les livres et les outils de la famille.')
            ->line('Pour vous connecter, commencez par choisir votre mot de passe.')
            ->action('Définir mon mot de passe', $this->setPasswordUrl($notifiable))
            ->line("Ce lien expirera dans {$expiresInMinutes} minutes. Passé ce délai, utilisez « Mot de passe oublié » sur la page de connexion pour en recevoir un nouveau.")
            ->salutation('À bientôt sur famApp');
    }

    private function setPasswordUrl(User $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
