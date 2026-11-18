<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FailedJobActionRequest;
use App\Services\Admin\FailedJobService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Retry or deletion of a failed job from the admin control page.
 */
class FailedJobController extends Controller
{
    public function __construct(private readonly FailedJobService $failedJobs) {}

    public function retry(FailedJobActionRequest $request, string $uuid): RedirectResponse
    {
        abort_unless($this->failedJobs->exists($uuid), 404);

        $this->failedJobs->retry($uuid);

        return $this->backToControlPage('Le job a été remis dans sa file.');
    }

    public function destroy(FailedJobActionRequest $request, string $uuid): RedirectResponse
    {
        abort_unless($this->failedJobs->exists($uuid), 404);

        $this->failedJobs->forget($uuid);

        return $this->backToControlPage('Le job en échec a été supprimé.');
    }

    private function backToControlPage(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.control');
    }
}
