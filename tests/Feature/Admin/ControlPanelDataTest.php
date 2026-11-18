<?php

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\Auth\AccountRequest;
use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\Library\GoogleBooksRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['famapp.control.log_directory' => storage_path('framework/testing/no-logs-'.Str::random(8))]);
});

it('renders the control page', function () {
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Control/Index')
            ->has('pendingAccountRequests')
            ->has('counts')
            ->has('recentAdditions')
            ->has('recentLogins')
            ->has('failedJobs')
            ->has('errors')
            ->has('googleBooks'));
});

it('counts only the pending account requests', function () {
    AccountRequest::factory()->count(2)->create();
    AccountRequest::factory()->approved()->create();
    AccountRequest::factory()->rejected()->create();
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page->where('pendingAccountRequests', 2));
});

it('counts the members, invitations and objects exactly', function () {
    $admin = User::factory()->admin()->create();
    $marie = User::factory()->create();
    User::factory()->create();
    User::factory()->invited()->count(2)->create();

    Book::factory()->for($marie)->count(3)->create();
    Book::factory()->for($admin)->create();
    $bosch = Brand::factory()->create();
    Brand::factory()->create();
    Tool::factory()->for($marie)->create(['brand_id' => $bosch->id]);
    Tool::factory()->for($admin)->count(2)->create();
    Bookshelf::factory()->for($marie)->create();
    Bookshelf::factory()->for($marie)->create();
    ToolShelf::factory()->for($marie)->create();
    ToolShelf::factory()->for($admin)->create();
    ToolShelf::factory()->for($admin)->create();
    actingAs($admin);

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page->where('counts', [
            'members' => 3,
            'invited' => 2,
            'books' => 4,
            'tools' => 3,
            'bookshelves' => 2,
            'toolShelves' => 3,
            'brands' => 2,
        ]));
});

it('lists the ten latest books and tools, newest first', function () {
    $marie = User::factory()->create(['name' => 'Marie']);
    $paul = User::factory()->create(['name' => 'Paul']);
    foreach (range(1, 6) as $day) {
        Book::factory()->for($marie)->create(['title' => "Livre {$day}", 'created_at' => now()->subDays($day * 2)]);
        Tool::factory()->for($paul)->create(['name' => "Outil {$day}", 'created_at' => now()->subDays($day * 2 - 1)]);
    }
    $newest = Tool::factory()->for($marie)->create(['name' => 'Perceuse', 'created_at' => now()->subMinute()]);
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentAdditions', 10)
            ->where('recentAdditions.0', [
                'type' => 'tool',
                'id' => $newest->id,
                'label' => 'Perceuse',
                'owner' => 'Marie',
                'createdAt' => $newest->created_at?->toIso8601String(),
                'url' => route('garage.tools.show', $newest, false),
            ])
            ->where('recentAdditions.1.label', 'Outil 1')
            ->where('recentAdditions.2.label', 'Livre 1')
            ->where('recentAdditions.2.type', 'book')
            ->where('recentAdditions.2.owner', 'Marie')
            ->where('recentAdditions.2.url', fn (string $url) => str_starts_with($url, '/library/books/'))
            ->where('recentAdditions.8.label', 'Livre 4')
            ->where('recentAdditions.9.label', 'Outil 5'));
});

it('lists the latest logins, newest first, without the members who never signed in', function () {
    $old = User::factory()->create(['name' => 'Ancien', 'last_login_at' => now()->subDays(3)]);
    $recent = User::factory()->create(['name' => 'Récent', 'last_login_at' => now()->subMinutes(5)]);
    User::factory()->create(['name' => 'Jamais', 'last_login_at' => null]);
    actingAs(User::factory()->admin()->create(['last_login_at' => null]));

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentLogins', 2)
            ->where('recentLogins.0', [
                'id' => $recent->id,
                'name' => 'Récent',
                'lastLoginAt' => $recent->last_login_at?->toIso8601String(),
            ])
            ->where('recentLogins.1.id', $old->id));
});

it('limits the latest logins to ten', function () {
    foreach (range(1, 12) as $minutes) {
        User::factory()->create(['last_login_at' => now()->subMinutes($minutes)]);
    }
    actingAs(User::factory()->admin()->create(['last_login_at' => null]));

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page->has('recentLogins', 10));
});

it('keeps the number of queries constant whatever the amount of data', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $countQueries = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        get(route('admin.control'))->assertOk();
        DB::flushQueryLog();

        return $queries;
    };

    $seed = function (int $times): void {
        foreach (range(1, $times) as $i) {
            $member = User::factory()->create(['last_login_at' => now()->subMinutes($i)]);
            Book::factory()->for($member)->create();
            Tool::factory()->for($member)->create(['brand_id' => Brand::factory()->create()->id]);
            $uuid = (string) Str::uuid();
            DB::table('failed_jobs')->insert([
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\Example']),
                'exception' => 'RuntimeException: boom',
                'failed_at' => now(),
            ]);
            $call = new GoogleBooksRequest([
                'type' => GoogleBooksRequestType::Isbn,
                'query' => '9782070612758',
                'status' => GoogleBooksRequestStatus::ServerError,
                'http_status' => 500,
                'duration_ms' => 120,
            ]);
            $call->user()->associate($member);
            $call->save();
        }
    };

    $seed(1);
    $few = $countQueries();
    $seed(12);
    $many = $countQueries();

    expect($many)->toBe($few);
});
