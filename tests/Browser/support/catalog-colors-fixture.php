<?php

use App\Actions\ManageTaskCategories;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_ends_with($path, '/storage/framework/testing/catalog-colors-browser.sqlite')) {
    throw new RuntimeException('Dedicated catalog color testing SQLite required.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'catalog@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$category = app(ManageTaskCategories::class)->create($user, 'Proyecto de colores');
Task::create(['title' => 'Tarea de colores', 'category' => $category->key, 'start_date' => today()]);
