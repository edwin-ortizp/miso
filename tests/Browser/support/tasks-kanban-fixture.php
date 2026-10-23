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
if (config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/tasks-kanban-browser.sqlite')) {
    throw new RuntimeException('Dedicated tasks settings browser SQLite required');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'tasks-kanban-browser@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
TaskCategory::create(['key' => 'trabajo', 'name' => 'Trabajo de prueba']);
Task::create(['title' => 'Kanban browser task', 'category' => 'trabajo', 'start_date' => now()->startOfWeek()->addDays(2)->setTime(9, 0), 'end_date' => now()->startOfWeek()->addDays(2)->setTime(10, 0)]);
