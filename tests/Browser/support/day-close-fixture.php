<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace(DIRECTORY_SEPARATOR, '/', (string) config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/day-close-browser.sqlite')) {
    throw new RuntimeException('Dedicated day-close browser database required');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'day-close-browser@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
// CHG-070: dos tareas que vencen hoy (una con título largo) y una de ayer que no debe aparecer.
Task::create(['title' => 'Llamar al banco', 'end_date' => today()->setTime(23, 30)]);
Task::create(['title' => 'Revisar el informe trimestral completo con todos los anexos y comentarios del equipo antes de enviarlo', 'end_date' => today()->setTime(23, 45)]);
Task::create(['title' => 'Tarea de ayer', 'end_date' => today()->subDay()->setTime(10, 0)]);
