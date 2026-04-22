<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AI Importer: dispatche les imports Lunar programmés (scheduled_at <= now, status=parsed)
Schedule::command('ai-importer:run-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Shipping: interroge l'API Suivi La Poste pour tous les envois non terminaux
Schedule::command('shipping:poll-tracking')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground();
