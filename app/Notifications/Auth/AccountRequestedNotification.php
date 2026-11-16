<?php

namespace App\Notifications\Auth;

use App\Models\Auth\AccountRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Tells an administrator that a visitor asked for an account. Queued: sending
 * the email must not make the answer slower than for an ignored request.
 */
class AccountRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AccountRequest $accountRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $requester = $this->accountRequest;

        return (new MailMessage)
            ->subject('Nouvelle demande de compte famApp')
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->literal($requester->name, suffix: ' demande un compte famApp.'))
            ->line($this->literal($requester->name, prefix: 'Nom : '))
            ->line($this->literal($requester->email, prefix: 'Adresse email : '))
            ->line($this->messageLine($requester->message))
            ->action('Voir les demandes de compte', route('admin.account-requests.index'))
            ->line('Depuis cette page, vous pouvez créer son compte ou refuser la demande.')
            ->salutation('À bientôt sur famApp');
    }

    private function messageLine(?string $message): Htmlable|string
    {
        if ($message === null || trim($message) === '') {
            return 'Aucun message.';
        }

        return $this->literal($message, prefix: 'Message : ');
    }

    /**
     * The email body is Markdown: what the visitor typed must be shown as is,
     * never turned into HTML, a link, a heading or other formatting. Invisible
     * format characters (right-to-left override, zero width…) that could
     * disguise it are removed first. Every
     * punctuation character that HTML or Markdown could interpret becomes a
     * numeric character reference, which CommonMark treats as plain text and
     * the plain text version of the email decodes back.
     */
    private function literal(string $typed, string $prefix = '', string $suffix = ''): Htmlable
    {
        $visible = (string) preg_replace('/\p{Cf}/u', '', $typed);
        $singleLine = trim((string) preg_replace('/\s+/u', ' ', $visible));
        $encoded = (string) preg_replace_callback(
            '/[\\\\`*_{}\[\]()#+\-.!|~=<>&"\'\/:;]/',
            fn (array $match): string => '&#'.ord($match[0]).';',
            $singleLine,
        );

        return new HtmlString($prefix.$encoded.$suffix);
    }
}
