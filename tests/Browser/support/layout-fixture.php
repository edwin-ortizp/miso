<?php

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_ends_with($path, '/storage/framework/testing/layout-browser.sqlite')) {
    throw new RuntimeException('Dedicated testing SQLite required.');
}
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
$user = App\Models\User::factory()->create(['email' => 'layout@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
App\Support\DefaultTaskCategories::createFor($user);
App\Models\Task::create(['title' => 'Tarea de prueba de distribución', 'start_date' => today()]);
App\Models\Goal::create(['title' => 'Meta de prueba']);
App\Models\HealthEvent::create(['title' => 'Evento sintético', 'type' => 'symptom', 'event_date' => today(), 'details' => ['body_area' => 'head']]);
foreach (['Correr', 'Ciclismo', 'Tenis'] as $name) {
    App\Models\ExerciseType::create(['name' => $name, 'calories_per_hour' => 600, 'steps_equivalent' => 0]);
}
$habit = App\Models\HabitDefinition::create(['name' => 'Ejercicio de prueba', 'time_of_day' => 'morning']);
App\Models\HabitAction::create(['habit_id' => $habit->id, 'action_key' => 'exercise.log', 'mode' => 'prompt', 'config' => []]);
echo $habit->id;
