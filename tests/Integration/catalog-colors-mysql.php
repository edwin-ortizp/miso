<?php

// Validates only the color migration in a newly created, disposable local database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$connection = config('database.connections.mysql');
if (! in_array($connection['host'], ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Local MySQL required.');
}
$name = 'miso_catalog_colors_test_'.bin2hex(random_bytes(6));
$connection['url'] = null;
$connection['database'] = null;
config(['database.connections.catalog_admin' => $connection]);
$admin = DB::connection('catalog_admin');
$admin->statement("CREATE DATABASE `$name`");
try {
    $connection['database'] = $name;
    config(['database.connections.catalog_test' => $connection, 'database.default' => 'catalog_test']);
    foreach (['task_categories', 'task_statuses'] as $table) {
        Schema::create($table, function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('name');
        });
        DB::table($table)->insert(['name' => 'Existing record']);
    }
    $migration = require __DIR__.'/../../database/migrations/2026_10_26_000001_add_colors_to_task_catalogs.php';
    $migration->up();
    foreach (['task_categories', 'task_statuses'] as $table) {
        $row = DB::table($table)->first();
        if ($row->name !== 'Existing record' || $row->color !== null) {
            throw new RuntimeException('Existing row changed.');
        }
        DB::table($table)->update(['color' => 'violet']);
        if (DB::table($table)->value('color') !== 'violet') {
            throw new RuntimeException('Color did not persist.');
        }
    }
    $migration->down();
    foreach (['task_categories', 'task_statuses'] as $table) {
        if (Schema::hasColumn($table, 'color') || DB::table($table)->value('name') !== 'Existing record') {
            throw new RuntimeException('Rollback changed existing data.');
        }
    }
    $migration->up();
    echo "MySQL: migration, null defaults, persistence, rollback and reapply passed.\n";
} finally {
    DB::purge('catalog_test');
    $admin->statement("DROP DATABASE `$name`");
}
