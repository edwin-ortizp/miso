<?php

use App\Models\{User, DrinkType};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/water-form-browser.sqlite')) {
    throw new RuntimeException('Requires dedicated water form test database.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'water-form@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
DrinkType::create(['name' => 'Té de prueba', 'icon' => '🍵', 'hydration_factor' => 0.9]);
echo "Water form fixture ready.\n";
