<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AI Importer: tick des 2 phases pilotées par le cron —
//  1. préparation des fichiers en attente (status=pending → parse)
//  2. imports Lunar programmés dus (status=parsed, scheduled_at <= now)
Schedule::command('ai-importer:run-scheduled')
    ->everyTwoMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Shipping: interroge l'API Suivi La Poste pour tous les envois non terminaux
Schedule::command('shipping:poll-tracking')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground();
