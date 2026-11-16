<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;

it('builds a French reset password email with the reset link', function () {
    config(['auth.passwords.users.expire' => 60]);
    $user = User::factory()->make(['email' => 'marie@example.com']);

    $mail = (new ResetPasswordNotification('jeton-123'))->toMail($user);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Réinitialisation de votre mot de passe famApp')
        ->and($mail->actionText)->toBe('Choisir un nouveau mot de passe')
        ->and($mail->actionUrl)->toBe(route('password.reset', ['token' => 'jeton-123', 'email' => 'marie@example.com']))
        ->and($rendered)->toContain('Bonjour')
        ->and($mail->outroLines)->toContain('Ce lien expirera dans 60 minutes.')
        ->and(implode(' ', $mail->outroLines))->toContain("Si vous n'êtes pas à l'origine de cette demande")
        ->and($rendered)->toContain('Ce lien expirera dans 60 minutes.')
        ->and($rendered)->not->toContain('Reset Password')
        ->and($rendered)->toContain('Tous droits réservés')
        ->and($rendered)->toContain('copiez et collez')
        ->and($rendered)->not->toContain('Regards')
        ->and($rendered)->not->toContain('Hello')
        ->and($rendered)->not->toContain('having trouble')
        ->and($rendered)->not->toContain('All rights reserved');
});

it('is sent by mail', function () {
    $user = User::factory()->make();

    expect((new ResetPasswordNotification('jeton'))->via($user))->toBe(['mail']);
});
