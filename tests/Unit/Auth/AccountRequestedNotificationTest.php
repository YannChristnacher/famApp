<?php

use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\AccountRequestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Markdown;

function accountRequestMail(?string $message): string
{
    $accountRequest = new AccountRequest([
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'message' => $message,
    ]);
    $admin = User::factory()->admin()->make(['name' => 'Marie Dupont']);

    return (string) (new AccountRequestedNotification($accountRequest))->toMail($admin)->render();
}

it('builds a French email with the name, the email and the message of the requester', function () {
    $accountRequest = new AccountRequest([
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'message' => 'Je suis le cousin de Marie.',
    ]);
    $admin = User::factory()->admin()->make(['name' => 'Marie Dupont']);

    $mail = (new AccountRequestedNotification($accountRequest))->toMail($admin);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Nouvelle demande de compte famApp')
        ->and($mail->greeting)->toBe('Bonjour Marie Dupont,')
        ->and($mail->actionText)->toBe('Ouvrir famApp')
        ->and($mail->actionUrl)->toBe(route('home'))
        ->and($rendered)->toContain('Paul Martin')
        ->and($rendered)->toContain('paul@example.com')
        ->and($rendered)->toContain('Je suis le cousin de Marie.')
        ->and($rendered)->toContain('demande un compte')
        ->and($rendered)->not->toContain('Hello')
        ->and($rendered)->not->toContain('Regards');
});

it('says when the requester left no message', function () {
    expect(accountRequestMail(null))->toContain('Aucun message.');
});

it('does not render the Markdown typed by the requester', function () {
    $rendered = accountRequestMail("# Urgent\n[Cliquez ici](https://pirate.example) **gras**");

    expect($rendered)->not->toContain('href="https://pirate.example"')
        ->and($rendered)->not->toContain('<h1>Urgent')
        ->and($rendered)->not->toContain('<strong>gras')
        ->and($rendered)->toContain('Cliquez ici');
});

it('removes the invisible Unicode format characters typed by the requester', function () {
    $rendered = accountRequestMail("Paul\u{202E}nitraM\u{200B} \u{2066}ici\u{2069}\u{FEFF}");

    expect($rendered)->toContain('Message : PaulnitraM ici')
        ->and($rendered)->not->toMatch('/\p{Cf}/u');
});

it('does not render the HTML typed by the requester', function () {
    $rendered = accountRequestMail('<a href="https://pirate.example">ici</a> <img src=x>');

    expect($rendered)->not->toContain('<a href="https://pirate.example"')
        ->and($rendered)->not->toContain('<img')
        ->and($rendered)->toContain('&lt;img');
});

it('shows what the requester typed unchanged in the plain text version', function () {
    $accountRequest = new AccountRequest([
        'name' => 'Jean-Paul & Co.',
        'email' => 'jean_paul@example.com',
        'message' => "[Lien](https://exemple.fr) *étoile*\nSeconde ligne",
    ]);
    $mail = (new AccountRequestedNotification($accountRequest))->toMail(User::factory()->admin()->make());

    $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());

    expect($text)->toContain('Nom : Jean-Paul & Co.')
        ->and($text)->toContain('Adresse email : jean_paul@example.com')
        ->and($text)->toContain('Message : [Lien](https://exemple.fr) *étoile* Seconde ligne');
});

it('is queued and sent by mail', function () {
    $notification = new AccountRequestedNotification(new AccountRequest);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->via(User::factory()->admin()->make()))->toBe(['mail']);
});
