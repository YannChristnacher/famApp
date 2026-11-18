<?php

use App\Enums\AccountRequestStatus;
use App\Enums\Role;
use App\Jobs\Auth\SubmitAccountRequest;
use App\Models\Auth\AccountRequest;
use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolAccessory;
use App\Models\Garage\ToolPhoto;
use App\Models\Garage\ToolShelf;
use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\Library\GoogleBooksRequest;
use App\Models\User;
use App\Rules\Library\Isbn;
use App\Services\Admin\FailedJobService;
use Database\Seeders\Demo\DemoAccountsSeeder;
use Database\Seeders\Demo\DemoActivitySeeder;
use Database\Seeders\Demo\DemoDataSeeder;
use Database\Seeders\Demo\DemoGarageSeeder;
use Database\Seeders\Demo\DemoImageGenerator;
use Database\Seeders\Demo\DemoLibrarySeeder;
use Database\Seeders\Demo\DemoMembers;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(function () {
    config([
        'famapp.admin.name' => 'Administrateur',
        'famapp.admin.email' => 'admin@famapp.test',
        'famapp.admin.password' => 'mot-de-passe-admin',
    ]);
    Storage::fake('public');
    Http::preventStrayRequests();
    // Small images keep the test fast; the pipeline is the same.
    app()->when(DemoImageGenerator::class)->needs('$scale')->give(fn () => 0.1);
});

/**
 * @return list<User>
 */
function demoMembers(): array
{
    return User::query()->whereIn('email', DemoMembers::activatedEmails())->orderBy('id')->get()->all();
}

it('loads the accounts, the invitation and the account requests', function () {
    seed(DemoSeeder::class);

    expect(User::query()->where('email', 'admin@famapp.test')->sole()->role)->toBe(Role::Admin)
        ->and(User::query()->activated()->count())->toBe(5)
        ->and(User::query()->whereNull('password_set_at')->count())->toBe(1)
        ->and(User::query()->where('role', Role::Admin)->count())->toBe(2);

    $members = collect(demoMembers());
    expect($members)->toHaveCount(4)
        ->and($members->every(fn (User $user) => str_ends_with($user->email, '@famapp.local')))->toBeTrue()
        ->and($members->filter(fn (User $user) => $user->isAdmin()))->toHaveCount(1)
        ->and($members->whereNull('last_login_at'))->toHaveCount(1)
        ->and($members->pluck('last_login_at')->filter()->map->toDateTimeString()->unique())->toHaveCount(3);

    $invited = User::query()->whereNull('password_set_at')->sole();
    expect($invited->email)->toBe(DemoMembers::INVITED_EMAIL)
        ->and($invited->last_login_at)->toBeNull();

    expect(AccountRequest::query()->pending()->count())->toBe(2)
        ->and(AccountRequest::query()->where('status', AccountRequestStatus::Rejected)->count())->toBe(1);
    $approved = AccountRequest::query()->where('status', AccountRequestStatus::Approved)->sole();
    expect($approved->user_id)->toBe($invited->id)
        ->and($approved->email)->toBe($invited->email);
});

it('gives every member compact bookshelves and tool shelves', function () {
    seed(DemoSeeder::class);

    foreach (demoMembers() as $member) {
        expect(bookshelfNames($member))->not->toBeEmpty()
            ->and(toolShelfNames($member))->not->toBeEmpty();
    }
    expect(Bookshelf::query()->count())->toBeGreaterThanOrEqual(8)
        ->and(ToolShelf::query()->count())->toBeGreaterThanOrEqual(6);
});

it('only puts books and tools on shelves of their owner', function () {
    seed(DemoSeeder::class);

    $books = Book::query()->with('bookshelves')->get();
    $tools = Tool::query()->with('shelves')->get();

    expect($books->every(fn (Book $book) => $book->bookshelves->every(fn (Bookshelf $shelf) => $shelf->user_id === $book->user_id)))->toBeTrue()
        ->and($tools->every(fn (Tool $tool) => $tool->shelves->every(fn (ToolShelf $shelf) => $shelf->user_id === $tool->user_id)))->toBeTrue();
});

