<?php

namespace Tests\Feature\Meal;

use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\IngredientMergeService;
use App\Services\Meal\InventoryLedger;
use App\Services\Meal\MealInventory;
use App\Services\Meal\PurchaseRecorder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// The same file is a guarded child-process entry point, without a second test harness.
if (($argv[1] ?? '') === '--inventory-worker') {
    require __DIR__.'/../../../vendor/autoload.php';
    $database = $argv[2] ?? '';
    $port = (int) ($argv[3] ?? 0);
    if (! preg_match('/^miso_inventory_test_[a-z0-9_]+$/D', $database) || $port < 1024 || $port === 3306) {
        throw new \RuntimeException('An isolated inventory database and nonstandard loopback port are required.');
    }
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
    $app = require __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'mysql', 'database.connections.mysql.url' => null,
        'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => $port,
        'database.connections.mysql.database' => $database, 'database.connections.mysql.username' => 'root',
        'database.connections.mysql.password' => '', 'database.connections.mysql.unix_socket' => '']);
    DB::purge('mysql');
    $input = json_decode(base64_decode($argv[4]), true, flags: JSON_THROW_ON_ERROR);
    $barrier = $input['barrier'];
    if (realpath(dirname($barrier)) !== realpath(storage_path('framework/testing')) || ! str_starts_with(basename($barrier), 'inventory-barrier-')) {
        throw new \RuntimeException('Invalid barrier path.');
    }
    touch($barrier.'.'.$input['worker'].'.ready');
    $deadline = microtime(true) + 30;
    while (! file_exists($barrier)) {
        clearstatcache(true, $barrier);
        if (microtime(true) > $deadline) {
            throw new \RuntimeException('Timed out waiting for the inventory barrier.');
        }
        usleep(10000);
    }
    auth()->login(User::withoutGlobalScopes()->findOrFail($input['user_id']));
    try {
        $ledger = app(InventoryLedger::class);
        match ($input['action']) {
            'purchase' => app(PurchaseRecorder::class)->record($ledger->resolveProduct($input['user_id'], $input['product_id']), null, $input['quantity']),
            'consume' => app(MealInventory::class)->consume($input['user_id'], $input['meal_id']),
            'count' => $ledger->count($input['user_id'], $input['product_id'], $input['quantity'], 'unit'),
            'merge' => app(IngredientMergeService::class)->merge($ledger->resolveProduct($input['user_id'], $input['product_id']), $ledger->resolveProduct($input['user_id'], $input['target_id']), null),
        };
        echo json_encode(['ok' => true]);
    } catch (ValidationException $exception) {
        echo json_encode(['ok' => false, 'errors' => $exception->validator->errors()->all()]);
    }
    exit;
}

class InventoryLedgerConcurrencyTest extends TestCase
{
    private int $mealNumber = 0;

