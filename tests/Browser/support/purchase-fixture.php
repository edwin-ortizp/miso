<?php

use App\Models\MealInventoryOperation;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\ShoppingItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Meal\InventoryLedger;
use App\Services\Meal\MealInventory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Only used by the isolated purchase browser suite, never the configured database.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (config('database.default') !== 'sqlite' || ! str_ends_with($path, '/storage/framework/testing/purchase-browser.sqlite')) {
    throw new RuntimeException('Browser fixture requires its dedicated SQLite database.');
}
if (($argv[1] ?? '') === '--history-state') {
    $user = User::where('email', 'purchase-browser@example.test')->firstOrFail();
    $document = MealInventoryOperation::where('user_id', $user->id)->where('reason', 'Documento navegador 32')->sole();
    echo json_encode(['id' => $document->id, 'product' => $document->document_lines[0]['shopping_item_id']]);
    exit(0);
}
if (($argv[1] ?? '') === '--inventory-state') {
    $user = User::where('email', 'purchase-browser@example.test')->firstOrFail();
    $width = (int) ($argv[2] ?? 0);
    if (! in_array($width, [375, 768, 1280], true)) {
        throw new RuntimeException('Unexpected inventory fixture width.');
    }
    $product = ShoppingItem::where('user_id', $user->id)->where('name', 'Inventario navegador '.$width)->sole();
    echo json_encode(['stock' => $product->stock, 'documents' => MealInventoryOperation::where('user_id', $user->id)->where('reason', 'Conteo navegador '.$width)->whereNotNull('document_lines')->count()]);
    exit(0);
}
if (($argv[1] ?? '') === '--update-stock') {
    $user = User::where('email', 'purchase-browser@example.test')->firstOrFail();
    $stock = (int) ($argv[2] ?? 123);
    if (! in_array($stock, [123, 456], true)) {
        throw new RuntimeException('Unexpected fixture stock.');
    }
    auth()->login($user);
    $product = ShoppingItem::where('user_id', $user->id)->where('name', 'Pan de prueba')->sole();
    app(InventoryLedger::class)->count($user->id, $product->id, $stock, 'unit');
    exit(0);
}
if (in_array($argv[1] ?? '', ['--setup-race', '--consume-race', '--race-state'], true)) {
    DB::statement('PRAGMA busy_timeout = 10000');
    $user = User::where('email', 'purchase-browser@example.test')->firstOrFail();
    auth()->login($user);
    $inventory = app(MealInventory::class);
    if ($argv[1] === '--setup-race') {
        $product = ShoppingItem::create(['name' => 'Producto concurrencia', 'base_unit' => 'unit', 'stock' => 100, 'status' => 'available']);
        $ids = [];
        foreach (['1900-01-01', '1900-01-02'] as $date) {
            $ids[] = $inventory->plan($user->id, ['date' => $date, 'meal_type' => 'cena', 'items' => [['name' => 'Consumo concurrente', 'ingredients' => [['shopping_item_id' => $product->id, 'quantity' => 75]]]]])['meal_id'];
        }
        echo json_encode(['ids' => $ids]);
    } elseif ($argv[1] === '--consume-race') {
        try {
            $inventory->consume($user->id, (int) ($argv[2] ?? 0));
            echo json_encode(['consumed' => true]);
        } catch (ValidationException $exception) {
            echo json_encode(['consumed' => false, 'error' => implode(' ', $exception->validator->errors()->all())]);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'database is locked')) {
                throw $exception;
            }
            echo json_encode(['consumed' => false, 'error' => 'database is locked']);
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage());
            exit(1);
        }
    } else {
        echo json_encode(['stock' => ShoppingItem::where('name', 'Producto concurrencia')->sole()->stock,
            'consumed' => MealPlanEntry::where('user_id', $user->id)->where('status', 'consumed')->whereDate('date', '<', '1901-01-01')->count()]);
    }
    exit(0);
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = User::factory()->create(['email' => 'purchase-browser@example.test', 'password' => 'browser-test-password']);
auth()->login($user);
$lines = [];
foreach (range(1, 32) as $index) {
    $product = ShoppingItem::create(['name' => 'Kardex producto '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'base_unit' => 'unit', 'stock' => 0, 'status' => 'available']);
    $lines[] = ['shopping_item_id' => $product->id, 'quantity' => 2, 'unit' => 'unit'];
}
app(InventoryLedger::class)->record($user->id, 'purchase', $lines, ['reason' => 'Documento navegador 32', 'channel' => 'web', 'origin' => ['reference' => 'KARDEX-32']]);
foreach ([375, 768, 1280] as $width) {
    app(InventoryLedger::class)->saveProduct($user->id, ['name' => 'Inventario navegador '.$width, 'base_unit' => 'unit', 'stock' => 6, 'status' => 'available']);
}
$milk = ShoppingItem::create(['name' => 'Leche de consumo', 'base_unit' => 'ml', 'stock' => 1000, 'status' => 'available', 'consume_by' => today()->addDays(2)]);
$milk->variants()->create(['content' => 1000, 'is_preferred' => true]);
$cake = ShoppingItem::create(['name' => 'Torta de consumo', 'base_unit' => 'g', 'stock' => 500, 'status' => 'available']);
$recipe = Recipe::create(['name' => 'Sopa de inventario', 'meal_type' => 'cena', 'servings' => 6, 'nutrition' => ['calories' => 100]]);
$recipe->recipeIngredients()->create(['shopping_item_id' => $milk->id, 'quantity' => 600, 'unit' => 'ml']);
$dinner = MealPlanEntry::create(['date' => now()->toDateString(), 'meal_type' => 'cena', 'notes' => 'Cena de prueba', 'calories' => 420]);
$dinner->items()->create(['name' => 'Sopa de prueba', 'calories' => 420, 'position' => 0]);
Store::create(['name' => 'Tienda de prueba']);
foreach (['Pan de prueba', 'Leche de prueba'] as $name) {
    $item = ShoppingItem::create(['name' => $name, 'base_unit' => 'unit', 'stock' => 0, 'status' => 'available', 'next_purchase' => true]);
    $item->variants()->create(['content' => 5, 'packaging' => 'paquete']);
}
echo "Purchase browser fixture ready.\n";
