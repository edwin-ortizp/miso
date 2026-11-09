<?php

namespace Tests\Feature\Meal;

use App\Events\Meal\InventoryMovementRecorded;
use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\InventoryLedger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class InventoryLedgerInitializationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
    }

    private function product(string $stock = '6.000', ?string $unit = 'unit', ?User $owner = null): ShoppingItem
    {
        $product = new ShoppingItem(['name' => 'Producto de prueba', 'base_unit' => $unit, 'stock' => $stock, 'status' => 'available']);
        $product->user_id = ($owner ?? $this->owner)->id;
        $product->save();

        return $product;
    }

    public function test_default_command_only_previews_and_does_not_write_or_dispatch_events(): void
    {
        $product = $this->product('12.345');
        $zero = $this->product('0.000', 'g');
        $before = $product->refresh()->getAttributes();
        $writes = [];
        $events = [];
        DB::listen(function (QueryExecuted $query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        Event::listen(InventoryMovementRecorded::class, function ($event) use (&$events) {
            $events[] = $event;
        });

        $this->artisan('inventory:initialize-ledger')
            ->expectsOutputToContain('Productos: 2; pendientes: 2; inicializados: 0; bloqueados: 0.')
            ->expectsOutputToContain('Previsualización: no se escribió ningún dato.')
            ->assertSuccessful();

        $this->assertSame([], $writes);
        $this->assertSame([], $events);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame(0.0, $zero->fresh()->stock);
        $this->assertDatabaseCount('meal_inventory_operations', 0);
        $this->assertDatabaseCount('meal_inventory_movements', 0);
    }

    public function test_apply_preserves_stock_and_legacy_history_and_is_idempotent_after_later_movements(): void
    {
        $product = $this->product('12.345');
        $zero = $this->product('0.000', 'ml');
        $legacyOperation = MealInventoryOperation::create([
            'user_id' => $this->owner->id, 'operation_key' => 'old', 'action' => 'consume', 'fingerprint' => str_repeat('a', 64),
        ]);
        $legacy = $legacyOperation->movements()->create([
            'user_id' => $this->owner->id, 'shopping_item_id' => $product->id, 'name' => 'Nombre histórico', 'unit' => 'unit', 'delta' => -100,
        ]);
        $beforeLegacy = $legacy->refresh()->getAttributes();
        $beforeProduct = $product->refresh()->getAttributes();

        $this->artisan('inventory:initialize-ledger', ['--apply' => true])->assertSuccessful();

        $this->assertSame($beforeProduct, $product->fresh()->getAttributes());
        $this->assertSame(0.0, $zero->fresh()->stock);
        $this->assertSame($beforeLegacy, $legacy->fresh()->getAttributes());
        $this->assertNull($legacy->fresh()->balance_after);
        $this->assertDatabaseCount('meal_inventory_movements', 3);
        $apertures = MealInventoryOperation::where('document_type', 'opening')->orderBy('id')->get()->map->getAttributes()->all();
        $this->assertCount(2, $apertures);
        $this->assertSame('12.345', MealInventoryMovement::where('historical_product_id', $product->id)->firstOrFail()->balance_after);

        app(InventoryLedger::class)->adjust($this->owner->id, $product->id, '0.001', 'unit', [], 'later');
        $this->artisan('inventory:initialize-ledger', ['--apply' => true])
            ->expectsOutputToContain('Productos: 2; pendientes: 0; inicializados: 2; bloqueados: 0.')
            ->assertSuccessful();
        $this->assertSame(12.346, $product->fresh()->stock);
        $this->assertSame($apertures, MealInventoryOperation::where('document_type', 'opening')->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('meal_inventory_movements', 4);
    }

    public function test_user_selection_is_explicit_and_does_not_depend_on_the_authenticated_scope(): void
    {
        $other = User::factory()->create();
        $mine = $this->product();
        $foreign = $this->product('1.500', 'g', $other);
        $this->actingAs($this->owner);

        $this->artisan('inventory:initialize-ledger', ['--user' => [$other->id], '--apply' => true])
            ->expectsOutputToContain('Productos: 1; pendientes: 1; inicializados: 0; bloqueados: 0.')
            ->assertSuccessful();

        $this->assertSame(0, MealInventoryMovement::withoutGlobalScopes()->where('user_id', $this->owner->id)->count());
        $this->assertSame(1, MealInventoryMovement::withoutGlobalScopes()->where('user_id', $other->id)->count());
        $this->assertSame(6.0, $mine->fresh()->stock);
        $this->assertSame(1.5, ShoppingItem::withoutGlobalScopes()->findOrFail($foreign->id)->stock);
    }

    public function test_missing_unit_blocks_the_entire_selection_without_inventing_a_default(): void
    {
        $valid = $this->product();
        $invalid = $this->product('0.000', null);
        $this->artisan('inventory:initialize-ledger', ['--apply' => true])
            ->expectsOutputToContain('Productos: 2; pendientes: 1; inicializados: 0; bloqueados: 1.')
            ->expectsOutputToContain('Sin cambios: resuelve los productos bloqueados')
            ->assertFailed();

        $this->assertSame(6.0, $valid->fresh()->stock);
        $this->assertNull($invalid->fresh()->base_unit);
        $this->assertDatabaseCount('meal_inventory_operations', 0);
        $this->assertDatabaseCount('meal_inventory_movements', 0);
    }

    public function test_negative_stock_and_modified_units_are_reported_as_blocked_in_preview(): void
    {
        $product = $this->product();
        app(InventoryLedger::class)->initialize($this->owner->id, [$product->id]);
        $product->update(['base_unit' => 'g']);
        $negative = $this->product('-1.000');
        $preview = app(InventoryLedger::class)->previewInitialization($this->owner->id, [$product->id, $negative->id]);

        $this->assertSame(['blocked', 'blocked'], array_column($preview, 'status'));
        $messages = implode(' ', array_column($preview, 'message'));
        $this->assertStringContainsString('reclasificación', $messages);
        $this->assertStringContainsString('negativo', $messages);
        $this->artisan('inventory:initialize-ledger', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('meal_inventory_movements', 1);
        $this->assertSame(-1.0, $negative->fresh()->stock);
    }

    public function test_rerun_refuses_stock_changed_outside_the_ledger_instead_of_hiding_the_difference(): void
    {
        $product = $this->product();
        app(InventoryLedger::class)->initialize($this->owner->id, [$product->id]);
        $product->update(['stock' => 100]);

        $preview = app(InventoryLedger::class)->previewInitialization($this->owner->id, [$product->id]);
        $this->assertSame('blocked', $preview[0]['status']);
        $this->assertStringContainsString('fuera del motor', $preview[0]['message']);
        $this->artisan('inventory:initialize-ledger', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('meal_inventory_movements', 1);
        $this->assertSame(100.0, $product->fresh()->stock);
    }

    public function test_invalid_or_unknown_user_selection_fails_without_writes(): void
    {
        $this->product();
        $this->artisan('inventory:initialize-ledger', ['--user' => ['not-an-id'], '--apply' => true])->assertFailed();
        $this->artisan('inventory:initialize-ledger', ['--user' => [$this->owner->id, 9999999], '--apply' => true])
            ->expectsOutputToContain('uno de los usuarios solicitados no existe')
            ->assertFailed();
        $this->assertDatabaseCount('meal_inventory_operations', 0);
    }

    public function test_a_late_failure_rolls_back_openings_for_every_selected_user_and_dispatches_no_events(): void
    {
        $other = User::factory()->create();
        $mine = $this->product();
        $theirs = $this->product('2.500', 'g', $other);
        $ledger = Mockery::mock(InventoryLedger::class)->makePartial();
        $ledger->shouldReceive('initialize')->with($this->owner->id, [$mine->id], Mockery::any())->once()->passthru();
        $ledger->shouldReceive('initialize')->with($other->id, [$theirs->id], Mockery::any())->once()
            ->andThrow(ValidationException::withMessages(['inventory' => 'Datos cambiaron durante la aplicación.']));
        $this->app->instance(InventoryLedger::class, $ledger);
        $events = [];
        Event::listen(InventoryMovementRecorded::class, function ($event) use (&$events) {
            $events[] = $event;
        });

        $this->artisan('inventory:initialize-ledger', ['--apply' => true])
            ->expectsOutputToContain('Sin cambios: Datos cambiaron durante la aplicación.')
            ->assertFailed();

        $this->assertDatabaseCount('meal_inventory_operations', 0);
        $this->assertDatabaseCount('meal_inventory_movements', 0);
        $this->assertSame([], $events);
        $this->assertSame(6.0, $mine->fresh()->stock);
        $this->assertSame(2.5, $theirs->fresh()->stock);
    }

    public function test_empty_catalog_and_duplicate_user_options_are_safe(): void
    {
        $this->artisan('inventory:initialize-ledger', ['--apply' => true])
            ->expectsOutputToContain('No hay productos que inicializar.')
            ->assertSuccessful();
        $this->product();
        $this->artisan('inventory:initialize-ledger', ['--user' => [$this->owner->id, $this->owner->id], '--apply' => true])
            ->assertSuccessful();
        $this->assertDatabaseCount('meal_inventory_operations', 1);
        $this->assertDatabaseCount('meal_inventory_movements', 1);
    }

    public function test_preview_service_rejects_cross_user_product_ids_without_authentication(): void
    {
        $other = User::factory()->create();
        $foreign = $this->product('1.000', 'unit', $other);
        $this->expectException(ValidationException::class);
        app(InventoryLedger::class)->previewInitialization($this->owner->id, [$foreign->id]);
    }
}
