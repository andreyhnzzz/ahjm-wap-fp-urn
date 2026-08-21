<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| Three things in this app grow without limit and nothing used to shrink
| them: generated exports (a 12,000-row PDF is ~4 MB), the rows of
| failed_jobs, and the row spools of exports whose job never ran.
|
| These only run where the scheduler runs. On a server that means one
| cron entry:
|
|     * * * * * cd /path/to/SIGA && php artisan schedule:run >> /dev/null 2>&1
|
| Without it none of this executes and the growth is exactly as before —
| which is why each command is also safe to run by hand.
|
*/
Schedule::command('exports:prune')
    ->dailyAt('03:10')
    // Overlapping runs would fight over the same files, and a prune that
    // takes longer than a day means something else is already wrong.
    ->withoutOverlapping()
    ->runInBackground();

// Keeps the failure log readable: what matters is that a job failed
// recently, not that one failed in March.
Schedule::command('queue:prune-failed --hours=720')->weekly();
