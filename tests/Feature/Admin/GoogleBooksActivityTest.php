<?php

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\Library\GoogleBooksRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['famapp.control.log_directory' => storage_path('framework/testing/no-logs-'.Str::random(8))]);
});

function journalCall(GoogleBooksRequestStatus $status, int $duration, string $createdAt, ?User $user = null, GoogleBooksRequestType $type = GoogleBooksRequestType::Isbn, ?int $httpStatus = null): GoogleBooksRequest
{
    $call = new GoogleBooksRequest([
        'type' => $type,
        'query' => '9782070612758',
        'status' => $status,
        'http_status' => $httpStatus,
        'duration_ms' => $duration,
    ]);
    $call->user()->associate($user);
    $call->created_at = now()->modify($createdAt);
    $call->save();

    return $call;
}

it('counts the calls of the last day and of the last week by status, with their average duration', function () {
    freezeTime();
    journalCall(GoogleBooksRequestStatus::Found, 100, '-1 hour');
    journalCall(GoogleBooksRequestStatus::Found, 200, '-23 hours');
    journalCall(GoogleBooksRequestStatus::Timeout, 600, '-2 hours');
    journalCall(GoogleBooksRequestStatus::Found, 300, '-3 days');
    journalCall(GoogleBooksRequestStatus::NotFound, 400, '-6 days');
    journalCall(GoogleBooksRequestStatus::Found, 999, '-8 days');
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('googleBooks.periods.0', [
                'key' => 'day',
                'label' => '24 dernières heures',
                'total' => 3,
                'averageDurationMs' => 300,
                'statuses' => [
                    ['status' => 'found', 'label' => 'Trouvé', 'count' => 2],
                    ['status' => 'timeout', 'label' => 'Délai dépassé', 'count' => 1],
                ],
            ])
            ->where('googleBooks.periods.1', [
                'key' => 'week',
                'label' => '7 derniers jours',
                'total' => 5,
                'averageDurationMs' => 320,
                'statuses' => [
                    ['status' => 'found', 'label' => 'Trouvé', 'count' => 3],
                    ['status' => 'not_found', 'label' => 'Introuvable', 'count' => 1],
                    ['status' => 'timeout', 'label' => 'Délai dépassé', 'count' => 1],
                ],
            ]));
});

it('reports empty periods without an average', function () {
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('googleBooks.periods.0.total', 0)
            ->where('googleBooks.periods.0.averageDurationMs', null)
            ->where('googleBooks.periods.0.statuses', [])
            ->where('googleBooks.failures', []));
});

it('lists the latest failures, newest first, without the found and not found calls', function () {
    $marie = User::factory()->create(['name' => 'Marie']);
    journalCall(GoogleBooksRequestStatus::Found, 100, '-1 minute', $marie);
    journalCall(GoogleBooksRequestStatus::NotFound, 100, '-2 minutes', $marie);
    $older = journalCall(GoogleBooksRequestStatus::ServerError, 100, '-1 hour', null, GoogleBooksRequestType::Volume, 503);
    $newer = journalCall(GoogleBooksRequestStatus::Refused, 5, '-10 minutes', $marie, GoogleBooksRequestType::Cover);
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('googleBooks.failures', 2)
            ->where('googleBooks.failures.0', [
                'id' => $newer->id,
                'type' => 'Couverture',
                'query' => '9782070612758',
                'status' => 'refused',
                'statusLabel' => 'Refusé',
                'httpStatus' => null,
                'member' => 'Marie',
                'createdAt' => $newer->created_at?->toIso8601String(),
            ])
            ->where('googleBooks.failures.1.id', $older->id)
            ->where('googleBooks.failures.1.type', 'Volume')
            ->where('googleBooks.failures.1.httpStatus', 503)
            ->where('googleBooks.failures.1.member', null));
});

it('limits the latest failures to ten', function () {
    foreach (range(1, 12) as $minutes) {
        journalCall(GoogleBooksRequestStatus::ServerError, 100, "-{$minutes} minutes");
    }
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page->has('googleBooks.failures', 10));
});

it('gives a French label to every status and type', function () {
    foreach (GoogleBooksRequestStatus::cases() as $status) {
        expect($status->label())->not->toBe('')
            ->and($status->label())->not->toBe($status->value);
    }
    foreach (GoogleBooksRequestType::cases() as $type) {
        expect($type->label())->not->toBe('');
    }
});
