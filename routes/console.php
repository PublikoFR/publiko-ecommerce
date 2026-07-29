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

// Worker de file d'attente. L'hébergement est mutualisé : pas de Supervisor ni de
// systemd pour maintenir un `queue:work` résident, on s'appuie donc sur le cron
// `schedule:run` déjà en place. Chaque minute, un worker vide la file puis s'arrête
// (`--stop-when-empty`), avec un plafond sous la minute pour ne pas chevaucher le
// tick suivant. Sans ce worker, un `QUEUE_CONNECTION=database` empilerait les jobs
// sans jamais les exécuter — silencieusement.
// No-op tant que la connexion est `sync` : les jobs s'exécutent déjà en ligne.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Shipping: interroge l'API Suivi La Poste pour tous les envois non terminaux
Schedule::command('shipping:poll-tracking')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground();
