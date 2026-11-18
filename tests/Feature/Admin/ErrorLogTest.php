<?php

use App\Models\User;
use App\Services\Admin\ErrorLogReader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $directory = storage_path('framework/testing/logs-'.Str::random(8));
    File::ensureDirectoryExists($directory);
    config(['famapp.control.log_directory' => $directory]);
});

afterEach(function () {
    File::deleteDirectory(config()->string('famapp.control.log_directory'));
});

function writeLog(string $content, string $name = 'laravel.log'): string
{
    $path = config()->string('famapp.control.log_directory').'/'.$name;
    file_put_contents($path, $content);

    return $path;
}

function sampleLog(): string
{
    return <<<'LOG'
[2026-11-18 09:00:00] production.INFO: Something normal happened
[2026-11-18 09:05:00] production.ERROR: Mail failed for password=hunter2&token=abc123 {"userId":3,"exception":"[object] (RuntimeException(code: 0): Mail failed at /app/Mailer.php:12)
[stacktrace]
#0 /app/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(42): send()
#1 {main}
"}
[2026-11-18 09:10:00] production.WARNING: Slow query
[2026-11-18 09:15:00] production.CRITICAL: Database down {"exception":"[object] (PDOException(code: 2002): Connection refused)"}
[2026-11-18 09:20:00] production.DEBUG: debug details
[2026-11-18 09:25:00] production.ALERT: Signed link https://famapp.test/invitation/3?expires=1&signature=5f2b9c secret_key: s3cr3t Authorization: Bearer eyJhbGciOi.abc.def [] []
[2026-11-18 09:30:00] production.EMERGENCY: Google key AIzaSyA1234567890abcdefghijklmnopqrstuv leaked "password":"p4ss" []
LOG;
}

it('lists the error entries and worse, newest first, without context nor trace', function () {
    writeLog(sampleLog());

    $entries = app(ErrorLogReader::class)->latest(10);

    expect(array_map(fn ($entry) => [$entry->level, $entry->loggedAt->format('H:i')], $entries))->toBe([
        ['emergency', '09:30'],
        ['alert', '09:25'],
        ['critical', '09:15'],
        ['error', '09:05'],
    ])
        ->and($entries[2]->message)->toBe('Database down')
        ->and($entries[3]->message)->toBe('Mail failed for password=***&token=***');
});

it('masks the secrets of the messages', function () {
    writeLog(sampleLog());

    $messages = array_map(fn ($entry) => $entry->message, app(ErrorLogReader::class)->latest(10));

    expect($messages[1])->toBe('Signed link https://famapp.test/invitation/3?expires=1&signature=*** secret_key: *** Authorization: ***')
        ->and($messages[0])->toBe('Google key *** leaked "password":"***"');
});

it('truncates a long message', function () {
    writeLog('[2026-11-18 09:05:00] local.ERROR: '.str_repeat('é', 500)." []\n");

    $message = app(ErrorLogReader::class)->latest(10)[0]->message;

    expect(mb_strlen($message))->toBe(300)
        ->and($message)->toEndWith('…');
});

it('keeps only the requested number of entries', function () {
    $lines = '';
    foreach (range(10, 30) as $minute) {
        $lines .= "[2026-11-18 09:{$minute}:00] local.ERROR: Error {$minute} []\n";
    }
    writeLog($lines);

    $entries = app(ErrorLogReader::class)->latest(10);

    expect($entries)->toHaveCount(10)
        ->and($entries[0]->message)->toBe('Error 30')
        ->and($entries[9]->message)->toBe('Error 21');
});

it('reads only the end of a big file', function () {
    config(['famapp.control.log_tail_bytes' => 1024]);
    $old = "[2026-11-18 08:00:00] local.ERROR: Old error at the start []\n";
    $padding = str_repeat("[2026-11-18 08:30:00] local.INFO: padding line of text []\n", 200);
    $recent = "[2026-11-18 09:00:00] local.ERROR: Recent error []\n";
    writeLog($old.$padding.$recent);

    $messages = array_map(fn ($entry) => $entry->message, app(ErrorLogReader::class)->latest(10));

    expect($messages)->toBe(['Recent error']);
});

it('reads the most recently written log file of a daily channel', function () {
    $older = writeLog("[2026-11-17 09:00:00] local.ERROR: Yesterday []\n", 'laravel-2026-11-17.log');
    touch($older, time() - 86400);
    writeLog("[2026-11-18 09:00:00] local.ERROR: Today []\n", 'laravel-2026-11-18.log');
    writeLog("[2026-11-18 10:00:00] local.ERROR: Not a Laravel log []\n", 'other.log');

    $messages = array_map(fn ($entry) => $entry->message, app(ErrorLogReader::class)->latest(10));

    expect($messages)->toBe(['Today']);
});

it('returns nothing without a log file', function () {
    expect(app(ErrorLogReader::class)->latest(10))->toBe([]);
});

it('shares the error entries on the control page', function () {
    writeLog(sampleLog());
    actingAs(User::factory()->admin()->create());

    get(route('admin.control'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('errors', 4)
            ->where('errors.3', [
                'level' => 'error',
                'message' => 'Mail failed for password=***&token=***',
                'loggedAt' => '2026-11-18T09:05:00+00:00',
            ]))
        ->assertDontSee('hunter2')
        ->assertDontSee('stacktrace')
        ->assertDontSee('5f2b9c');
});
