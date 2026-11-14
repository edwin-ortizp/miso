<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$source = config('database.connections.mysql');
$source['database'] = null;
$source['url'] = null;
config(['database.connections.generation_admin' => $source]);
$admin = DB::connection('generation_admin');
$name = 'miso_chg104_disposable_'.bin2hex(random_bytes(4));
if (! preg_match('/^miso_chg104_disposable_[a-f0-9]{8}$/', $name)) {
    throw new RuntimeException('Unsafe name');
}
$admin->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $source['database'] = $name;
    config(['database.connections.generation_test' => $source, 'database.default' => 'generation_test']);
    Schema::create('game_species', function ($t) {
        $t->id();
        $t->unsignedInteger('number')->unique();
        $t->string('name');
        $t->text('description')->nullable();
    });
    $rows = json_decode(file_get_contents(base_path('pokemon.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach (array_chunk($rows, 100) as $chunk) {
        DB::table('game_species')->insert(array_map(fn ($s) => ['number' => $s['number'], 'name' => $s['name'], 'description' => 'preserve-'.$s['number']], $chunk));
    }
    $migration = require base_path('database/migrations/2026_11_14_000001_add_generation_to_game_species.php');
    $migration->up();
    foreach ($rows as $s) {
        $actual = DB::table('game_species')->where('number', $s['number'])->first();
        if ((int) $actual->generation !== $s['generation'] || $actual->description !== 'preserve-'.$s['number']) {
            throw new RuntimeException('Mapping changed data');
        }
    }
    echo json_encode(['mapped' => count($rows), 'counts' => DB::table('game_species')->selectRaw('generation, count(*) as total')->groupBy('generation')->pluck('total', 'generation')->all(), 'descriptions_preserved' => true]).PHP_EOL;
    $migration->down();
    if (Schema::hasColumn('game_species', 'generation')) {
        throw new RuntimeException('Rollback failed');
    }
    DB::table('game_species')->where('number', 1)->update(['name' => 'Mismatch']);
    $blocked = false;
    try {
        $migration->up();
    } catch (RuntimeException $e) {
        $blocked = true;
    }
    if (! $blocked || Schema::hasColumn('game_species', 'generation')) {
        throw new RuntimeException('Validation failed to precede DDL');
    }
    echo 'Rollback and pre-DDL mismatch rejection passed'.PHP_EOL;
} finally {
    DB::disconnect('generation_test');
    $admin->statement('DROP DATABASE `'.$name.'`');
    echo 'Disposable database removed; original database untouched'.PHP_EOL;
}
