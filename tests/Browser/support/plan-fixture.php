<?php

use App\Actions\RecordPlanVisit;
use App\Actions\SavePlan;
use App\Models\Plan;
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
