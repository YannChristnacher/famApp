<?php

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\Library\GoogleBooksRequest;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\artisan;
use function Pest\Laravel\freezeTime;

function journalEntryAged(int $days): GoogleBooksRequest
{
    $call = new GoogleBooksRequest([
        'type' => GoogleBooksRequestType::Isbn,
        'query' => '9782070612758',
        'status' => GoogleBooksRequestStatus::Found,
        'http_status' => 200,
        'duration_ms' => 100,
    ]);
    $call->created_at = now()->subDays($days);
    $call->save();

    return $call;
}

it('is prunable', function () {
    expect(class_uses_recursive(GoogleBooksRequest::class))->toContain(MassPrunable::class);
});

it('keeps the calls for ninety days by default', function () {
    expect(config('services.google_books.journal_retention_days'))->toBe(90);
});

it('prunes the calls older than the retention period', function () {
    freezeTime();
    $old = journalEntryAged(91);
    $kept = journalEntryAged(89);

    artisan('model:prune')->assertSuccessful();

    expect(GoogleBooksRequest::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(GoogleBooksRequest::query()->find($old->id))->toBeNull();
});

it('follows the configured retention period', function () {
    freezeTime();
    config(['services.google_books.journal_retention_days' => 7]);
    journalEntryAged(8);
    $kept = journalEntryAged(6);

    artisan('model:prune', ['--model' => GoogleBooksRequest::class])->assertSuccessful();

    expect(GoogleBooksRequest::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('schedules the daily pruning of the models', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'model:prune'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('0 0 * * *');
});

it('indexes the journal by status and date', function () {
    $indexes = collect(Schema::getIndexes('google_books_requests'))->pluck('columns')->all();

    expect($indexes)->toContain(['status', 'created_at']);
});
