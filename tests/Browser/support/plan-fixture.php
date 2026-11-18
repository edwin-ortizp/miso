<?php

use App\Actions\RecordPlanVisit;
use App\Actions\SavePlan;
use App\Models\Plan;
use App\Models\PlanCategory;
use App\Models\PlanTag;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/plan-browser.sqlite')) {
    throw new RuntimeException('Dedicated plan browser SQLite required');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'plan-browser@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$person = Relationship::factory()->for($user)->create(['full_name' => 'Contacto de planes', 'nickname' => 'Contacto']);
$plan = SavePlan::handle(new Plan, ['title' => 'Cena programada', 'type' => 'restaurant', 'category' => 'Gastronomía', 'scheduled_on' => today()->addDay()->toDateString()], null, [$person->id]);
SavePlan::handle(new Plan, ['title' => 'Plan vencido', 'type' => 'activity', 'scheduled_on' => today()->subDay()->toDateString()]);
$done = SavePlan::handle(new Plan, ['title' => 'Plan realizado', 'type' => 'dessert']);
foreach ([1440, 390] as $width) RecordPlanVisit::handle($done, today()->subDay()->toDateString(), collect([$person->id]), 'Comentario de visita '.$width);

$longCircle = \App\Models\Circle::factory()->for($user)->create(['name' => str_repeat('Circulo', 20)]);
$longPlan = Plan::factory()->for($user)->create([
    'title' => 'Evento extenso '.str_repeat('Titulo', 20), 'status' => 'scheduled',
    'scheduled_on' => today()->subDays(3), 'ends_on' => today()->addDays(3),
]);
$longPlan->circles()->attach($longCircle);
Plan::factory()->for($user)->count(12)->create(['status' => 'pending']);

$bundleUser = User::factory()->create(['email' => 'plan-bundle-browser@example.test', 'password' => 'browser-test-password']);
auth()->login($bundleUser);
$bundlePerson = Relationship::factory()->for($bundleUser)->create(['full_name' => 'Contacto de planes']);
\App\Models\Circle::factory()->for($bundleUser)->create(['name' => 'Círculo de prueba']);
SavePlan::handle(new Plan, ['title' => 'Cena programada', 'type' => 'restaurant', 'category' => 'Gastronomía'], null, [$bundlePerson->id]);
PlanTag::create(['name' => 'Etiqueta de prueba']);
for ($index = 1; $index <= 10; $index++) {
    PlanCategory::create(['name' => 'Categoría de prueba '.$index, 'normalized_name' => 'categoría de prueba '.$index]);
}

\App\Models\Country::create(['code' => 'CO', 'name' => 'Colombia']);
for ($index = 1; $index <= 25; $index++) {
    \App\Models\City::create(['id' => $index, 'country_code' => 'CO', 'name' => 'Ciudad '.$index, 'normalized_name' => 'ciudad '.$index, 'population' => 1000]);
}
\App\Models\City::create(['id' => 3671916, 'country_code' => 'CO', 'name' => 'Popayán', 'normalized_name' => 'popayan', 'region' => 'Cauca']);
foreach ([1440, 390] as $width) {
    Plan::factory()->for($bundleUser)->create([
        'title' => 'Detalle de prueba '.$width,
        'city' => 'Popayan anterior',
        'address' => 'Calle 1 #2'.($width === 390 ? ', '.str_repeat('Referencia', 10) : ''),
        'scheduled_on' => today()->toDateString(),
    ]);
}
