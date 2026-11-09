<?php

namespace Tests\Feature\Meal;

use App\Livewire\Meal\MealInventoryHistory;
use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\IngredientMergeService;
use App\Services\Meal\InventoryHistory;
use App\Services\Meal\InventoryLedger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class InventoryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_kardex_load_error_offers_retry_and_recovers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = ShoppingItem::create(['name' => 'Recuperado', 'base_unit' => 'unit', 'stock' => 3, 'status' => 'available']);
        $mock = \Mockery::mock(InventoryHistory::class)->makePartial();
        $mock->shouldReceive('product')->once()->andThrow(new QueryException('sqlite', 'select 1', [], new \RuntimeException('unavailable')));
        $mock->shouldReceive('product')->passthru();
        $this->app->instance(InventoryHistory::class, $mock);
        Livewire::test(MealInventoryHistory::class, ['item' => $product->id])->assertSee('Reintentar')->call('$refresh')->assertSee('Recuperado');
    }

    public function test_unit_reclassification_keeps_separate_segments_without_converting_balances(): void
    {
        $user = User::factory()->create();
        $ledger = app(InventoryLedger::class);
        $product = $ledger->saveProduct($user->id, ['name' => 'Producto', 'base_unit' => 'g', 'stock' => 600, 'status' => 'available']);
        $ledger->saveProduct($user->id, ['base_unit' => 'unit', 'stock' => 3], $product->id);
        $history = app(InventoryHistory::class)->movements($user->id, $product->id);
        $this->assertSame('3.000', $history['product']['stock']);
        $this->assertSame('unit', $history['movements'][0]['unit']);
        $this->assertSame('3.000', $history['movements'][0]['balance_after']);
        $this->assertSame('g', $history['movements'][1]['unit']);
        $this->assertSame('600.000', $history['movements'][1]['balance_before']);
        $this->assertNotSame($history['movements'][0]['segment_id'], $history['movements'][1]['segment_id']);
    }

    public function test_web_kardex_is_read_only_has_filters_and_rejects_foreign_identity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = ShoppingItem::create(['name' => 'Producto web', 'base_unit' => 'unit', 'stock' => 3, 'status' => 'available']);
        $this->get(route('meals.inventory.history', $product->id))->assertOk()->assertSee('Sin movimientos');
        app(InventoryLedger::class)->count($user->id, $product->id, 7, 'unit');
        $this->get(route('meals.inventory.history', $product->id))->assertOk()->assertSee('Stock actual: 7.000')->assertSee('Cobertura completa desde')->assertSee('Ver documento');
        Livewire::test(MealInventoryHistory::class, ['item' => $product->id])->set('channel', 'mcp')->assertSee('Sin resultados para estos filtros');
        $this->assertSame(7.0, $product->fresh()->stock);
        $this->actingAs(User::factory()->create())->get(route('meals.inventory.history', $product->id))->assertNotFound();
    }

    public function test_filtering_and_pagination_never_recompute_persisted_balances(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = ShoppingItem::create(['status' => 'available', 'name' => 'Huevos', 'base_unit' => 'unit', 'stock' => 6]);
        $ledger = app(InventoryLedger::class);
        $original = $ledger->adjust($user->id, $product->id, 4, 'unit', ['channel' => 'web']);
        $ledger->adjust($user->id, $product->id, -2, 'unit', ['channel' => 'system']);
        $ledger->count($user->id, $product->id, 7, 'unit', ['channel' => 'mcp']);
        $history = app(InventoryHistory::class);
        $data = $history->movements($user->id, $product->id, ['channel' => 'mcp']);
        $this->assertSame('7.000', $data['product']['stock']);
        $this->assertSame('8.000', $data['movements'][0]['balance_before']);
        $this->assertSame('7.000', $data['movements'][0]['balance_after']);
        $this->assertSame('-1.000', $data['movements'][0]['delta']);
        $this->assertNotNull($data['coverage_started_at']);
        $ledger->compensate($user->id, $original['operation_id']);
        $this->assertSame('3.000', $history->movements($user->id, $product->id)['product']['stock']);
    }

    public function test_deleted_merged_identity_has_its_own_history_and_destination_shows_predecessors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $source = ShoppingItem::create(['status' => 'available', 'name' => 'Origen', 'base_unit' => 'unit', 'stock' => 2]);
        $target = ShoppingItem::create(['status' => 'available', 'name' => 'Destino', 'base_unit' => 'unit', 'stock' => 3]);
        app(IngredientMergeService::class)->merge($source, $target, null);
        $history = app(InventoryHistory::class);
        $old = $history->movements($user->id, $source->id);
        $this->assertNull($old['product']['stock']);
        $this->assertSame($target->id, $old['product']['successor']['id']);
        $this->assertSame('0.000', $old['movements'][0]['balance_after']);
        $current = $history->movements($user->id, $target->id);
        $this->assertSame('5.000', $current['product']['stock']);
        $this->assertSame($source->id, $current['product']['predecessors'][0]['id']);
        $this->assertSame(2, $current['pagination']['total']);
    }

    public function test_foreign_document_and_product_ids_are_not_readable_without_an_authentication_scope(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = new ShoppingItem(['status' => 'available', 'name' => 'Privado', 'base_unit' => 'unit', 'stock' => 6]);
        $product->user_id = $other->id;
        $product->save();
        $operation = app(InventoryLedger::class)->adjust($other->id, $product->id, 1, 'unit');
        $history = app(InventoryHistory::class);
        $this->assertSame([], $history->documents($user->id)['documents']);
        try {
            $history->document($user->id, $operation['operation_id']);
            $this->fail('Foreign document readable.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->expectException(NotFoundHttpException::class);
        $history->movements($user->id, $product->id);
    }
}
