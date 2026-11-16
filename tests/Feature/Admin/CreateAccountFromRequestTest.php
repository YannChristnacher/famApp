<?php

use App\Enums\AccountRequestStatus;
use App\Enums\Role;
use App\Http\Requests\Admin\StoreAccountFromRequestRequest;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

const ALREADY_PROCESSED_ERROR = 'Cette demande a déjà été traitée.';

beforeEach(function () {
    Notification::fake();
    actingAs(User::factory()->admin()->create());
});

it('renders the account form prefilled with the request', function () {
    $request = AccountRequest::factory()->create([
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'message' => 'Je suis le cousin de Marie.',
    ]);

    get(route('admin.account-requests.account.create', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AccountRequests/CreateAccount')
            ->where('accountRequest', [
                'id' => $request->id,
                'name' => 'Paul Martin',
                'email' => 'paul@example.com',
                'message' => 'Je suis le cousin de Marie.',
            ])
            ->where('roles', ['user', 'admin']));
});

it('sends the admin back to the list when the request was already processed', function (string $state) {
    $request = AccountRequest::factory()->{$state}()->create();

    get(route('admin.account-requests.account.create', $request))
        ->assertRedirect(route('admin.account-requests.index'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => ALREADY_PROCESSED_ERROR]);
})->with(['approved', 'rejected']);

it('creates the account from the request with the chosen role', function (string $role) {
    $request = AccountRequest::factory()->create(['name' => 'Paul', 'email' => 'paul@example.com']);

    post(route('admin.account-requests.account.store', $request), [
        'name' => 'Paul Martin',
        'email' => '  Paul.Martin@Example.COM ',
        'role' => $role,
    ])
        ->assertRedirect(route('admin.account-requests.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Le compte de Paul Martin a été créé. Un email lui a été envoyé pour définir son mot de passe.',
        ]);

    $user = User::query()->where('email', 'paul.martin@example.com')->sole();
    $request->refresh();
    expect($user->name)->toBe('Paul Martin')
        ->and($user->role)->toBe(Role::from($role))
        ->and($request->status)->toBe(AccountRequestStatus::Approved)
        ->and($request->user_id)->toBe($user->id);
})->with(['user', 'admin']);

it('gives the new user an unusable random password', function () {
    $request = AccountRequest::factory()->create();

    post(route('admin.account-requests.account.store', $request), [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'role' => 'user',
    ]);

    $password = (string) User::query()->where('email', 'paul@example.com')->sole()->password;
    expect($password)->not->toBe('')
        ->and(Hash::isHashed($password))->toBeTrue()
        ->and(Hash::check('', $password))->toBeFalse()
        ->and(Hash::check('password', $password))->toBeFalse()
        ->and(Hash::check('paul@example.com', $password))->toBeFalse();
});

it('sends the queued set password notification to the new user only', function () {
    $request = AccountRequest::factory()->create();

    post(route('admin.account-requests.account.store', $request), [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'role' => 'user',
    ]);

    $user = User::query()->where('email', 'paul@example.com')->sole();
    Notification::assertSentTo($user, SetPasswordNotification::class);
    Notification::assertCount(1);
});

it('lets the new user set a password from the emailed link, then sign in', function () {
    $request = AccountRequest::factory()->create();
    post(route('admin.account-requests.account.store', $request), [
        'name' => 'Paul Martin',
        'email' => 'Paul@Example.com',
        'role' => 'user',
    ]);
    $user = User::query()->where('email', 'paul@example.com')->sole();
    expect($user->password_set_at)->toBeNull();
    $link = null;
    Notification::assertSentTo($user, SetPasswordNotification::class, function (SetPasswordNotification $notification) use ($user, &$link) {
        $link = (string) $notification->toMail($user)->actionUrl;

        return true;
    });
    auth()->logout();

    get($link)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/SetPassword')
            ->where('email', 'paul@example.com'));

    post($link, [
        'password' => 'mon-nouveau-mot-de-passe',
        'password_confirmation' => 'mon-nouveau-mot-de-passe',
    ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

    post(route('login.store'), ['email' => 'paul@example.com', 'password' => 'mon-nouveau-mot-de-passe'])
        ->assertRedirect(route('home'));
    assertAuthenticatedAs($user);
});

it('refuses invalid account data with French errors and changes nothing', function (array $data, string $field) {
    User::factory()->create(['email' => 'deja@example.com']);
    $request = AccountRequest::factory()->create();
    $userCount = User::query()->count();

    from(route('admin.account-requests.account.create', $request))
        ->post(route('admin.account-requests.account.store', $request), [
            'name' => 'Paul Martin',
            'email' => 'paul@example.com',
            'role' => 'user',
            ...$data,
        ])
        ->assertRedirect(route('admin.account-requests.account.create', $request))
        ->assertSessionHasErrors($field);

    expect(User::query()->count())->toBe($userCount)
        ->and($request->fresh()?->status)->toBe(AccountRequestStatus::Pending);
    Notification::assertNothingSent();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
    'missing email' => [['email' => ''], 'email'],
    'invalid email' => [['email' => 'pas-un-email'], 'email'],
    'email too long' => [['email' => str_repeat('a', 250).'@example.com'], 'email'],
    'email already used' => [['email' => 'deja@example.com'], 'email'],
    'email already used, other case' => [['email' => ' DEJA@Example.com '], 'email'],
    'missing role' => [['role' => ''], 'role'],
    'unknown role' => [['role' => 'superadmin'], 'role'],
]);

it('writes the validation errors in French', function () {
    User::factory()->create(['email' => 'deja@example.com']);
    $request = AccountRequest::factory()->create();

    from(route('admin.account-requests.account.create', $request))
        ->post(route('admin.account-requests.account.store', $request), [
            'name' => '',
            'email' => 'deja@example.com',
            'role' => 'superadmin',
        ])
        ->assertSessionHasErrors([
            'name' => 'Le champ nom est obligatoire.',
            'email' => 'La valeur du champ adresse e-mail est déjà utilisée.',
            'role' => 'Le champ rôle est invalide.',
        ]);
});

it('refuses to create an account from a request already processed', function (string $state) {
    $request = AccountRequest::factory()->{$state}()->create();
    $userCount = User::query()->count();

    post(route('admin.account-requests.account.store', $request), [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'role' => 'user',
    ])
        ->assertRedirect(route('admin.account-requests.index'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => ALREADY_PROCESSED_ERROR]);

    expect(User::query()->count())->toBe($userCount)
        ->and($request->fresh()?->status)->toBe(AccountRequestStatus::from($state));
    Notification::assertNothingSent();
})->with(['approved', 'rejected']);

it('refuses to create a second account from the same request', function () {
    $request = AccountRequest::factory()->create();
    $data = ['name' => 'Paul Martin', 'email' => 'paul@example.com', 'role' => 'user'];
    post(route('admin.account-requests.account.store', $request), $data);

    post(route('admin.account-requests.account.store', $request), [...$data, 'email' => 'autre@example.com'])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => ALREADY_PROCESSED_ERROR]);

    expect(User::query()->where('email', 'autre@example.com')->exists())->toBeFalse();
    Notification::assertCount(1);
});

it('answers 404 for an unknown request', function () {
    get(route('admin.account-requests.account.create', 999))->assertNotFound();
});

it('only lets an admin pass the account form request', function () {
    $request = new StoreAccountFromRequestRequest;

    $request->setUserResolver(fn () => User::factory()->admin()->make());
    expect($request->authorize())->toBeTrue();

    $request->setUserResolver(fn () => User::factory()->make());
    expect($request->authorize())->toBeFalse();

    $request->setUserResolver(fn () => null);
    expect($request->authorize())->toBeFalse();
});

it('only offers the member and administrator roles, the same as the form', function () {
    $request = AccountRequest::factory()->create();

    get(route('admin.account-requests.account.create', $request))
        ->assertInertia(fn (Assert $page) => $page->where('roles', Role::assignableValues()));
    expect(Role::assignableValues())->toBe(['user', 'admin']);
});
