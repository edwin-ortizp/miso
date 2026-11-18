<?php

use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Learning\LearningRecords;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== storage_path('framework/testing/learning-browser.sqlite')) {
    throw new RuntimeException('A dedicated Learning testing database is required.');
}
config(['database.connections.sqlite.synchronous' => 'OFF', 'database.connections.sqlite.journal_mode' => 'MEMORY', 'hashing.bcrypt.rounds' => 4]);
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['name' => 'Aprendiz ficticio', 'email' => 'learning@example.test', 'password' => 'learning-test-password']);
$empty = User::factory()->create(['name' => 'Aprendiz sin registros', 'email' => 'learning-empty@example.test', 'password' => 'learning-test-password']);
auth()->login($user);
$species = GameSpecies::create(['number' => 1, 'name' => 'Compañero de prueba', 'line_key' => 'learning', 'stage' => 1, 'rarity' => 'common', 'types' => ['psiquico'], 'active' => true]);
GameCompanion::create(['species_id' => $species->id, 'line_key' => 'learning', 'first_met_at' => now()]);
$service = app(LearningRecords::class);
$degree = $service->saveFormation($user->id, null, ['title' => 'Ingeniería de Sistemas', 'type' => 'degree', 'status' => 'in_progress', 'institution' => 'UNAL']);
$period = $service->saveUnit($user->id, $degree->id, null, null, 'period', ['title' => 'Semestre 1', 'status' => 'pending']);
$subject = $service->saveUnit($user->id, $degree->id, null, $period->id, 'subject', ['title' => 'Cálculo diferencial', 'status' => 'pending', 'credits' => 3]);
$service->saveUnit($user->id, $degree->id, null, null, 'subject', ['title' => 'Introducción a la ingeniería', 'status' => 'pending']);
$service->pin($user->id, 'unit', $subject->id, true);
$service->saveFormation($user->id, null, ['title' => 'Ruta personal con un nombre muy largo para comprobar su lectura y las acciones disponibles en pantallas pequeñas', 'type' => 'personal', 'status' => 'pending']);
echo json_encode(['degree' => $degree->id, 'period' => $period->id, 'subject' => $subject->id], JSON_THROW_ON_ERROR);
