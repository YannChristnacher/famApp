<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ErrorLogEntryResource;
use App\Http\Resources\Admin\FailedJobResource;
use App\Http\Resources\Admin\GoogleBooksFailureResource;
use App\Http\Resources\Admin\GoogleBooksPeriodResource;
use App\Http\Resources\Admin\RecentAdditionResource;
use App\Http\Resources\Admin\RecentLoginResource;
use App\Services\Admin\ControlCountsService;
use App\Services\Admin\ErrorLogReader;
use App\Services\Admin\FailedJobService;
use App\Services\Admin\GoogleBooksActivityService;
use App\Services\Admin\RecentActivityService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin control page: activity and debugging figures (F13).
 */
class ControlPanelController extends Controller
{
    public function __invoke(
        ControlCountsService $counts,
        RecentActivityService $activity,
        FailedJobService $failedJobs,
        ErrorLogReader $errorLog,
        GoogleBooksActivityService $googleBooks,
    ): Response {
        $limit = config()->integer('famapp.control.recent_limit');

        return Inertia::render('Admin/Control/Index', [
            'pendingAccountRequests' => $counts->pendingAccountRequests(),
            'counts' => $counts->counts(),
            'recentAdditions' => RecentAdditionResource::collection($activity->latestAdditions($limit))->resolve(),
            'recentLogins' => RecentLoginResource::collection($activity->latestLogins($limit))->resolve(),
            'failedJobs' => [
                'total' => $failedJobs->count(),
                'latest' => FailedJobResource::collection($failedJobs->latest($limit))->resolve(),
            ],
            'errors' => ErrorLogEntryResource::collection($errorLog->latest($limit))->resolve(),
            'googleBooks' => [
                'periods' => GoogleBooksPeriodResource::collection($googleBooks->periods())->resolve(),
                'failures' => GoogleBooksFailureResource::collection($googleBooks->latestFailures($limit))->resolve(),
            ],
        ]);
    }
}
