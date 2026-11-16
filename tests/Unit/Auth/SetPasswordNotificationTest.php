<?php

use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

it('builds a French welcome email with a link to set the password', function () {
    config(['auth.passwords.users.expire' => 60]);
    $user = User::factory()->make(['name' => 'Paul Martin', 'email' => 'paul@example.com']);

    $mail = (new SetPasswordNotification('jeton-secret'))->toMail($user);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Bienvenue sur famApp, définissez votre mot de passe')
        ->and($mail->greeting)->toBe('Bonjour Paul Martin,')
        ->and($mail->actionText)->toBe('Définir mon mot de passe')
        ->and($mail->actionUrl)->toBe(route('password.reset', ['token' => 'jeton-secret', 'email' => 'paul@example.com']))
        ->and($rendered)->toContain('Un compte famApp vient d&#039;être créé pour vous')
        ->and($rendered)->toContain('Ce lien expirera dans 60 minutes.')
        ->and($rendered)->toContain('Mot de passe oublié')
        ->and($rendered)->not->toContain('Hello')
        ->and($rendered)->not->toContain('Regards');
});

it('is queued after the commit and sent by mail', function () {
    $notification = new SetPasswordNotification('jeton');

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via(User::factory()->make()))->toBe(['mail']);
});
