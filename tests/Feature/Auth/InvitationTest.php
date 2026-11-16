<?php

use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use App\Services\Auth\InvitationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

const INVITATION_EXPIRED = 'Ce lien pour définir votre mot de passe a expiré ou n\'est pas valide. Utilisez « Mot de passe oublié » pour en recevoir un nouveau.';
const INVITATION_USED = 'Votre mot de passe a déjà été défini. Connectez-vous, ou utilisez « Mot de passe oublié » si vous l\'avez oublié.';
const PASSWORD_DEFINED = 'Votre mot de passe est défini. Vous pouvez maintenant vous connecter.';

function invitationLink(User $user): string
{
    return (string) (new SetPasswordNotification)->toMail($user)->actionUrl;
}

/**
 * @return array<string, string>
 */
function newPassword(string $password = 'mon-nouveau-mot-de-passe'): array
{
    return ['password' => $password, 'password_confirmation' => $password];
}

it('keeps the invitation link valid for 7 days by default', function () {
    expect(config('famapp.invitation_expire_days'))->toBe(7);
});

it('renders the French set password page from the invitation link', function () {
    $user = User::factory()->invited()->create(['name' => 'Paul Martin', 'email' => 'paul@example.com']);
    $link = invitationLink($user);

    get($link)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/SetPassword')
            ->where('name', 'Paul Martin')
            ->where('email', 'paul@example.com')
            ->where('submitUrl', (string) strstr($link, '/invitation/')));
});

it('still accepts the invitation link after 6 days', function () {
    $user = User::factory()->invited()->create();
    $link = invitationLink($user);
    travel(6)->days();

    get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword'));
});

it('refuses an expired invitation link with a clear message pointing to the forgotten password page', function () {
    $user = User::factory()->invited()->create();
    $link = invitationLink($user);
    travel(7)->days();
    travel(1)->minute();

    get($link)
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/InvitationInvalid')
            ->where('message', INVITATION_EXPIRED));
});

it('follows the configured duration', function () {
    config(['famapp.invitation_expire_days' => 2]);
    $user = User::factory()->invited()->create();
    $link = invitationLink($user);
    travel(3)->days();

    get($link)->assertForbidden();
});

it('refuses a tampered invitation link', function () {
    $user = User::factory()->invited()->create();
    $other = User::factory()->invited()->create();
    $link = str_replace("/invitation/{$user->id}?", "/invitation/{$other->id}?", invitationLink($user));

    get($link)
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->where('message', INVITATION_EXPIRED));
    post($link, newPassword())->assertForbidden();

    expect($other->fresh()?->password_set_at)->toBeNull();
});

it('sets the password from the invitation link, then lets the member sign in', function () {
    $user = User::factory()->invited()->create(['email' => 'paul@example.com']);
    $link = invitationLink($user);
    travel(6)->days();

    post($link, newPassword())
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', PASSWORD_DEFINED);

    $user->refresh();
    expect(Hash::check('mon-nouveau-mot-de-passe', $user->password))->toBeTrue()
        ->and($user->password_set_at)->not->toBeNull();

    post(route('login.store'), ['email' => 'paul@example.com', 'password' => 'mon-nouveau-mot-de-passe'])
        ->assertRedirect(route('home'));
    assertAuthenticatedAs($user);
});

it('can be used only once', function () {
    $user = User::factory()->invited()->create();
    $link = invitationLink($user);
    post($link, newPassword());

    get($link)
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/InvitationInvalid')
            ->where('message', INVITATION_USED));
    post($link, newPassword('un-autre-mot-de-passe'))->assertForbidden();

    expect(Hash::check('mon-nouveau-mot-de-passe', (string) $user->fresh()?->password))->toBeTrue();
});

it('refuses to set the password from an expired link', function () {
    $user = User::factory()->invited()->create();
    $link = invitationLink($user);
    travel(8)->days();

    post($link, newPassword())->assertForbidden();

    expect($user->fresh()?->password_set_at)->toBeNull();
});

it('requires a confirmed password of the usual strength', function () {
    $user = User::factory()->invited()->create();
    $link = invitationLink($user);

    from($link)->post($link, newPassword('court'))->assertSessionHasErrors('password');
    from($link)->post($link, ['password' => 'mon-nouveau-mot-de-passe', 'password_confirmation' => 'autre'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()?->password_set_at)->toBeNull();
});

it('ends the invitation once the password was chosen through the forgotten password page', function () {
    $user = User::factory()->invited()->create();
    $link = invitationLink($user);

    post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        ...newPassword('choisi-par-oubli'),
    ])->assertRedirect(route('login'));

    expect($user->fresh()?->password_set_at)->not->toBeNull();
    get($link)->assertForbidden()->assertInertia(fn (Assert $page) => $page->where('message', INVITATION_USED));
});

it('keeps the invitation pages for guests only', function () {
    $user = User::factory()->invited()->create();
    actingAs(User::factory()->create());

    get(invitationLink($user))->assertRedirect(route('home'));
});

it('does not overwrite a password set in the meantime', function () {
    $user = User::factory()->create(['password' => 'deja-choisi']);

    expect(app(InvitationService::class)->setFirstPassword($user, 'autre-mot-de-passe'))->toBeFalse()
        ->and(Hash::check('deja-choisi', (string) $user->fresh()?->password))->toBeTrue();
});
