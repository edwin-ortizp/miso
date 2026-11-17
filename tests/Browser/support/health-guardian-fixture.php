<?php

use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || $database !== str_replace('\\', '/', storage_path('framework/testing/health-guardian-browser.sqlite'))) {
    throw new RuntimeException('Dedicated health-guardian testing database required.');
}
// This database is disposable; avoid disk sync on every migration statement.
config(['database.connections.sqlite.synchronous' => 'OFF', 'database.connections.sqlite.journal_mode' => 'MEMORY']);
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['name' => 'Cuenta ficticia', 'email' => 'voice@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$species = GameSpecies::create(['number' => 1, 'name' => 'Compañero de prueba', 'line_key' => 'voice', 'stage' => 1, 'rarity' => 'common', 'types' => ['planta'], 'active' => true]);
GameCompanion::create(['species_id' => $species->id, 'line_key' => 'voice', 'first_met_at' => now()]);
\App\Models\HealthMeasurement::create(['type' => 'weight', 'value' => 70, 'unit' => 'kg', 'measured_at' => now()->subDays(50), 'source' => 'app']);
echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
