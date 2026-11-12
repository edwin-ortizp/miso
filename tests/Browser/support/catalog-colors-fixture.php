<?php

use App\Models\{User, Task, Plan, PlanCategory, VehicleExpenseCategory};
use App\Actions\ManageTaskCategories;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/catalog-colors-browser.sqlite')) {
    throw new RuntimeException('Requires dedicated catalog colors test database.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'catalog-colors@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$category = app(ManageTaskCategories::class)->create($user, 'Proyecto visual', 'bi-star', color: 'violet');
Task::create(['title' => 'Tarea visual', 'category' => $category->key, 'start_date' => today()]);
$planCategory = PlanCategory::create(['user_id' => $user->id, 'name' => 'Paseos visuales', 'icon' => 'bi-tree']);
$plan = Plan::factory()->for($user)->create(['title' => 'Plan visual', 'category' => $planCategory->name, 'plan_category_id' => $planCategory->id]);
$vehicle = $user->vehicles()->create(['name' => 'Auto visual', 'vehicle_type' => 'automovil', 'power_source' => 'gasolina', 'usage_unit' => 'km']);
$expenseCategory = VehicleExpenseCategory::create(['name' => 'Gasto visual', 'icon' => 'bi-shield']);
$vehicle->expenses()->create(['vehicle_expense_category_id' => $expenseCategory->id, 'spent_on' => today(), 'amount' => 10000, 'description' => 'Descripción separada']);
echo json_encode(['plan' => $plan->id, 'vehicle' => $vehicle->id]);
