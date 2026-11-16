<?php

use App\Enums\AccountRequestStatus;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\AccountRequestedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

const ACCOUNT_REQUEST_SENT_STATUS = 'Votre demande a bien été envoyée. Un administrateur vous contactera par email.';

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function validAccountRequest(array $overrides = []): array
{
    return [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'message' => 'Je suis le cousin de Marie.',
        ...$overrides,
    ];
}

/**
 * @param  array<string, string>  $data
 * @return TestResponse<Response>
 */
function postAccountRequest(array $data): TestResponse
{
    return from(route('account-request.create'))->post(route('account-request.store'), $data);
}

it('renders the account request page for guests', function () {
    get(route('account-request.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/RequestAccount'));
});

it('keeps the account request page for guests only', function () {
    Notification::fake();
    actingAs(User::factory()->create());

    get(route('account-request.create'))->assertRedirect(route('home'));
    post(route('account-request.store'), validAccountRequest())->assertRedirect(route('home'));

    expect(AccountRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('sends a signed-in user home with a full page visit from Inertia', function () {
    actingAs(User::factory()->create());

    get(route('account-request.create'), inertiaHeaders())
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('home'));
});

it('requires a name and an email', function () {
    Notification::fake();

    postAccountRequest(['name' => '', 'email' => '', 'message' => ''])
        ->assertRedirect(route('account-request.create'))
        ->assertSessionHasErrors([
            'name' => 'Le champ nom est obligatoire.',
            'email' => 'Le champ adresse e-mail est obligatoire.',
        ]);

    expect(AccountRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('requires a valid email', function () {
    postAccountRequest(validAccountRequest(['email' => 'pas-un-email']))
        ->assertSessionHasErrors(['email' => 'Le champ adresse e-mail doit être une adresse e-mail valide.']);

    expect(AccountRequest::query()->count())->toBe(0);
});

it('limits the length of the message and of the name', function () {
    postAccountRequest(validAccountRequest([
        'name' => str_repeat('a', 256),
        'message' => str_repeat('a', 2001),
    ]))->assertSessionHasErrors(['name', 'message']);

    expect(AccountRequest::query()->count())->toBe(0);
});

it('accepts a message of the maximum length', function () {
    postAccountRequest(validAccountRequest(['message' => str_repeat('a', 2000)]))
        ->assertSessionHasNoErrors();

    expect(AccountRequest::query()->count())->toBe(1);
});

it('stores a pending request with the email in lower case', function () {
    Notification::fake();

    postAccountRequest(validAccountRequest(['email' => '  Paul.Martin@Example.COM ']))
        ->assertSessionHasNoErrors();

    $request = AccountRequest::query()->sole();
    expect($request->name)->toBe('Paul Martin')
        ->and($request->email)->toBe('paul.martin@example.com')
        ->and($request->message)->toBe('Je suis le cousin de Marie.')
        ->and($request->status)->toBe(AccountRequestStatus::Pending);
});

it('makes the message optional', function () {
    Notification::fake();

    postAccountRequest(validAccountRequest(['message' => '']))->assertSessionHasNoErrors();

    expect(AccountRequest::query()->sole()->message)->toBeNull();
});

it('notifies every administrator and no other user', function () {
    Notification::fake();
    $admins = User::factory()->admin()->count(2)->create();
    $user = User::factory()->create();

    postAccountRequest(validAccountRequest());

    $request = AccountRequest::query()->sole();
    foreach ($admins as $admin) {
        Notification::assertSentTo(
            $admin,
            AccountRequestedNotification::class,
            fn (AccountRequestedNotification $notification) => $notification->accountRequest->is($request),
        );
    }
    Notification::assertNotSentTo($user, AccountRequestedNotification::class);
});

it('redirects to the login page with a French confirmation', function () {
    Notification::fake();

    postAccountRequest(validAccountRequest())
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', ACCOUNT_REQUEST_SENT_STATUS);
});

it('answers the same way for an email that already has an account, without storing or notifying', function () {
    Notification::fake();
    User::factory()->admin()->create();
    User::factory()->create(['email' => 'paul@example.com']);

    $new = postAccountRequest(validAccountRequest(['email' => 'nouveau@example.com']));
    AccountRequest::query()->delete();
    Notification::fake();

    $existing = postAccountRequest(validAccountRequest(['email' => 'Paul@Example.com']))
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', ACCOUNT_REQUEST_SENT_STATUS);

    expect($existing->getStatusCode())->toBe($new->getStatusCode())
        ->and($existing->headers->get('Location'))->toBe($new->headers->get('Location'))
        ->and(AccountRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('answers the same way when a request is already pending, without a duplicate or a new notification', function () {
    Notification::fake();
    User::factory()->admin()->create();
    postAccountRequest(validAccountRequest())->assertSessionHas('status', ACCOUNT_REQUEST_SENT_STATUS);
    Notification::fake();

    postAccountRequest(validAccountRequest(['email' => 'PAUL@example.com', 'message' => 'Relance']))
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', ACCOUNT_REQUEST_SENT_STATUS);

    expect(AccountRequest::query()->count())->toBe(1)
        ->and(AccountRequest::query()->sole()->message)->toBe('Je suis le cousin de Marie.');
    Notification::assertNothingSent();
});

it('accepts a new request after a rejected or approved one', function (string $state) {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    AccountRequest::factory()->{$state}()->create(['email' => 'paul@example.com']);

    postAccountRequest(validAccountRequest())->assertSessionHasNoErrors();

    expect(AccountRequest::query()->where('status', AccountRequestStatus::Pending)->count())->toBe(1);
    Notification::assertSentTo($admin, AccountRequestedNotification::class);
})->with(['rejected', 'approved']);

it('limits the account requests per IP address', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    foreach (range(1, 5) as $attempt) {
        postAccountRequest(validAccountRequest(['email' => "personne{$attempt}@example.com"]))
            ->assertSessionHasNoErrors();
    }

    postAccountRequest(validAccountRequest(['email' => 'personne6@example.com']))
        ->assertRedirect(route('account-request.create'))
        ->assertSessionHasErrors(['email' => 'Trop de demandes envoyées. Veuillez réessayer plus tard.'])
        ->assertSessionHasInput('name', 'Paul Martin')
        ->assertSessionHasInput('email', 'personne6@example.com');

    expect(AccountRequest::query()->where('email', 'personne6@example.com')->exists())->toBeFalse();
    Notification::assertSentToTimes($admin, AccountRequestedNotification::class, 5);
});
