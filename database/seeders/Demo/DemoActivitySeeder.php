<?php

namespace Database\Seeders\Demo;

use App\Enums\Library\GoogleBooksRequestStatus as Status;
use App\Enums\Library\GoogleBooksRequestType as Type;
use App\Jobs\Auth\SubmitAccountRequest;
use App\Models\Library\GoogleBooksRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

/**
 * Activity shown on the admin control page: Google Books calls of the last
 * seven days in every status, and two failed jobs that can be retried (an
 * account request of a fictitious visitor: a retry records it and emails the
 * administrators) or deleted.
 */
class DemoActivitySeeder extends Seeder
{
    private const COVER_QUERY = 'books.google.com/books/content';

    /**
     * Calls: hours ago, type, query, status, HTTP status, duration (ms),
     * member.
     *
     * @var list<array{int, Type, string, Status, int|null, int, string}>
     */
    private const GOOGLE_BOOKS_CALLS = [
        [1, Type::Isbn, '9782070612758', Status::Found, 200, 312, DemoMembers::THOMAS_EMAIL],
        [1, Type::Cover, self::COVER_QUERY, Status::Found, 200, 188, DemoMembers::THOMAS_EMAIL],
        [3, Type::Isbn, '9782266320481', Status::Found, 200, 274, DemoMembers::THOMAS_EMAIL],
        [3, Type::Volume, 'B1hSG45JCX4C', Status::Found, 200, 241, DemoMembers::THOMAS_EMAIL],
        [6, Type::Isbn, '9791032100004', Status::NotFound, 200, 198, DemoMembers::SOPHIE_EMAIL],
        [9, Type::Isbn, '9782253140870', Status::Timeout, null, 5004, DemoMembers::SOPHIE_EMAIL],
        [20, Type::Isbn, '9782253140870', Status::Found, 200, 402, DemoMembers::SOPHIE_EMAIL],
        [20, Type::Cover, self::COVER_QUERY, Status::StorageFailed, 200, 356, DemoMembers::SOPHIE_EMAIL],
        [30, Type::Isbn, '9782070360024', Status::Found, 200, 287, DemoMembers::JULIEN_EMAIL],
        [31, Type::Isbn, '9782070368228', Status::RateLimited, 429, 143, DemoMembers::JULIEN_EMAIL],
        [50, Type::Isbn, '9782012101333', Status::Found, 200, 266, DemoMembers::JULIEN_EMAIL],
        [50, Type::Volume, 'xQqXDwAAQBAJ', Status::ServerError, 503, 1210, DemoMembers::JULIEN_EMAIL],
        [74, Type::Isbn, '9782877068161', Status::Found, 200, 301, DemoMembers::ADMIN_EMAIL],
        [74, Type::Cover, 'images.exemple.fr/couverture.jpg', Status::Refused, null, 0, DemoMembers::ADMIN_EMAIL],
        [98, Type::Isbn, '9782226257017', Status::Found, 200, 355, DemoMembers::ADMIN_EMAIL],
        [99, Type::Volume, 'Tj6pDwAAQBAJ', Status::InvalidResponse, 200, 420, DemoMembers::ADMIN_EMAIL],
        [122, Type::Isbn, '9782070584628', Status::Found, 200, 239, DemoMembers::SOPHIE_EMAIL],
        [140, Type::Isbn, '9782070391653', Status::ClientError, 400, 175, DemoMembers::ADMIN_EMAIL],
        [150, Type::Isbn, '9782070391653', Status::Found, 200, 293, DemoMembers::ADMIN_EMAIL],
        [160, Type::Cover, self::COVER_QUERY, Status::Found, 200, 205, DemoMembers::ADMIN_EMAIL],
    ];

    public function __construct(private readonly FailedJobProviderInterface $failedJobs) {}

    public function run(): void
    {
        $this->googleBooksCalls();
        $this->failedJobs();
    }

    private function googleBooksCalls(): void
    {
        $members = User::query()->whereIn('email', DemoMembers::activatedEmails())->pluck('id', 'email');

        foreach (self::GOOGLE_BOOKS_CALLS as $index => [$hoursAgo, $type, $query, $status, $httpStatus, $duration, $email]) {
            $calledAt = now()->subHours($hoursAgo)->subMinutes(7 * $index);

            GoogleBooksRequest::factory()->create([
                'user_id' => $members[$email],
                'type' => $type,
                'query' => $query,
                'status' => $status,
                'http_status' => $httpStatus,
                'duration_ms' => $duration,
                'created_at' => $calledAt,
                'updated_at' => $calledAt,
            ]);
        }
    }

    private function failedJobs(): void
    {
        $this->failedJob(
            new SubmitAccountRequest([
                'name' => 'Bernard Petit',
                'email' => 'bernard.petit@famapp.local',
                'message' => 'Je suis le voisin de Sophie, elle m\'a dit de m\'inscrire.',
            ]),
            new TransportException('Connection could not be established with host "mailpit:1025": stream_socket_client(): Unable to connect to mailpit:1025 (Connection refused)'),
        );
        $this->failedJob(
            new SubmitAccountRequest([
                'name' => 'Chantal Moreau',
                'email' => 'chantal.moreau@famapp.local',
                'message' => null,
            ]),
            new MaxAttemptsExceededException(SubmitAccountRequest::class.' has been attempted too many times.'),
        );
    }

    /**
     * Records the job as failed with a payload in the format of the Laravel
     * queue, so that "Relancer" pushes it back as it is.
     */
    private function failedJob(SubmitAccountRequest $job, Throwable $exception): void
    {
        $payload = json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => $job::class,
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'retryUntil' => null,
            'data' => [
                'commandName' => $job::class,
                'command' => serialize($job),
                'batchId' => null,
            ],
            'createdAt' => now()->subHours(4)->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $this->failedJobs->log(config()->string('queue.default'), 'default', $payload, $exception);
    }
}
