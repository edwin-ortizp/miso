<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tareas programadas (CHG-067). El servidor solo ejecuta `php artisan schedule:run`
 * (ver docs/DEPLOY.md). Corren en cada llamada del cron, sea cada minuto o cada 15:
 * todas son idempotentes dentro de su ventana, así que una corrida extra no repite nada.
 */
Schedule::command('notifications:generate')->everyMinute()->withoutOverlapping(10);
Schedule::command('miso:guardian-nudges')->everyMinute()->withoutOverlapping(10)
    ->when(fn () => (bool) config('game.nudges.enabled'));
Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping(10);
