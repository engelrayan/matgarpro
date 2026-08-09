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
