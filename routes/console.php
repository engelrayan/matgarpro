<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| A merchant pastes their DNS records and closes the tab. This is what turns
| their store on a few minutes later without them touching anything — the
| command itself only looks up domains whose recheck interval has elapsed, so
| running it every minute is cheap.
*/
Schedule::command('domains:verify')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
| The safety net under automatic certificate issuance.
|
| A certificate is normally requested the instant a domain verifies. This
| sweeps up what fell through: a job lost to a worker restart, a domain whose
| backoff has now elapsed, and — the first time it runs — every shop that was
| already live before this feature existed.
|
| Hourly, not every minute: the command's whole job is to retry things that
| failed, and Let's Encrypt counts failures per hostname per hour.
*/
Schedule::command('domains:certify')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
