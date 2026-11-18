<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array<string, Closure(string): TestResponse<Response>>
 */
function controlPanelCalls(): array
{
    return [
        'control' => fn (string $uuid) => get(route('admin.control')),
        'retry' => fn (string $uuid) => post(route('admin.failed-jobs.retry', $uuid)),
        'destroy' => fn (string $uuid) => delete(route('admin.failed-jobs.destroy', $uuid)),
    ];
}

function insertFailedJobForAccess(): string
{
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\Example', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]),
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    return $uuid;
}

it('lets an admin reach the control page and the failed job actions', function (string $call, int $status) {
    Queue::fake();
    $uuid = insertFailedJobForAccess();
    actingAs(User::factory()->admin()->create());

    controlPanelCalls()[$call]($uuid)->assertStatus($status);
})->with([
    'control' => ['control', 200],
    'retry' => ['retry', 302],
    'destroy' => ['destroy', 302],
]);

it('forbids a regular user the control page and the failed job actions', function (string $call) {
    Queue::fake();
    $uuid = insertFailedJobForAccess();
    actingAs(User::factory()->create());

    controlPanelCalls()[$call]($uuid)->assertForbidden();

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeTrue();
    Queue::assertNothingPushed();
})->with(['control', 'retry', 'destroy']);

it('redirects a guest to the login page and changes nothing', function (string $call) {
    Queue::fake();
    $uuid = insertFailedJobForAccess();

    controlPanelCalls()[$call]($uuid)->assertRedirect(route('login'));

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeTrue();
    Queue::assertNothingPushed();
})->with(['control', 'retry', 'destroy']);
