<?php

namespace App\Services\Admin;

use App\Support\SecretMasker;
use App\Support\TruncatedText;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;

/**
 * Failed jobs of the queue (table failed_jobs): summary for the admin control
 * page, retry and deletion.
 */
class FailedJobService
{
    public const UNKNOWN_JOB = 'Inconnu';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly FailedJobProviderInterface $failer,
        private readonly Artisan $artisan,
    ) {}

    public function count(): int
    {
        return $this->table()->count();
    }

    /**
     * The latest failed jobs, newest first.
     *
     * @return list<FailedJobSummary>
     */
    public function latest(int $limit): array
    {
        return $this->table()
            ->select(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => new FailedJobSummary(
                uuid: (string) $row->uuid,
                connection: (string) $row->connection,
                queue: (string) $row->queue,
                job: $this->jobName((string) $row->payload),
                exception: $this->exceptionSummary((string) $row->exception),
                failedAt: Carbon::parse((string) $row->failed_at),
            ))
            ->values()
            ->all();
    }

    public function exists(string $uuid): bool
    {
        return $this->table()->where('uuid', $uuid)->exists();
    }

    /**
     * Pushes the job back on its queue and removes it from the failed jobs.
     */
    public function retry(string $uuid): void
    {
        $this->artisan->call('queue:retry', ['id' => [$uuid]]);
    }

    public function forget(string $uuid): void
    {
        $this->failer->forget($uuid);
    }

    private function table(): Builder
    {
        $connection = config('queue.failed.database');

        return $this->database
            ->connection(is_string($connection) ? $connection : null)
            ->table(config()->string('queue.failed.table'));
    }

    /**
     * Class name of the job, the only thing read from the payload.
     */
    private function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) ? ($decoded['displayName'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : self::UNKNOWN_JOB;
    }

    /**
     * First line of the exception (no trace), masked and truncated.
     */
    private function exceptionSummary(string $exception): string
    {
        $firstLine = trim(strtok($exception, "\r\n") ?: '');

        return TruncatedText::of(
            SecretMasker::mask($firstLine),
            config()->integer('famapp.control.message_max_length'),
        );
    }
}
