<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deletes the expired lines of the prunable models (journal of the Google
// Books calls). The scheduler must run in production (F14).
Schedule::command('model:prune')->daily();
