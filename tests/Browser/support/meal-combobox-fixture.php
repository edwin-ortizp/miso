<?php

use App\Models\{User, ShoppingItem, Recipe, MealPreparation};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/meal-combobox-browser.sqlite')) {
    throw new RuntimeException('Requires dedicated meal combobox test database.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'meal-combobox@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$product = ShoppingItem::create(['name' => 'Plátano de prueba', 'base_unit' => 'g', 'stock' => 1000, 'status' => 'available']);
$recipe = Recipe::create(['name' => 'Sopa de prueba', 'meal_type' => 'desayuno', 'favorite' => true, 'servings' => 1, 'nutrition' => ['calories' => 100]]);
$guiso = Recipe::create(['name' => 'Guiso de prueba', 'meal_type' => 'almuerzo', 'servings' => 1, 'nutrition' => ['calories' => 200]]);
foreach ([$recipe, $guiso] as $dish) {
    $dish->recipeIngredients()->create(['shopping_item_id' => $product->id, 'quantity' => 1, 'unit' => 'g']);
}
app(\App\Services\Meal\MealInventory::class)->cook($user->id, ['recipe_id' => $guiso->id, 'portions' => 10]);
if (MealPreparation::count() !== 1) {
    throw new RuntimeException('Preparation fixture was not created.');
}

echo "Meal combobox fixture ready.\n";
