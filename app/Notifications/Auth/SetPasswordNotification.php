<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Welcomes a member whose account an administrator has just created, with a
 * signed link to choose their first password, valid for several days (unlike
 * the forgotten password link). Sent once the account creation is committed.
 */
class SetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
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
        $expiresInDays = (int) config('famapp.invitation_expire_days');

        return (new MailMessage)
            ->subject('Bienvenue sur famApp, définissez votre mot de passe')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Un compte famApp vient d\'être créé pour vous : vous pourrez y retrouver les livres et les outils de la famille.')
            ->line('Pour vous connecter, commencez par choisir votre mot de passe.')
            ->action('Définir mon mot de passe', $this->invitationUrl($notifiable, $expiresInDays))
            ->line("Ce lien est valable {$expiresInDays} jours. Passé ce délai, utilisez « Mot de passe oublié » sur la page de connexion pour recevoir un nouveau lien.")
            ->salutation('À bientôt sur famApp');
    }

    private function invitationUrl(User $notifiable, int $expiresInDays): string
    {
        return URL::temporarySignedRoute('invitation.show', now()->addDays($expiresInDays), ['user' => $notifiable]);
    }
}