it('loads thirty real books with valid ISBNs, covers and comments', function () {
    seed(DemoSeeder::class);

    $books = Book::query()->with('bookshelves')->get();
    expect($books)->toHaveCount(30)
        ->and($books->every(fn (Book $book) => $book->title !== '' && $book->author !== ''))->toBeTrue();

    foreach ($books as $book) {
        expect(Validator::make(['isbn' => $book->isbn], ['isbn' => ['required', new Isbn]])->passes())
            ->toBeTrue("Invalid ISBN {$book->isbn} for {$book->title}")
            ->and($book->isbn)->toHaveLength(13);
    }

    $duplicates = $books->groupBy('isbn')->filter(fn ($sameIsbn) => $sameIsbn->pluck('user_id')->unique()->count() > 1);
    expect($duplicates->count())->toBeGreaterThanOrEqual(2);

    $shelfCounts = $books->map(fn (Book $book) => min($book->bookshelves->count(), 2))->unique()->sort()->values()->all();
    expect($shelfCounts)->toBe([0, 1, 2])
        ->and($books->whereNotNull('comment')->count())->toBeGreaterThanOrEqual(3)
        ->and(collect(demoMembers())->every(fn (User $user) => $books->where('user_id', $user->id)->isNotEmpty()))->toBeTrue();

    $withCover = $books->whereNotNull('cover_path');
    expect($withCover->count())->toBeGreaterThan(20)
        ->and($books->whereNull('cover_path'))->not->toBeEmpty();
    foreach ($withCover as $book) {
        expect($book->cover_path)->toMatch('#^covers/[A-Za-z0-9]{40}\.jpg$#')
            ->and(storedImage((string) $book->cover_path)['mime'])->toBe('image/jpeg');
    }
});

it('loads twenty tools with shared brands, accessories and photos', function () {
    seed(DemoSeeder::class);

    $tools = Tool::query()->with(['brand', 'photos', 'accessories', 'shelves'])->get();
    expect($tools)->toHaveCount(20)
        ->and($tools->whereNotNull('comment')->count())->toBeGreaterThanOrEqual(3)
        ->and($tools->whereNull('brand_id'))->not->toBeEmpty()
        ->and(collect(demoMembers())->every(fn (User $user) => $tools->where('user_id', $user->id)->isNotEmpty()))->toBeTrue();

    $brands = Brand::query()->get();
    expect($brands->count())->toBeGreaterThanOrEqual(8)
        ->and($brands->pluck('normalized_name')->unique()->count())->toBe($brands->count())
        ->and($brands->pluck('name'))->toContain('Bosch', 'Makita', 'DeWalt', 'Ryobi', 'Stanley', 'Festool');

    $bosch = Brand::query()->where('normalized_name', 'bosch')->sole();
    expect($tools->where('brand_id', $bosch->id)->pluck('user_id')->unique()->count())->toBeGreaterThan(1);

    expect($tools->map(fn (Tool $tool) => $tool->photos->count())->unique()->sort()->values()->all())->toBe([0, 1, 2, 3])
        ->and($tools->filter(fn (Tool $tool) => $tool->accessories->isNotEmpty()))->not->toBeEmpty()
        ->and($tools->filter(fn (Tool $tool) => $tool->shelves->isEmpty()))->not->toBeEmpty();

    foreach ($tools as $tool) {
        expect($tool->photos->pluck('position')->all())->toBe($tool->photos->isEmpty() ? [] : range(1, $tool->photos->count()))
            ->and($tool->accessories->pluck('position')->all())->toBe($tool->accessories->isEmpty() ? [] : range(1, $tool->accessories->count()));
    }
    foreach (ToolPhoto::query()->pluck('path') as $path) {
        expect($path)->toMatch('#^tools/[A-Za-z0-9]{40}\.jpg$#')
            ->and(storedImage($path)['mime'])->toBe('image/jpeg');
    }
    expect(ToolAccessory::query()->count())->toBeGreaterThanOrEqual(10);
});

it('fills the Google Books journal of the last seven days', function () {
    seed(DemoSeeder::class);

    $calls = GoogleBooksRequest::query()->get();
    expect($calls->count())->toBeGreaterThanOrEqual(15)
        ->and($calls->every(fn (GoogleBooksRequest $call) => $call->created_at?->greaterThan(now()->subDays(7))))->toBeTrue()
        ->and($calls->where('created_at', '>=', now()->subDay())->count())->toBeGreaterThan(0)
        ->and($calls->pluck('status')->unique()->count())->toBeGreaterThanOrEqual(5)
        ->and($calls->pluck('type')->unique()->count())->toBe(3);
});

