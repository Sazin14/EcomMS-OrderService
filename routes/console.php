<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run with:  php artisan schedule:work   (development)   or a cron entry "* * * * * php artisan schedule:run" (production)
Schedule::command('orders:cancel-expired')->everyFiveMinutes()->withoutOverlapping();   // safety net for a lost "reservation expired" event
Schedule::command('orders:sync-preorders')->hourly()->withoutOverlapping();             // safety net for a lost "preorder allocated" event
Schedule::command('orders:complete-delivered')->daily()->withoutOverlapping();          // delivered + return window over -> completed
Schedule::command('outbox:prune')->daily();
