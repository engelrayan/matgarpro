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

/*
| AI drafts are only useful while the merchant is on the form; this clears
| the month-old ones, and the photos of any whose job never ran.
*/
Schedule::command('model:prune', ['--model' => [\App\Models\AiGeneration::class]])
    ->daily()
    ->withoutOverlapping();

/*
| WhatsApp follow-up on the platform number: one reminder for a customer who
| has not tapped "تأكيد الطلب", then the call list. Every fifteen minutes so a
| reminder lands close to its hour; the command does nothing at night.
*/
Schedule::command('whatsapp:remind')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
| Heartbeats for the admin's health page: the scheduler says it ran, and each
| queue gets a job that says a worker picked it up. Without them a stopped
| worker has no symptom until a merchant asks why nothing was sent.
*/
Schedule::call(fn () => \App\Support\Heartbeat::beat(\App\Support\Heartbeat::SCHEDULER))
    ->everyMinute()
    ->name('heartbeat:scheduler');

foreach (array_unique(['default', (string) config('ai.queue')]) as $queue) {
    Schedule::job(new \App\Jobs\QueueHeartbeat($queue), $queue)
        ->everyMinute()
        ->name("heartbeat:{$queue}");
}
