<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ═══════════════════════════════════════════════════════════════════════════
// HISTORIAN — Agregación diaria automática
// Corre cada día a las 00:05 AM para agregar el día anterior
// ═══════════════════════════════════════════════════════════════════════════
Schedule::command('historian:aggregate --days=1')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();
