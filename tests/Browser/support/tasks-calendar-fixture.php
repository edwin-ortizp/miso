<?php

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Models\User;
use App\Support\DefaultTaskCategories;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
$port = (int) (getenv('MISO_TASK_CALENDAR_TEST_PORT') ?: 8033);
$expected = '/storage/framework/testing/tasks-calendar-browser-'.$port.'.sqlite';
if (config('database.default') !== 'sqlite'
    || (! str_ends_with($path, $expected) && ! str_ends_with($path, '/storage/framework/testing/tasks-calendar-browser.sqlite'))) {
    throw new RuntimeException('Dedicated tasks settings browser SQLite required');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'tasks-calendar-browser@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
Task::create(['title' => 'Calendar browser task', 'start_date' => now()->startOfWeek()->addDays(2)->setTime(9, 0), 'end_date' => now()->startOfWeek()->addDays(2)->setTime(10, 0), 'start_is_date' => false, 'end_is_date' => false]);
Task::create(['title' => 'Calendar recurring task', 'start_date' => today(), 'start_is_date' => true, 'is_recurrent' => true, 'recurrence_interval_days' => 7]);
Task::create([
    'title' => 'Calendar completed task with a long title that should wrap across several lines on a narrow screen',
    'start_date' => today()->startOfWeek()->addDays(today()->dayOfWeekIso % 7),
    'start_is_date' => true,
    'completed' => true,
]);
\App\Models\Plan::factory()->create(['user_id' => $user->id, 'title' => 'Calendar browser plan', 'scheduled_on' => now()->startOfWeek(), 'ends_on' => now()->endOfWeek()]);
\App\Models\Relationship::factory()->withBirthday((int) now()->month, (int) now()->day, 1990)->create(['user_id' => $user->id, 'full_name' => 'Calendar birthday person']);

$meal = \App\Models\MealPlanEntry::create(['date' => today()->toDateString(), 'meal_type' => 'desayuno', 'status' => 'planned']);
$meal->items()->create(['name' => 'Calendar meal rice', 'position' => 0]);
