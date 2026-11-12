<?php

use App\Models\Goal;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Insights\WeeklyInsights;
use App\Services\Notifications\CreateNotification;
use App\Services\Notifications\Push\GuardianNudgePlanner;
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
$plan = Plan::create(['title' => 'Visitar el museo de historia', 'type' => 'museum', 'status' => 'scheduled', 'scheduled_on' => today()->addDay()]);
$goal = Goal::create(['title' => 'Aprender francés', 'status' => 'active', 'due_date' => today()->addMonth()]);
$person = Relationship::create(['full_name' => 'Laura Martínez', 'category' => 'friend', 'birthday_month' => (int) now()->format('n'), 'birthday_day' => (int) now()->format('j')]);
$longTask = Task::create(['title' => 'Preparar la propuesta comercial y revisar todos los acuerdos del proyecto antes de enviarlos al equipo', 'end_date' => today()->subDay(), 'end_is_date' => true]);
app(CreateNotification::class)->create($user, $longTask, 'task_due', today()->subDay()->format('Y-m-d').' 09:00:00', 'Tarea para mañana', 'Tienes una tarea que vence mañana.', now()->subSeconds(6));
app(CreateNotification::class)->create($user, $plan, 'plan_due', today()->addDay()->format('Y-m-d').' 09:00:00', 'Plan para mañana', 'Tienes un plan.', now()->subSeconds(5));
app(CreateNotification::class)->create($user, $goal, 'reminder', 'goal-browser', 'Recordatorio', 'Tienes un recordatorio.', now()->subSeconds(4));
app(CreateNotification::class)->create($user, $person, 'birthday', today()->toDateString(), 'Cumpleaños de hoy', 'Una persona cumple años.', now()->subSeconds(3));
foreach ([
    ['source_type' => GuardianNudgePlanner::SOURCE, 'source_id' => GuardianNudgePlanner::sourceId('tasks'), 'type' => 'guardian_nudge', 'title' => 'Tu guardián de tareas', 'body' => 'Revisa tus tareas pendientes.', 'occurrence_key' => 'browser-guardian'],
    ['source_type' => WeeklyInsights::SOURCE, 'source_id' => WeeklyInsights::sourceId(), 'type' => 'weekly_insights', 'title' => 'Tu resumen de la semana', 'body' => 'Consulta tus avances en Estadísticas.', 'occurrence_key' => today()->startOfWeek()->toDateString()],
] as $attributes) {
    UserNotification::create($attributes + ['user_id' => $user->id, 'scheduled_for' => now()->subSecond()]);
}
