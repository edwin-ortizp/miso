<?php

namespace Tests\Feature\Meal;

use App\Events\Meal\InventoryMovementRecorded;
use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->ledger = app(InventoryLedger::class);
        // Deliberately unauthenticated: the engine must scope every query explicitly.
    }

    private function product(string $stock = '6.000', ?User $owner = null): ShoppingItem
    {
        $product = new ShoppingItem(['name' => 'Huevos', 'base_unit' => 'unit', 'stock' => $stock, 'status' => 'available']);
        $product->user_id = ($owner ?? $this->owner)->id;
        $product->save();

        return $product;
    }

    private function rejected(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected inventory validation failure.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, implode(' ', $exception->errors()['inventory'] ?? []));
        }
    }

    public function test_purchase_consumption_and_count_chain_balances_and_preserve_a_zero_difference_count(): void
    {
        $product = $this->product();
        $this->ledger->record($this->owner->id, 'purchase', [['shopping_item_id' => $product->id, 'quantity' => 4, 'unit' => 'unit']], ['channel' => 'web'], 'purchase');
        $this->ledger->record($this->owner->id, 'consume', [['shopping_item_id' => $product->id, 'quantity' => -2, 'unit' => 'unit']], [], 'consume');
        $count = $this->ledger->count($this->owner->id, $product->id, 7, 'unit', ['channel' => 'mcp'], 'count');
        $this->ledger->count($this->owner->id, $product->id, 7, 'unit', [], 'same-count');

        $movements = MealInventoryMovement::orderBy('sequence')->get();
        $this->assertSame([6.0, 4.0, -2.0, -1.0, 0.0], $movements->pluck('delta')->all());
        $this->assertSame(['0.000', '6.000', '10.000', '8.000', '7.000'], $movements->pluck('balance_before')->all());
        $this->assertSame(['6.000', '10.000', '8.000', '7.000', '7.000'], $movements->pluck('balance_after')->all());
        $this->assertSame([1, 2, 3, 4, 5], $movements->pluck('sequence')->all());
        $this->assertSame(1, $movements->pluck('segment_id')->unique()->count());
        $this->assertSame(7.0, $product->fresh()->stock);
        $operation = MealInventoryOperation::findOrFail($count['operation_id']);
        $this->assertSame('Conteo físico', $operation->reason);
        $this->assertSame('mcp', $operation->channel);
        $this->assertSame('7.000', $operation->document_lines[0]['quantity']);
    }

    public function test_idempotency_normalizes_quantities_and_context_key_order_without_reapplying_after_a_later_change(): void
    {
        $product = $this->product();
        $first = $this->ledger->adjust($this->owner->id, $product->id, '1.0', 'unit', ['reason' => 'Ajuste', 'channel' => 'web'], 'same');
        $this->ledger->adjust($this->owner->id, $product->id, 2, 'unit', [], 'later');
        $this->assertSame($first, $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit', ['channel' => 'web', 'reason' => 'Ajuste'], 'same'));
        $this->assertSame(9.0, $product->fresh()->stock);
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $product->id, 3, 'unit', ['reason' => 'Ajuste', 'channel' => 'web'], 'same'), 'clave');
        $this->assertDatabaseCount('meal_inventory_movements', 3);
    }

    public function test_a_32_product_document_has_32_historical_lines_and_preserves_variants_of_one_product(): void
    {
        $lines = [];
        for ($i = 0; $i < 32; $i++) {
            $product = $this->product('0.000');
            $lines[] = ['shopping_item_id' => $product->id, 'quantity' => 1, 'unit' => 'unit', 'line_id' => 'ticket-'.$i];
        }
        $result = $this->ledger->record($this->owner->id, 'purchase', $lines, ['effective_date' => '2026-11-02', 'reason' => 'Compra'], 'ticket32');
        $operation = MealInventoryOperation::findOrFail($result['operation_id']);
        $this->assertTrue(Str::isUuid($operation->id));
        $this->assertNotNull($operation->created_at);
        $this->assertSame('2026-11-02', $operation->effective_date->toDateString());
        $this->assertNull($operation->effective_at);
        $this->assertCount(32, $operation->document_lines);
        $this->assertSame(range(1, 32), array_column($operation->document_lines, 'position'));
        $this->assertSame(32, MealInventoryMovement::where('operation_id', $operation->id)->count());
        $product = ShoppingItem::findOrFail($lines[0]['shopping_item_id']);
        $result = $this->ledger->record($this->owner->id, 'purchase', [
            ['shopping_item_id' => $product->id, 'quantity' => '0.1', 'unit' => 'unit', 'variant_label' => 'Caja pequeña'],
            ['shopping_item_id' => $product->id, 'quantity' => '0.2', 'unit' => 'unit', 'variant_label' => 'Caja grande'],
        ], [], 'variants');
        $this->assertCount(2, $result['document_lines']);
        $this->assertSame(['1.100', '1.300'], array_column($result['document_lines'], 'balance_after'));
        $product->update(['name' => 'Nombre nuevo']);
        $this->assertSame('Huevos', MealInventoryOperation::findOrFail($operation->id)->document_lines[0]['name']);
    }

    public function test_decimal_arithmetic_rounding_scientific_notation_and_bounds(): void
    {
        $product = $this->product('0.000');
        foreach (['0.1', '0.2', '-.100', '5e-4'] as $index => $delta) {
            $this->ledger->adjust($this->owner->id, $product->id, $delta, 'unit', [], 'decimal-'.$index);
        }
        $this->assertSame('0.201', MealInventoryMovement::orderByDesc('sequence')->first()->balance_after);
        $this->ledger->count($this->owner->id, $product->id, '999999999.999', 'unit', [], 'max');
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $product->id, '.001', 'unit'), 'máximo');
        $this->rejected(fn () => $this->ledger->count($this->owner->id, $product->id, '1000000000', 'unit'), 'máximo');
        $this->rejected(fn () => $this->ledger->count($this->owner->id, $product->id, '-.0001', 'unit'), 'negativo');
        $this->assertSame(999999999.999, $product->fresh()->stock);
    }

    public function test_a_failed_multi_product_write_rolls_back_stock_document_openings_and_events(): void
    {
        $product = $this->product();
        $empty = $this->product('0.000');
        $events = [];
        Event::listen(InventoryMovementRecorded::class, function ($event) use (&$events) {
            $events[] = $event;
        });
        $this->rejected(fn () => $this->ledger->record($this->owner->id, 'consume', [
            ['shopping_item_id' => $product->id, 'quantity' => -1, 'unit' => 'unit'],
            ['shopping_item_id' => $empty->id, 'quantity' => -1, 'unit' => 'unit'],
        ], [], 'failed'), 'negativo');
        $this->assertSame(6.0, $product->fresh()->stock);
        $this->assertDatabaseCount('meal_inventory_movements', 0);
        $this->assertDatabaseCount('meal_inventory_operations', 0);
        $this->assertSame([], $events);
    }

    public function test_explicit_user_scoping_rejects_foreign_products_corrections_origins_and_transfers(): void
    {
        $other = User::factory()->create();
        $foreign = $this->product('6.000', $other);
        $mine = $this->product();
        $foreignResult = $this->ledger->adjust($other->id, $foreign->id, 1, 'unit', [], 'shared-key');
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $foreign->id, 1, 'unit'), 'pertenece');
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $mine->id, 1, 'unit', ['corrects_operation_id' => $foreignResult['operation_id']]), 'pertenece');
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $mine->id, 1, 'unit', ['origin' => ['type' => 'purchase', 'id' => (string) Str::uuid()]]), 'origen');
        $this->rejected(fn () => $this->ledger->transfer($this->owner->id, $mine->id, $foreign->id), 'pertenece');
        $this->ledger->adjust($this->owner->id, $mine->id, 1, 'unit', [], 'shared-key');
        $this->assertSame(7.0, $mine->fresh()->stock);
        $this->assertSame(7.0, $foreign->fresh()->stock);
    }

    public function test_compensation_has_its_own_document_and_links_original_movements_without_mutating_them(): void
    {
        $product = $this->product();
        $original = $this->ledger->adjust($this->owner->id, $product->id, -2, 'unit', [], 'debit');
        $before = MealInventoryMovement::findOrFail($original['movement_ids'][0])->getAttributes();
        $result = $this->ledger->compensate($this->owner->id, $original['operation_id'], [], 'reverse');
        $this->assertNotSame($original['operation_id'], $result['operation_id']);
        $this->assertSame($result, $this->ledger->compensate($this->owner->id, $original['operation_id'], [], 'reverse'));
        $this->assertSame($before, MealInventoryMovement::findOrFail($original['movement_ids'][0])->getAttributes());
        $this->assertSame($original['movement_ids'][0], $result['document_lines'][0]['compensates_movement_id']);
        $this->assertSame($original['operation_id'], MealInventoryOperation::findOrFail($result['operation_id'])->corrects_operation_id);
        $this->assertSame(6.0, $product->fresh()->stock);
        $this->rejected(fn () => $this->ledger->compensate($this->owner->id, $original['operation_id'], [], 'another'), 'revertido');
    }

    public function test_successor_chain_preserves_identity_and_compensates_into_the_surviving_product(): void
    {
        $source = $this->product();
        $target = $this->product('1.000');
        $last = $this->product('2.000');
        $debit = $this->ledger->adjust($this->owner->id, $source->id, -1, 'unit', [], 'debit');
        $merged = $this->ledger->transfer($this->owner->id, $source->id, $target->id, [], 'merge');
        $this->assertSame($merged, $this->ledger->transfer($this->owner->id, $source->id, $target->id, [], 'merge'));
        $this->ledger->transfer($this->owner->id, $target->id, $last->id, [], 'merge-again');
        $result = $this->ledger->compensate($this->owner->id, $debit['operation_id'], [], 'restore');
        $this->assertSame(9.0, $last->fresh()->stock);
        $this->assertSame(0.0, $source->fresh()->stock);
        $this->assertSame($last->id, $result['document_lines'][0]['shopping_item_id']);
        $this->assertSame($source->id, MealInventoryMovement::findOrFail($debit['movement_ids'][0])->historical_product_id);
        $this->assertDatabaseCount('inventory_product_successors', 2);
        $this->rejected(fn () => $this->ledger->transfer($this->owner->id, $last->id, $source->id), 'ciclos');
    }

    public function test_events_are_dispatched_after_the_outer_commit_with_the_origin_and_never_on_rollback(): void
    {
        $product = $this->product();
        $this->ledger->initialize($this->owner->id, [$product->id]);
        $events = [];
        Event::listen(InventoryMovementRecorded::class, function ($event) use (&$events) {
            $events[] = $event;
        });
        DB::beginTransaction();
        $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit', [], 'rolled-back');
        $this->assertSame([], $events);
        DB::rollBack();
        $this->assertSame([], $events);
        $this->assertSame(6.0, $product->fresh()->stock);

        $origin = ['type' => 'manual', 'reference' => 'Conteo de nevera'];
        $result = DB::transaction(function () use ($product, $origin, &$events) {
            $result = $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit', ['origin' => $origin], 'committed');
            $this->assertSame([], $events);

            return $result;
        });
        $this->assertCount(1, $events);
        $this->assertSame($origin, $events[0]->origin);
        $this->assertSame($result['operation_id'], $events[0]->operationId);
        $this->assertSame($result['movement_ids'][0], $events[0]->movementId);
    }

    public function test_can_attach_to_existing_business_operation_without_a_second_document_and_roll_back_with_it(): void
    {
        $product = $this->product();
        $operation = MealInventoryOperation::create(['user_id' => $this->owner->id, 'operation_key' => 'business', 'action' => 'cook', 'fingerprint' => str_repeat('a', 64)]);
        $lines = [['shopping_item_id' => $product->id, 'quantity' => -1, 'unit' => 'unit']];
        $result = $this->ledger->apply($operation, 'cook', $lines, ['channel' => 'web']);
        $this->assertSame($operation->id, $result['operation_id']);
        $this->assertSame($result, $this->ledger->apply($operation, 'cook', $lines, ['channel' => 'web']));
        $this->assertDatabaseCount('meal_inventory_operations', 2); // One opening plus the business document.
        $this->assertSame(5.0, $product->fresh()->stock);
        $this->rejected(fn () => $this->ledger->apply($operation, 'cook', [['shopping_item_id' => $product->id, 'quantity' => -2, 'unit' => 'unit']], ['channel' => 'web']), 'otros datos');
    }

    public function test_new_evidence_is_immutable_but_the_live_reference_can_be_detached_for_a_merge(): void
    {
        $product = $this->product();
        $result = $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit');
        $movement = MealInventoryMovement::findOrFail($result['movement_ids'][0]);
        $this->rejected(fn () => $movement->update(['delta' => 20]), 'inmutable');
        $movement->refresh();
        $movement->update(['shopping_item_id' => null]);
        $this->assertSame($product->id, $movement->fresh()->historical_product_id);
        $operation = MealInventoryOperation::findOrFail($result['operation_id']);
        $this->rejected(fn () => $operation->update(['reason' => 'Otra razón']), 'inmutable');
        $this->rejected(fn () => $operation->refresh()->delete(), 'borrar');
        $this->rejected(fn () => $movement->delete(), 'borrar');
    }

    public function test_legacy_history_is_not_replayed_or_given_invented_balances(): void
    {
        $product = $this->product();
        $operation = MealInventoryOperation::create(['user_id' => $this->owner->id, 'operation_key' => 'legacy', 'action' => 'consume', 'fingerprint' => str_repeat('b', 64)]);
        $old = $operation->movements()->create(['user_id' => $this->owner->id, 'shopping_item_id' => $product->id, 'name' => 'Huevos', 'unit' => 'unit', 'delta' => -50]);
        $first = $this->ledger->initialize($this->owner->id, [$product->id]);
        $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit');
        $this->assertSame($first, $this->ledger->initialize($this->owner->id, [$product->id]));
        $this->assertSame(7.0, $product->fresh()->stock);
        $this->assertNull($old->fresh()->balance_before);
        $this->assertNull($old->fresh()->balance_after);
        $this->assertSame(-50.0, $old->fresh()->delta);
    }

    public function test_units_invalid_lines_and_stock_changes_outside_the_engine_are_rejected_atomically(): void
    {
        $product = $this->product();
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $product->id, 1, 'g'), 'unidad');
        $this->assertDatabaseCount('meal_inventory_operations', 0);
        $this->ledger->initialize($this->owner->id, [$product->id]);
        $product->update(['stock' => 100]);
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit'), 'fuera del motor');
        $product->update(['stock' => 6, 'base_unit' => 'g']);
        $this->rejected(fn () => $this->ledger->adjust($this->owner->id, $product->id, 1, 'g'), 'reclasificación');
        $this->assertDatabaseCount('meal_inventory_movements', 1);
    }

    public function test_failures_also_roll_back_the_callers_business_document_and_allow_a_clean_retry(): void
    {
        $product = $this->product();
        try {
            DB::transaction(function () use ($product) {
                $product->update(['name' => 'Documento pendiente']);
                $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit', [], 'business-rollback');
                throw new \RuntimeException('Business document failed.');
            });
            $this->fail('Expected caller failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Business document failed.', $exception->getMessage());
        }
        $this->assertSame('Huevos', $product->fresh()->name);
        $this->assertSame(6.0, $product->fresh()->stock);
        $this->assertDatabaseCount('meal_inventory_operations', 0);
        $this->assertDatabaseCount('meal_inventory_movements', 0);
        $this->ledger->adjust($this->owner->id, $product->id, 1, 'unit', [], 'business-rollback');
        $this->assertSame(7.0, $product->fresh()->stock);
    }

    public function test_zero_opening_and_ownership_of_compensation_references_are_preserved(): void
    {
        $mine = $this->product('0.000');
        $other = User::factory()->create();
        $foreign = $this->product('0.000', $other);
        $foreignResult = $this->ledger->adjust($other->id, $foreign->id, 1, 'unit');
        $this->rejected(fn () => $this->ledger->record($this->owner->id, 'adjustment', [[
            'shopping_item_id' => $mine->id, 'quantity' => -1, 'unit' => 'unit',
            'compensates_movement_id' => $foreignResult['movement_ids'][0],
        ]]), 'negativo');
        $this->rejected(fn () => $this->ledger->record($this->owner->id, 'adjustment', [[
            'shopping_item_id' => $mine->id, 'quantity' => 1, 'unit' => 'unit',
            'compensates_movement_id' => $foreignResult['movement_ids'][0],
        ]]), 'original');
        $opening = $this->ledger->initialize($this->owner->id, [$mine->id]);
        $line = MealInventoryOperation::findOrFail($opening['operation_ids'][0])->document_lines[0];
        $this->assertSame('0.000', $line['delta']);
        $this->assertSame('0.000', $line['balance_after']);
        $this->assertSame(0.0, $mine->fresh()->stock);
    }
}
