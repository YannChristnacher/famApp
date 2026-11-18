<?php

use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    config(['famapp.control.log_directory' => storage_path('framework/testing/no-logs-'.Str::random(8))]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function insertFailedJob(array $attributes = []): string
{
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'mail',
        'payload' => json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\\Notifications\\Auth\\SetPasswordNotification',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => null,
            'data' => [
                'commandName' => 'Illuminate\\Notifications\\SendQueuedNotifications',
                'command' => 'O:48:"SECRET-PAYLOAD paul@example.com https://famapp.test/invitation?signature=abc123"',
            ],
        ]),
        'exception' => "RuntimeException: SMTP refused password=hunter2 for paul@example.com in /app/vendor/Mailer.php:12\nStack trace:\n#0 /app/vendor/Transport.php(42): send()\n#1 {main}",
        'failed_at' => now()->subMinutes(5),
        ...$attributes,
    ]);

    return $uuid;
}

it('lists the failed jobs without their payload nor their trace', function () {
    freezeTime();
    $uuid = insertFailedJob();
    actingAs(User::factory()->admin()->create());

    $response = get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('failedJobs.total', 1)
            ->has('failedJobs.latest', 1)
            ->where('failedJobs.latest.0', [
                'uuid' => $uuid,
                'queue' => 'mail',
                'connection' => 'database',
                'job' => 'App\\Notifications\\Auth\\SetPasswordNotification',
                'exception' => 'RuntimeException: SMTP refused password=*** for paul@example.com in /app/vendor/Mailer.php:12',
                'failedAt' => now()->subMinutes(5)->toIso8601String(),
            ]));

    $response->assertDontSee('SECRET-PAYLOAD')
        ->assertDontSee('signature=abc123', false)
        ->assertDontSee('hunter2')
        ->assertDontSee('Stack trace')
        ->assertDontSee('commandName');
});

it('truncates a long exception message', function () {
    insertFailedJob(['exception' => 'RuntimeException: '.str_repeat('a', 600)]);
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('failedJobs.latest.0.exception', fn (string $message) => mb_strlen($message) === 300 && str_ends_with($message, '…')));
});

it('counts every failed job but lists the ten latest, newest first', function () {
    foreach (range(1, 12) as $minutes) {
        insertFailedJob(['queue' => "queue-{$minutes}", 'failed_at' => now()->subMinutes($minutes)]);
    }
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('failedJobs.total', 12)
            ->has('failedJobs.latest', 10)
            ->where('failedJobs.latest.0.queue', 'queue-1')
            ->where('failedJobs.latest.9.queue', 'queue-10'));
});

it('shows an unknown job name when the payload cannot be read', function () {
    insertFailedJob(['payload' => 'not json']);
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page->where('failedJobs.latest.0.job', 'Inconnu'));
});

it('pushes a failed job back on its queue and forgets it', function () {
    Queue::fake();
    $uuid = insertFailedJob(['payload' => json_encode([
        'displayName' => SendQueuedNotifications::class,
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => [
            'commandName' => SendQueuedNotifications::class,
            'command' => serialize(new SendQueuedNotifications(collect([User::factory()->create()]), new SetPasswordNotification)),
        ],
    ])]);
    actingAs(User::factory()->admin()->create());

    post(route('admin.failed-jobs.retry', $uuid))
        ->assertRedirect(route('admin.control'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Le job a été remis dans sa file.']);

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(Queue::pushedRaw())->toHaveCount(1)
        ->and(Queue::pushedRaw()[0]['queue'])->toBe('mail');
});

it('deletes a failed job', function () {
    $uuid = insertFailedJob();
    $other = insertFailedJob();
    actingAs(User::factory()->admin()->create());

    delete(route('admin.failed-jobs.destroy', $uuid))
        ->assertRedirect(route('admin.control'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Le job en échec a été supprimé.']);

    expect(DB::table('failed_jobs')->pluck('uuid')->all())->toBe([$other]);
});

it('answers 404 for an unknown failed job', function (string $method) {
    Queue::fake();
    insertFailedJob();
    actingAs(User::factory()->admin()->create());
    $unknown = (string) Str::uuid();

    ($method === 'retry' ? post(route('admin.failed-jobs.retry', $unknown)) : delete(route('admin.failed-jobs.destroy', $unknown)))
        ->assertNotFound();

    expect(DB::table('failed_jobs')->count())->toBe(1);
    Queue::assertNothingPushed();
})->with(['retry', 'destroy']);

it('answers 404 for a malformed identifier', function () {
    actingAs(User::factory()->admin()->create());

    post('/admin/failed-jobs/not-a-uuid/retry')->assertNotFound();
    delete('/admin/failed-jobs/not-a-uuid')->assertNotFound();
});
