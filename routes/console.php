<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Visit records are pruned to the configured retention window so the table
// cannot grow without bound on a busy site.
Schedule::command('visitors:prune')->dailyAt('03:17');

// Engagement figures are read back from the platforms server-side, on a
// schedule — never from a visitor's browser. Hourly is well inside every
// provider's rate limit and keeps the cards close enough to live.
Schedule::command('social:sync')->hourlyAt(23)->withoutOverlapping();
