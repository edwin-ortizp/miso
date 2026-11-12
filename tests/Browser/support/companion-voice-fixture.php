<?php

use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\Goal;
use App\Models\HabitDefinition;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || $database !== str_replace('\\', '/', storage_path('framework/testing/companion-voice-browser.sqlite'))) {
    throw new RuntimeException('Dedicated companion-voice testing database required.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['name' => 'Cuenta ficticia', 'email' => 'voice@example.test', 'password' => 'browser-test-password']);
User::factory()->create(['name' => 'Cuenta vacía ficticia', 'email' => 'voice-empty@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$species = GameSpecies::create(['number' => 1, 'name' => 'Compañero de prueba', 'line_key' => 'voice', 'stage' => 1, 'rarity' => 'common', 'types' => ['planta'], 'active' => true]);
GameCompanion::create(['species_id' => $species->id, 'line_key' => 'voice', 'first_met_at' => now()]);
$goal = Goal::create(['title' => 'Completar el proyecto personal con un nombre largo, acentos y muchas ideas para revisar con calma', 'status' => 'active']);
HabitDefinition::create(['name' => 'Respirar con calma y preparar el siguiente paso del proyecto personal', 'time_of_day' => 'morning', 'base_time' => '08:00', 'goal_duration' => '60 s']);
HabitDefinition::create(['name' => 'Estirar', 'time_of_day' => 'morning', 'base_time' => '08:10', 'goal_duration' => '20 s']);
HabitDefinition::create(['name' => 'Leer', 'time_of_day' => 'morning', 'base_time' => '08:20']);
echo json_encode(['goal' => $goal->id], JSON_THROW_ON_ERROR);
