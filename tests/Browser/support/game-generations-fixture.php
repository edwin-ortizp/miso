<?php

use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\CompanionProgress;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_ends_with($path, '/storage/framework/testing/game-generations-browser.sqlite')) {
    throw new RuntimeException('Dedicated testing SQLite required.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('game:import-species', ['file' => base_path('pokemon.json')]);
$user = User::factory()->create(['email' => 'generations@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$species = GameSpecies::where('number', 25)->firstOrFail();
GameCompanion::create(['user_id' => $user->id, 'species_id' => $species->id, 'line_key' => $species->line_key, 'first_met_at' => now(), 'xp' => 200, 'level' => 5]);
app(CompanionProgress::class)->discover($user->id, $species->id);
