<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_ends_with($path, '/storage/framework/testing/ui-density-browser.sqlite')) {
    throw new RuntimeException('Dedicated UI density testing SQLite required.');
}
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
$user = App\Models\User::factory()->create(['full_name' => 'Cuenta de prueba', 'email' => 'density@example.test', 'password' => 'browser-test-password', 'current_weight_kg' => 75, 'height_cm' => 175, 'birth_date' => '1988-04-10', 'activity_level' => 'moderate']);
App\Models\User::factory()->create(['full_name' => 'Cuenta sin datos', 'email' => 'density-empty@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$species = App\Models\Game\GameSpecies::create(['number' => 1, 'name' => 'Compañero de prueba', 'line_key' => 'density', 'stage' => 1, 'rarity' => 'common', 'types' => ['electrico'], 'active' => true]);
App\Models\Game\GameCompanion::create(['species_id' => $species->id, 'line_key' => 'density', 'level' => 3, 'first_met_at' => now()]);
foreach (range(1, 9) as $i) {
    $start = now()->startOfDay()->addHours(7)->addMinutes($i * 35);
    App\Models\PomodoroSession::create(['date' => today()->toDateString(), 'start_time' => ['timestamp' => $start->timestamp], 'end_time' => ['timestamp' => $start->copy()->addMinutes(25)->timestamp], 'duration' => 1500, 'completed' => true, 'description' => $i === 1 ? str_repeat('Preparar los materiales y compartir las conclusiones del proyecto. ', 4) : 'Sesión de prueba '.$i]);
}
App\Models\PushSubscription::create(['user_id' => $user->id, 'endpoint' => 'https://example.invalid/density-push', 'public_key' => 'synthetic-density', 'auth_token' => 'synthetic-density']);
