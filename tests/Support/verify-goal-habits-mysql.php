<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{DB, Schema};

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = config('database.connections.mysql');
$source['database'] = null;
$source['url'] = null;
config(['database.connections.goals_admin' => $source]);
$admin = DB::connection('goals_admin');
$name = 'miso_chg111_disposable_'.bin2hex(random_bytes(4));
if (! preg_match('/^miso_chg111_disposable_[a-f0-9]{8}$/', $name)) throw new RuntimeException('Unsafe name');
$admin->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $source['database'] = $name;
    config(['database.connections.goals_test' => $source, 'database.default' => 'goals_test']);
    Schema::create('users', function ($t) { $t->id(); });
    Schema::create('goals', function ($t) { $t->uuid('id')->primary(); $t->unsignedBigInteger('user_id'); $t->unique(['id', 'user_id']); });
    Schema::create('habit_definitions', function ($t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->unique(['id', 'user_id']); });
    $migration = require base_path('database/migrations/2026_11_15_000001_create_goal_habits_table.php');
    $migration->up();
    $goal = (string) Illuminate\Support\Str::uuid();
    DB::table('users')->insert(['id' => 1]);
    DB::table('goals')->insert(['id' => $goal, 'user_id' => 1]);
    DB::table('habit_definitions')->insert(['id' => 1, 'user_id' => 1]);
    $link = ['user_id' => 1, 'goal_id' => $goal, 'habit_id' => 1];
    DB::table('goal_habits')->insert($link);
    $duplicateBlocked = false;
    try { DB::table('goal_habits')->insert($link); } catch (Illuminate\Database\QueryException $e) { $duplicateBlocked = $e->getCode() === '23000'; }
    if (!$duplicateBlocked) throw new RuntimeException('Unique pair not enforced');
    DB::table('users')->insert(['id' => 2]);
    $foreignGoal = (string) Illuminate\Support\Str::uuid();
    DB::table('goals')->insert(['id' => $foreignGoal, 'user_id' => 2]);
    $ownershipBlocked = false;
    try { DB::table('goal_habits')->insert(['user_id' => 1, 'goal_id' => $foreignGoal, 'habit_id' => 1]); }
    catch (Illuminate\Database\QueryException $e) { $ownershipBlocked = $e->getCode() === '23000'; }
    if (!$ownershipBlocked) throw new RuntimeException('Ownership FK not enforced');
    DB::table('goals')->where('id', $goal)->delete();
    if (DB::table('goal_habits')->count() || DB::table('habit_definitions')->count() !== 1) throw new RuntimeException('Cascade changed source');
    $migration->down();
    if (Schema::hasTable('goal_habits')) throw new RuntimeException('Rollback failed');
    echo 'MySQL: migration, unique pair, cascade and rollback passed'.PHP_EOL;
} finally {
    DB::disconnect('goals_test');
    $admin->statement('DROP DATABASE `'.$name.'`');
    echo 'Disposable database removed; original database untouched'.PHP_EOL;
}
