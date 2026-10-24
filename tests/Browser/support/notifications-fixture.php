<?php

use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\CreateNotification;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/notifications-browser.sqlite')) {
    throw new RuntimeException('Dedicated notifications browser database required');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'notifications-browser@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$task = Task::create(['title' => 'Tarea de prueba', 'end_date' => today()->addDays(10), 'end_is_date' => true]);
for ($i = 0; $i < 18; $i++) {
    app(CreateNotification::class)->create($user, $task, 'reminder', 'browser-'.$i, 'Aviso de prueba '.($i + 1), 'Un recordatorio de prueba.', now()->subMinute());
}
