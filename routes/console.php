<?php

use App\Jobs\ExpireSubscriptions;
use App\Jobs\PublishDueCalendarItems;
use App\Jobs\RefreshConnectionTokens;
use App\Jobs\ResetMonthlyAiCredits;
use App\Jobs\SyncConnectedAccounts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PublishDueCalendarItems)->everyMinute()->withoutOverlapping();
Schedule::job(new SyncConnectedAccounts)->everyThirtyMinutes()->withoutOverlapping();
Schedule::job(new RefreshConnectionTokens)->dailyAt('02:00')->withoutOverlapping();
Schedule::job(new ExpireSubscriptions)->dailyAt('03:00')->withoutOverlapping();
Schedule::job(new ResetMonthlyAiCredits)->monthlyOn(1, '00:10')->withoutOverlapping();
