<?php

namespace App\Jobs\Auth;

use App\Services\Auth\AccountRequestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Handles an account request in the queue worker. The HTTP request only
 * validates the form and dispatches this job, so it does exactly the same work
 * whether the email is new, already has an account or a pending request: the
 * response time reveals nothing about known emails.
 */
class SubmitAccountRequest implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{name: string, email: string, message?: string|null}  $data
     */
    public function __construct(public readonly array $data) {}

    public function handle(AccountRequestService $accountRequests): void
    {
        $accountRequests->submit($this->data);
    }
}
