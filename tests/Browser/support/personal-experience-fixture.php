<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || $database !== str_replace('\\', '/', storage_path('framework/testing/personal-experience-browser.sqlite'))) {
    throw new RuntimeException('Dedicated personal-experience testing database required.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
echo json_encode(['ready' => true], JSON_THROW_ON_ERROR);
