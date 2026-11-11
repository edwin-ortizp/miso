<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_ends_with($path, '/storage/framework/testing/game-balls-browser.sqlite')) {
    throw new RuntimeException('Dedicated testing SQLite required.');
}
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
$user = App\Models\User::factory()->create(['email' => 'balls@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$types = ['normal', 'planta', 'fuego', 'agua', 'electrico', 'hielo', 'lucha', 'veneno', 'tierra', 'volador', 'psiquico', 'bicho', 'roca', 'fantasma', 'dragon', 'siniestro', 'acero', 'hada'];
foreach ($types as $i => $type) {
    App\Models\Game\GameSpecies::create(['number' => $i + 1, 'name' => 'Compañero '.($i + 1), 'line_key' => 'test-'.$i, 'stage' => 1, 'rarity' => 'common', 'types' => [$type], 'active' => true]);
}
config(['game.bonus_balls_per_week' => 10]);
foreach (['Una pausa de prueba', 'Un hábito de prueba', 'Un objetivo de prueba', 'Un paso de prueba', 'Preparar materiales para la reunión y compartir las conclusiones con el equipo', 'Un momento para mí'] as $i => $label) {
    app(App\Services\Game\RewardGranter::class)->grantBonusBall($user->id, 'browser-'.$i, $label);
}
