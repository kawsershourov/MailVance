<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires `php artisan schedule:work` (or a cron entry running schedule:run).
Schedule::command('campaigns:process-scheduled')->everyMinute()->withoutOverlapping();

// Abandoned 200MB CSV uploads would otherwise accumulate forever.
Schedule::command('contacts:prune-temp-uploads')->hourly()->withoutOverlapping();