    public function test_disposable_mysql_migration_and_concurrent_sources_preserve_the_ledger(): void
    {
        $database = getenv('INVENTORY_MYSQL_DATABASE') ?: '';
        $port = (int) getenv('INVENTORY_MYSQL_PORT');
        if ($database === '') {
            $this->markTestSkipped('Explicit disposable MySQL integration run required.');
        }
        $this->assertMatchesRegularExpression('/^miso_inventory_test_[a-z0-9_]+$/D', $database);
        $this->assertGreaterThanOrEqual(1024, $port);
        $this->assertNotSame(3306, $port);
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => $port,
            'database.connections.mysql.database' => $database, 'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '', 'database.connections.mysql.unix_socket' => '']);
        DB::purge('mysql');
        $this->assertSame($database, DB::connection()->getDatabaseName());
        Artisan::call('migrate', ['--force' => true]);
        $user = User::factory()->create();
        auth()->login($user);

        // Round trip only the new migration, retaining legacy stock/movements in the disposable DB.
        $legacyProduct = $this->product($user, 6);
        $legacyOperation = MealInventoryOperation::create(['user_id' => $user->id, 'operation_key' => (string) Str::uuid(), 'action' => 'consume', 'fingerprint' => str_repeat('a', 64)]);
        $legacy = $legacyOperation->movements()->create(['user_id' => $user->id, 'shopping_item_id' => $legacyProduct->id, 'name' => 'Histórico', 'unit' => 'unit', 'delta' => -2]);
        $migration = require base_path('database/migrations/2026_11_02_000001_extend_inventory_ledger.php');
        $migration->down();
        $this->assertSame(6.0, $legacyProduct->fresh()->stock);
        $this->assertSame(-2.0, $legacy->fresh()->delta);
        $migration->up();
        $this->assertNull($legacy->fresh()->balance_after);

        $purchaseProduct = $this->product($user, 100);
        $mealId = $this->meal($user, $purchaseProduct);
        $results = $this->race($database, $port, $user, [
            ['action' => 'purchase', 'product_id' => $purchaseProduct->id, 'quantity' => 20],
            ['action' => 'consume', 'meal_id' => $mealId],
        ]);
        $this->assertSame([true, true], array_column($results, 'ok'));
        $this->assertSame(45.0, $purchaseProduct->fresh()->stock);
        $this->assertChain($purchaseProduct);

        $countProduct = $this->product($user, 100);
        $results = $this->race($database, $port, $user, [
            ['action' => 'count', 'product_id' => $countProduct->id, 'quantity' => 50],
            ['action' => 'consume', 'meal_id' => $this->meal($user, $countProduct)],
        ]);
        $this->assertTrue($results[0]['ok']);
        if (! $results[1]['ok']) {
            $this->assertStringContainsString('faltan', implode(' ', $results[1]['errors']));
        }
        $this->assertSame(50.0, $countProduct->fresh()->stock);
        $this->assertChain($countProduct);

        $source = $this->product($user, 40);
        $target = $this->product($user, 5);
        $results = $this->race($database, $port, $user, [
            ['action' => 'merge', 'product_id' => $source->id, 'target_id' => $target->id],
            ['action' => 'purchase', 'product_id' => $source->id, 'quantity' => 10],
        ]);
        $this->assertSame([true, true], array_column($results, 'ok'));
        $this->assertSame(55.0, $target->fresh()->stock);
        $this->assertNull(ShoppingItem::withoutGlobalScopes()->find($source->id));
        $this->assertChain($target);
        $sourceRows = MealInventoryMovement::where('historical_product_id', $source->id)->orderBy('sequence')->get();
        $this->assertSame('0.000', $sourceRows->last()->balance_after);
    }

    private function product(User $user, int $stock): ShoppingItem
    {
        $product = new ShoppingItem(['name' => 'Concurrencia '.Str::random(12), 'base_unit' => 'unit', 'stock' => $stock, 'status' => 'available']);
        $product->user_id = $user->id;
        $product->save();

        return $product;
    }

    private function meal(User $user, ShoppingItem $product): int
    {
        return app(MealInventory::class)->plan($user->id, ['date' => today()->addDays($this->mealNumber++)->toDateString(), 'meal_type' => 'cena',
            'items' => [['name' => 'Consumo concurrente', 'ingredients' => [['shopping_item_id' => $product->id, 'quantity' => 75]]]]])['meal_id'];
    }

    private function assertChain(ShoppingItem $product): void
    {
        $rows = MealInventoryMovement::where('historical_product_id', $product->id)->orderBy('sequence')->get();
        $this->assertSame(range(1, $rows->count()), $rows->pluck('sequence')->all());
        $previous = '0.000';
        foreach ($rows as $row) {
            $this->assertSame($previous, $row->balance_before);
            $this->assertGreaterThanOrEqual(0, $row->balance_after);
            $previous = $row->balance_after;
        }
        $this->assertSame(number_format($product->fresh()->stock, 3, '.', ''), $previous);
    }

    private function race(string $database, int $port, User $user, array $actions): array
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $barrier = $directory.'/inventory-barrier-'.Str::uuid();
        $children = [];
        try {
            foreach ($actions as $index => $action) {
                $payload = base64_encode(json_encode($action + ['user_id' => $user->id, 'barrier' => $barrier, 'worker' => $index], JSON_THROW_ON_ERROR));
                $process = proc_open([PHP_BINARY, __FILE__, '--inventory-worker', $database, (string) $port, $payload],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), options: ['bypass_shell' => true, 'create_process_group' => true]);
                if (! is_resource($process)) {
                    $this->fail('Could not start inventory worker.');
                }
                fclose($pipes[0]);
                $children[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 30;
            while (! file_exists($barrier.'.0.ready') || ! file_exists($barrier.'.1.ready')) {
                clearstatcache();
                if (microtime(true) > $deadline) {
                    $this->fail('Inventory workers did not reach the start barrier.');
                }
                usleep(10000);
            }
            touch($barrier);
            $results = [];
            foreach ($children as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                $this->assertSame(0, $code, $errors ?: $output);
                $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($children as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    foreach ($pipes as $pipe) {
                        if (is_resource($pipe)) {
                            fclose($pipe);
                        }
                    }
                    proc_close($process);
                }
            }
            foreach ([$barrier, $barrier.'.0.ready', $barrier.'.1.ready'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
