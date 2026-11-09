<?php

namespace Tests\Feature\Meal;

use App\Livewire\Meal\MealInventoryDocuments;
use App\Livewire\Meal\MealInventoryDocumentShow;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Shopping\GetInventoryDocumentTool;
use App\Models\MealInventoryOperation;
use App\Models\ShoppingItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Meal\InventoryHistory;
use App\Services\Meal\InventoryLedger;
use App\Services\Meal\PurchaseRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_load_error_offers_retry_and_recovers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = ShoppingItem::create(['name' => 'Recuperado', 'base_unit' => 'unit', 'stock' => 3, 'status' => 'available']);
        $operation = app(InventoryLedger::class)->count($user->id, $product->id, 4, 'unit');
        $mock = \Mockery::mock(InventoryHistory::class)->makePartial();
        $mock->shouldReceive('document')->once()->andThrow(new QueryException('sqlite', 'select 1', [], new \RuntimeException('unavailable')));
        $mock->shouldReceive('document')->once()->passthru();
        $this->app->instance(InventoryHistory::class, $mock);
        Livewire::test(MealInventoryDocumentShow::class, ['operation' => $operation['operation_id']])->assertSee('Reintentar')->call('$refresh')->assertSee('Recuperado');
    }

    public function test_document_queries_are_batched_and_deleted_origins_preserve_snapshot_without_a_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::create(['name' => 'Tienda']);
        $product = ShoppingItem::create(['name' => 'Histórico', 'base_unit' => 'unit', 'stock' => 0, 'status' => 'available']);
        $purchase = app(PurchaseRecorder::class)->save(['store_id' => $store->id, 'purchased_at' => now()->toDateTimeString(), 'ticket_reference' => 'ORIGEN', 'operation_key' => (string) Str::uuid(), 'lines' => [['shopping_item_id' => $product->id, 'packages' => 2]]]);
        $history = app(InventoryHistory::class);
        $first = $history->documents($user->id, ['type' => 'purchase'])['documents'][0];
        $purchase->delete();
        $detail = $history->document($user->id, $first['id']);
        $this->assertNull($detail['document']['origin_url']);
        $this->assertSame('ORIGEN', $detail['document']['origin']['reference']);
        $this->assertSame('Histórico', $detail['lines'][0]['name']);
        $ledger = app(InventoryLedger::class);
        foreach (range(1, 12) as $index) {
            $ledger->count($user->id, $product->id, 2, 'unit');
        }
        DB::enableQueryLog();
        $history->documents($user->id, ['per_page' => 1]);
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $history->documents($user->id, ['per_page' => 50]);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small + 2, $large);
    }

    public function test_web_document_routes_render_snapshots_and_preserve_safe_return_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = ShoppingItem::create(['name' => 'Producto visible', 'base_unit' => 'unit', 'stock' => 6, 'status' => 'available']);
        $op = app(InventoryLedger::class)->count($user->id, $product->id, 8, 'unit');
        $this->get(route('meals.inventory'))->assertOk()->assertSee('Movimientos de inventario')->assertSee('Conteo físico');
        $return = ['screen' => 'documents', 'filters' => ['q' => 'prueba', 'page' => 2, 'redirect' => 'https://example.test']];
        $this->get(route('meals.inventory.show', ['operation' => $op['operation_id'], 'return' => $return]))->assertOk()->assertSee($op['operation_id'])->assertSee('Producto visible')->assertSee('8.000')->assertSee('Ver Kardex');
        $url = app(InventoryHistory::class)->returnUrl($user->id, $return);
        $this->assertStringContainsString('q=prueba', $url);
        $this->assertStringNotContainsString('example.test', $url);
        $this->get(route('meals.inventory.show', ['operation' => $op['operation_id'], 'return' => ['filters' => 'invalid']]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('meals.inventory.show', $op['operation_id']))->assertNotFound();
    }

    public function test_livewire_empty_invalid_filtered_and_retry_states(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(MealInventoryDocuments::class)->assertSee('Sin movimientos de inventario')->set('from', 'invalid')->assertSee('Revisa los filtros');
        $product = ShoppingItem::create(['name' => 'Algo', 'base_unit' => 'unit', 'stock' => 0, 'status' => 'available']);
        app(InventoryLedger::class)->count(auth()->id(), $product->id, 1, 'unit');
        Livewire::test(MealInventoryDocuments::class)->set('search', 'sin coincidencias')->assertSee('Sin resultados para estos filtros');
        $mock = \Mockery::mock(InventoryHistory::class)->makePartial();
        $mock->shouldReceive('documents')->once()->andThrow(new QueryException('sqlite', 'select 1', [], new \RuntimeException('unavailable')));
        $mock->shouldReceive('documents')->once()->passthru();
        $this->app->instance(InventoryHistory::class, $mock);
        Livewire::test(MealInventoryDocuments::class)->assertSee('Reintentar')->call('$refresh')->assertSee('Conteo físico');
    }

    public function test_32_product_document_remains_complete_when_registry_is_filtered_and_purchase_is_edited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::create(['name' => 'Tienda']);
        $lines = [];
        for ($index = 0; $index < 32; $index++) {
            $product = ShoppingItem::create(['name' => 'Ingrediente '.$index, 'base_unit' => 'unit', 'stock' => 0, 'status' => 'available']);
            $lines[] = ['shopping_item_id' => $product->id, 'packages' => 2];
        }
        $input = ['store_id' => $store->id, 'purchased_at' => '2026-11-02 09:30:00', 'operation_key' => (string) Str::uuid(), 'ticket_reference' => 'TICKET-32', 'lines' => $lines];
        $purchase = app(PurchaseRecorder::class)->save($input);
        $history = app(InventoryHistory::class);
        $registry = $history->documents($user->id, ['item_id' => $lines[0]['shopping_item_id'], 'type' => 'purchase', 'search' => 'TICKET-32']);
        $this->assertCount(1, $registry['documents']);
        $header = $registry['documents'][0];
        $this->assertSame(32, $header['product_count']);
        $this->assertSame(32, $header['line_count']);
        $detail = $history->document($user->id, $header['id']);
        $this->assertCount(32, $detail['lines']);
        $this->assertTrue($detail['lines'][0]['kardex_available']);
        $this->assertSame('0.000', $detail['lines'][0]['balance_before']);
        $this->assertSame('2.000', $detail['lines'][0]['balance_after']);
        $this->assertSame(route('meals.purchases.show', $purchase->id), $detail['document']['origin_url']);
        $original = $detail;
        $input['operation_key'] = (string) Str::uuid();
        $input['lines'] = $purchase->lines->take(31)->map(fn ($line) => ['id' => $line->id, 'shopping_item_id' => $line->shopping_item_id, 'packages' => 3])->all();
        app(PurchaseRecorder::class)->save($input, $purchase->id);
        $current = $history->document($user->id, $header['id']);
        $this->assertSame($original['lines'], $current['lines']);
        $this->assertCount(1, $current['corrections']);
        $correction = $history->document($user->id, $current['corrections'][0]['id']);
        $this->assertSame($header['id'], $correction['document']['corrects_operation_id']);
    }

    public function test_repeated_variants_and_more_than_100_lines_preserve_distinct_lines_and_explicit_pagination(): void
    {
        $user = User::factory()->create();
        $product = new ShoppingItem(['status' => 'available', 'name' => 'Arroz', 'base_unit' => 'g', 'stock' => 0]);
        $product->user_id = $user->id;
        $product->save();
        $lines = array_map(fn ($index) => ['shopping_item_id' => $product->id, 'quantity' => 1, 'unit' => 'g', 'variant_label' => 'Presentación '.$index], range(1, 101));
        $result = app(InventoryLedger::class)->record($user->id, 'purchase', $lines);
        $history = app(InventoryHistory::class);
        $page = $history->document($user->id, $result['operation_id'], ['per_page' => 100]);
        $this->assertSame(1, $page['document']['product_count']);
        $this->assertSame(101, $page['document']['line_count']);
        $this->assertCount(100, $page['lines']);
        $this->assertSame(2, $page['pagination']['next_page']);
        $last = $history->document($user->id, $result['operation_id'], ['per_page' => 100, 'page' => 2]);
        $this->assertSame(101, $last['lines'][0]['position']);
        $this->assertNull($last['pagination']['next_page']);
        $this->actingAs($user);
        Livewire::test(MealInventoryDocumentShow::class, ['operation' => $result['operation_id']])
            ->assertSee('Presentación 100')->assertDontSee('Presentación 101')->call('nextPage')->assertSee('Presentación 101')->assertDontSee('Presentación 100');
        LifeTrackerServer::actingAs($user)->tool(GetInventoryDocumentTool::class, ['operation_id' => $result['operation_id'], 'page' => 2])->assertOk()->assertSee('Presentación 101')->assertDontSee('Presentación 100');
    }

    public function test_legacy_documents_are_read_only_and_keep_unknown_balances(): void
    {
        $user = User::factory()->create();
        $product = new ShoppingItem(['status' => 'available', 'name' => 'Legado', 'base_unit' => 'unit', 'stock' => 6]);
        $product->user_id = $user->id;
        $product->save();
        $operation = MealInventoryOperation::create(['user_id' => $user->id, 'operation_key' => 'old', 'action' => 'consume', 'fingerprint' => str_repeat('a', 64)]);
        $operation->movements()->create(['user_id' => $user->id, 'shopping_item_id' => $product->id, 'name' => 'Legado', 'unit' => 'unit', 'delta' => -2]);
        MealInventoryOperation::create(['user_id' => $user->id, 'operation_key' => 'plan', 'action' => 'plan', 'fingerprint' => str_repeat('b', 64)]);
        $before = $product->refresh()->getAttributes();
        $history = app(InventoryHistory::class);
        $list = $history->documents($user->id);
        $this->assertSame(1, $list['pagination']['total']);
        $detail = $history->document($user->id, $operation->id);
        $this->assertTrue($detail['document']['legacy']);
        $this->assertNull($detail['lines'][0]['balance_after']);
        $this->assertNull($detail['lines'][0]['balance_before']);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertDatabaseCount('meal_inventory_movements', 1);
    }

    public function test_filters_validate_dates_types_channels_and_pagination_and_order_is_stable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = ShoppingItem::create(['status' => 'available', 'name' => 'Huevos', 'base_unit' => 'unit', 'stock' => 6]);
        $ledger = app(InventoryLedger::class);
        $first = $ledger->count($user->id, $product->id, 7, 'unit', ['channel' => 'mcp', 'effective_date' => '2026-10-31']);
        $second = $ledger->count($user->id, $product->id, 7, 'unit', ['channel' => 'web', 'effective_date' => '2026-11-02']);
        $history = app(InventoryHistory::class);
        $list = $history->documents($user->id, ['from' => '2026-10-31', 'to' => '2026-10-31', 'type' => 'count', 'channel' => 'mcp']);
        $this->assertSame([$first['operation_id']], array_column($list['documents'], 'id'));
        $paged = $history->documents($user->id, ['type' => 'count', 'per_page' => 1]);
        $next = $history->documents($user->id, ['type' => 'count', 'per_page' => 1, 'page' => 2]);
        $this->assertSame([$second['operation_id'], $first['operation_id']], [$paged['documents'][0]['id'], $next['documents'][0]['id']]);
        $this->assertSame(1, $history->documents($user->id, ['to' => '2026-10-31', 'type' => 'count'])['pagination']['total']);
        foreach ([['from' => '2026-11-02', 'to' => '2026-10-31'], ['type' => 'other'], ['channel' => 'other'], ['per_page' => 101], ['page' => 0]] as $filter) {
            try {
                $history->documents($user->id, $filter);
                $this->fail('Expected validation.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }
}