it('adds two retryable failed jobs without secrets in their payload', function () {
    seed(DemoSeeder::class);

    $failedJobs = app(FailedJobService::class)->latest(10);
    expect($failedJobs)->toHaveCount(2)
        ->and(collect($failedJobs)->pluck('job')->unique()->all())->toBe([SubmitAccountRequest::class]);

    foreach (DB::table('failed_jobs')->get() as $row) {
        $payload = json_decode((string) $row->payload, true);
        expect($payload)->toBeArray()
            ->and($payload['uuid'])->toBe($row->uuid)
            ->and($payload['job'])->toBe('Illuminate\Queue\CallQueuedHandler@call')
            ->and(unserialize($payload['data']['command']))->toBeInstanceOf(SubmitAccountRequest::class)
            ->and(strtolower((string) $row->payload))->not->toContain('password')
            ->and(strtolower((string) $row->payload))->not->toContain('token')
            ->and(strtolower((string) $row->payload))->not->toContain('secret');
    }
});

dataset('demo seeders', [
    'DemoSeeder' => DemoSeeder::class,
    'DemoAccountsSeeder' => DemoAccountsSeeder::class,
    'DemoLibrarySeeder' => DemoLibrarySeeder::class,
    'DemoGarageSeeder' => DemoGarageSeeder::class,
    'DemoActivitySeeder' => DemoActivitySeeder::class,
]);

it('refuses to run outside the allowed environments and writes nothing', function (string $seeder, string $environment) {
    app()->detectEnvironment(fn () => $environment);

    expect(fn () => Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]))
        ->toThrow(RuntimeException::class, "l'environnement « {$environment} »");

    expect(User::query()->count())->toBe(0)
        ->and(AccountRequest::query()->count())->toBe(0)
        ->and(GoogleBooksRequest::query()->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with('demo seeders')->with(['production', 'staging', 'prod']);

it('runs in the allowed environments', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

    expect(User::query()->where('email', DemoMembers::ADMIN_EMAIL)->exists())->toBeTrue()
        ->and(DemoDataSeeder::ALLOWED_ENVIRONMENTS)->toBe(['local', 'testing']);
})->with(['local', 'testing']);

it('does nothing more when run again', function () {
    seed(DemoSeeder::class);
    $files = Storage::disk('public')->allFiles();

    artisan('db:seed', ['--class' => DemoSeeder::class])
        ->expectsOutputToContain('déjà chargées')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(6)
        ->and(Book::query()->count())->toBe(30)
        ->and(Tool::query()->count())->toBe(20)
        ->and(DB::table('failed_jobs')->count())->toBe(2)
        ->and(Storage::disk('public')->allFiles())->toBe($files);
});

it('prints the demo accounts and their password', function () {
    artisan('db:seed', ['--class' => DemoSeeder::class])
        ->expectsOutputToContain('camille@famapp.local')
        ->expectsOutputToContain('mot de passe : '.DemoMembers::PASSWORD)
        ->expectsOutputToContain('storage:link')
        ->assertSuccessful();
});

it('lets the activated demo members sign in with the documented password', function (string $email) {
    seed(DemoSeeder::class);

    post(route('login.store'), ['email' => $email, 'password' => 'password']);

    assertAuthenticatedAs(User::query()->where('email', $email)->sole());
})->with(DemoMembers::activatedEmails());

it('does not let the invited member sign in', function () {
    seed(DemoSeeder::class);

    post(route('login.store'), ['email' => DemoMembers::INVITED_EMAIL, 'password' => 'password']);

    assertGuest();
});

it('still loads the demo when no admin is configured', function () {
    config(['famapp.admin.email' => null, 'famapp.admin.password' => null]);

    artisan('db:seed', ['--class' => DemoSeeder::class])
        ->expectsOutputToContain('ADMIN_EMAIL')
        ->assertSuccessful();

    expect(User::query()->where('role', Role::Admin)->pluck('email')->all())->toBe([DemoMembers::ADMIN_EMAIL]);
});

it('lets the administrator retry a demo failed job, which records the account request', function () {
    config(['queue.default' => 'database']);
    Notification::fake();
    seed(DemoSeeder::class);
    $uuid = (string) DB::table('failed_jobs')->orderBy('id')->value('uuid');

    app(FailedJobService::class)->retry($uuid);
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(AccountRequest::query()->pending()->where('email', 'bernard.petit@famapp.local')->exists())->toBeTrue();
});
