<?php

use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\freezeTime;

it('builds a French welcome email with a signed link to set the password', function () {
    config(['famapp.invitation_expire_days' => 7]);
    $user = User::factory()->invited()->make(['id' => 42, 'name' => 'Paul Martin', 'email' => 'paul@example.com']);

    $mail = (new SetPasswordNotification)->toMail($user);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Bienvenue sur famApp, définissez votre mot de passe')
        ->and($mail->greeting)->toBe('Bonjour Paul Martin,')
        ->and($mail->actionText)->toBe('Définir mon mot de passe')
        ->and((string) $mail->actionUrl)->toStartWith(route('invitation.show', ['user' => 42]).'?expires=')
        ->and(URL::hasValidSignature(Request::create((string) $mail->actionUrl)))->toBeTrue()
        ->and($rendered)->toContain('Un compte famApp vient d\'être créé pour vous')
        ->and($rendered)->toContain('Ce lien est valable 7 jours.')
        ->and($rendered)->toContain('Mot de passe oublié')
        ->and($rendered)->not->toContain('Hello')
        ->and($rendered)->not->toContain('Regards');
});

it('signs the link for the configured number of days', function () {
    config(['famapp.invitation_expire_days' => 3]);
    freezeTime();
    $user = User::factory()->invited()->make(['id' => 42]);

    $url = (string) (new SetPasswordNotification)->toMail($user)->actionUrl;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect((int) $query['expires'])->toBe(now()->addDays(3)->getTimestamp());
});

it('is queued after the commit and sent by mail', function () {
    $notification = new SetPasswordNotification;

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via(User::factory()->make()))->toBe(['mail']);
});
