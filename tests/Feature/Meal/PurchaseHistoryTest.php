<?php

namespace Tests\Feature\Meal;

use App\Livewire\Meal\MealIngredients;
use App\Livewire\Meal\MealPurchases;
use App\Livewire\Meal\MealPurchaseShow;
use App\Livewire\Meal\MealShopping;
use App\Livewire\Meal\PurchaseEditor;
use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\Purchase;
use App\Models\ShoppingItem;
use App\Models\ShoppingItemPrice;
use App\Models\Store;
use App\Models\User;
use App\Services\Meal\InventoryLedger;
use App\Services\Meal\PurchaseRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingredient_form_metadata_save_preserves_new_stock_and_count_records_the_reason(): void
    {
        $form = Livewire::test(MealIngredients::class)->call('openForm', $this->item->id);
        app(InventoryLedger::class)->count((int) auth()->id(), $this->item->id, 200, 'ml');
        $before = MealInventoryMovement::count();
        $form->set('name', 'Leche renombrada')->call('save')->assertHasNoErrors();
        $this->assertSame(200.0, $this->item->fresh()->stock);
        $this->assertSame($before, MealInventoryMovement::count());
        Livewire::test(MealIngredients::class)->call('openForm', $this->item->id)->set('stock', 175)->set('stockReason', 'Conteo de hoy')->call('save')->assertHasNoErrors();
        $this->assertSame(175.0, $this->item->fresh()->stock);
        $operation = MealInventoryOperation::where('reason', 'Conteo de hoy')->sole();
        $this->assertSame('web', $operation->channel);
        $this->assertSame(-25.0, $operation->movements()->sole()->delta);
    }

    public function test_inventory_document_retains_32_lines_and_correction_history(): void
    {
        $input = $this->input;
        $input['lines'] = [];
        for ($index = 0; $index < 32; $index++) {
            $item = ShoppingItem::create(['name' => 'Producto '.$index, 'base_unit' => 'unit', 'stock' => 0, 'status' => 'available']);
            $input['lines'][] = ['shopping_item_id' => $item->id, 'packages' => 2];
        }
        $purchase = $this->save($input);
        $document = MealInventoryOperation::where('document_type', 'purchase')->where('origin->id', $purchase->id)->sole();
        $snapshot = $document->getAttributes();
        $this->assertCount(32, $document->document_lines);
        $this->assertSame(32, $document->movements()->count());
        $input['operation_key'] = (string) Str::uuid();
        $input['lines'] = $purchase->lines->take(31)->map(fn ($line) => ['id' => $line->id, 'shopping_item_id' => $line->shopping_item_id, 'packages' => 3])->all();
        $this->save($input, $purchase->id);
        $correction = MealInventoryOperation::where('document_type', 'purchase_correction')->sole();
        $this->assertSame($document->id, $correction->corrects_operation_id);
        $this->assertCount(32, $correction->document_lines);
        $this->assertSame($snapshot, $document->fresh()->getAttributes());
        $this->save($input, $purchase->id);
        $this->assertSame(1, MealInventoryOperation::where('document_type', 'purchase_correction')->count());
        $changed = $input;
        $changed['lines'][0]['packages'] = 4;
        $this->expectException(ValidationException::class);
        $this->save($changed, $purchase->id);
    }

    public function test_reusing_a_purchase_creation_key_with_different_content_is_rejected(): void
    {
        $this->save();
        $changed = $this->input;
        $changed['lines'][0]['packages'] = 5;
        $this->expectException(ValidationException::class);
        $this->save($changed);
    }

    private ShoppingItem $item;

    private Store $store;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->item = ShoppingItem::create(['name' => 'Leche', 'base_unit' => 'ml', 'stock' => 100, 'to_buy' => 2, 'next_purchase' => true, 'status' => 'available']);
        $variant = $this->item->variants()->create(['content' => 1000]);
        $this->store = Store::create(['name' => 'D1']);
        $this->input = ['operation_key' => (string) Str::uuid(), 'store_id' => $this->store->id, 'purchased_at' => '2026-09-01 10:00:00', 'total_paid' => 999,
            'lines' => [['shopping_item_id' => $this->item->id, 'shopping_item_variant_id' => $variant->id, 'packages' => 2, 'unit_price' => 10, 'ticket_text' => 'LECHE TET']]];
    }

    private function save(?array $input = null, ?string $id = null): Purchase
    {
        return app(PurchaseRecorder::class)->save($input ?? $this->input, $id);
    }

    public function test_multiline_purchase_updates_stock_prices_and_list_atomically_and_is_idempotent(): void
    {
        $other = ShoppingItem::create(['name' => 'Huevos', 'base_unit' => 'unit', 'stock' => 0, 'next_purchase' => true, 'status' => 'available']);
        $this->input['lines'][] = ['shopping_item_id' => $other->id, 'packages' => 6];
        $purchase = $this->save();
        $this->assertSame(2100.0, $this->item->fresh()->stock);
        $this->assertFalse($this->item->fresh()->next_purchase);
        $this->assertSame(0.0, $this->item->fresh()->to_buy);
        $this->assertSame(6.0, $other->fresh()->stock);
        $price = $purchase->lines->firstWhere('shopping_item_id', $this->item->id)->price;
        $this->assertSame('2026-09-01', $price->observed_on->toDateString());
        $this->assertTrue($price->paid);
        $this->assertSame('ticket', $price->source);
        $this->assertSame(['lines_total' => 20.0, 'lines_total_complete' => false], $purchase->summary());
        $this->assertSame('999.00', $purchase->total_paid);
        $this->assertSame($purchase->id, $this->save()->id);
        $this->assertSame(1, Purchase::count());
        $this->assertSame(2100.0, $this->item->fresh()->stock);
    }

    public function test_invalid_later_line_does_not_change_any_stock(): void
    {
        $this->input['lines'][] = ['shopping_item_id' => (string) Str::uuid(), 'packages' => 1];
        try {
            $this->save();
            $this->fail('Expected validation');
        } catch (ValidationException) {
        }
        $this->assertSame(100.0, $this->item->fresh()->stock);
        $this->assertTrue($this->item->fresh()->next_purchase);
        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, ShoppingItemPrice::count());
    }

    public function test_edit_preserves_snapshot_and_stock_when_catalog_content_changes(): void
    {
        $purchase = $this->save();
        $line = $purchase->lines->first();
        $this->item->variants()->first()->update(['content' => 500]);
        $this->item->refresh()->update(['name' => 'Leche nueva', 'next_purchase' => true, 'to_buy' => 3]);
        $this->input['lines'][0]['id'] = $line->id;
        $this->input['lines'][0]['unit_price'] = 12;
        $this->input['purchased_at'] = '2026-09-02 11:00:00';
        $edited = $this->save($this->input, $purchase->id);
        $this->assertSame(2100.0, $this->item->fresh()->stock);
        $this->assertSame('Leche', $edited->lines->first()->product_name);
        $this->assertSame(1000.0, $edited->lines->first()->content);
        $this->assertTrue($this->item->fresh()->next_purchase);
        $this->assertSame(3.0, $this->item->fresh()->to_buy);
        $this->assertSame('12.00', $edited->lines->first()->price->amount);
        $this->assertSame('2026-09-02', $edited->lines->first()->price->observed_on->toDateString());
    }

    public function test_edit_changes_quantity_using_current_content_and_keeps_independent_prices(): void
    {
        $purchase = $this->save();
        $variant = $this->item->variants()->first();
        $variant->update(['content' => 500]);
        $manual = $variant->prices()->create(['store_id' => $this->store->id, 'amount' => 50, 'observed_on' => now(), 'source' => 'manual']);
        $this->input['lines'][0]['id'] = $purchase->lines->first()->id;
        $this->input['lines'][0]['packages'] = 3;
        $this->input['lines'][0]['unit_price'] = null;
        $edited = $this->save($this->input, $purchase->id);
        $this->assertSame(1600.0, $this->item->fresh()->stock);
        $this->assertSame(1500.0, $edited->lines->first()->stock_added);
        $this->assertNull($edited->lines->first()->price);
        $this->assertNotNull($manual->fresh());
    }

    public function test_edit_replaces_product_and_deletes_old_linked_price(): void
    {
        $purchase = $this->save();
        $other = ShoppingItem::create(['name' => 'Pan', 'base_unit' => 'unit', 'stock' => 0, 'next_purchase' => true, 'status' => 'available']);
        $this->input['lines'] = [['shopping_item_id' => $other->id, 'packages' => 2]];
        $edited = $this->save($this->input, $purchase->id);
        $this->assertSame(100.0, $this->item->fresh()->stock);
        $this->assertSame(2.0, $other->fresh()->stock);
        $this->assertFalse($other->fresh()->next_purchase);
        $this->assertSame(0, ShoppingItemPrice::count());
        $this->assertSame(1, $edited->lines->count());
    }

    public function test_negative_stock_edit_rolls_back_header_lines_and_prices(): void
    {
        $purchase = $this->save();
        $this->item->update(['stock' => 0]);
        $this->input['lines'][0]['id'] = $purchase->lines->first()->id;
        $this->input['lines'][0]['packages'] = 1;
        $this->input['total_paid'] = 123;
        try {
            $this->save($this->input, $purchase->id);
            $this->fail('Expected validation');
        } catch (ValidationException) {
        }
        $this->assertSame('999.00', $purchase->fresh()->total_paid);
        $this->assertSame(2.0, $purchase->lines()->first()->packages);
        $this->assertSame('10.00', $purchase->lines()->first()->price->amount);
        $this->assertSame(0.0, $this->item->fresh()->stock);
    }

    public function test_duplicate_ticket_is_rejected_but_same_purchase_can_be_edited(): void
    {
        $this->input['ticket_reference'] = ' T-1 ';
        $purchase = $this->save();
        $this->input['lines'][0]['id'] = $purchase->lines->first()->id;
        $this->save($this->input, $purchase->id);
        unset($this->input['lines'][0]['id']);
        $this->input['operation_key'] = (string) Str::uuid();
        $this->expectException(ValidationException::class);
        $this->save();
    }

    public function test_foreign_products_stores_and_lines_cannot_be_written(): void
    {
        $purchase = $this->save();
        $this->actingAs(User::factory()->create());
        foreach (['product', 'store'] as $case) {
            $local = ShoppingItem::create(['name' => $case, 'base_unit' => 'unit', 'status' => 'available']);
            $store = Store::firstOrCreate(['name' => 'Local']);
            $input = ['operation_key' => (string) Str::uuid(), 'purchased_at' => now()->toDateTimeString(), 'store_id' => $case === 'store' ? $this->store->id : $store->id,
                'lines' => [['shopping_item_id' => $case === 'product' ? $this->item->id : $local->id, 'packages' => 1]]];
            try {
                $this->save($input);
                $this->fail('Expected validation');
            } catch (ValidationException) {
            }
        }
        $this->expectException(ModelNotFoundException::class);
        $this->save($this->input, $purchase->id);
    }

    public function test_referenced_catalog_records_cannot_be_deleted_and_store_merge_moves_history(): void
    {
        $purchase = $this->save();
        foreach ([$this->item, $this->item->variants()->first(), $this->store] as $model) {
            try {
                $model->delete();
                $this->fail('Expected foreign-key protection');
            } catch (QueryException) {
            }
        }
        $target = Store::create(['name' => 'Ara']);
        $this->store->mergeInto($target);
        $this->assertSame($target->id, $purchase->fresh()->store_id);
        $this->assertSame($target->id, $purchase->lines()->first()->price->store_id);
    }

    public function test_history_counts_distinct_purchases_with_inclusive_boundaries(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 9)->setTime(12, 0));
        foreach ([30, 60, 90, 91] as $days) {
            $input = $this->input;
            $input['operation_key'] = (string) Str::uuid();
            $input['purchased_at'] = now()->subDays($days)->toDateTimeString();
            $input['lines'][] = $input['lines'][0];
            $this->save($input);
        }
        $summary = $this->item->fresh()->purchaseHistorySummary();
        $this->assertSame([30 => 1, 60 => 2, 90 => 3], $summary['counts']);
        $this->assertFalse($summary['never_purchased']);
        $never = ShoppingItem::create(['name' => 'Nunca', 'base_unit' => 'unit', 'status' => 'available']);
        $this->assertTrue($never->purchaseHistorySummary()['never_purchased']);
    }

    public function test_livewire_editor_and_history_render_create_edit_and_filter(): void
    {
        Livewire::test(PurchaseEditor::class)->call('open')->set('form', collect($this->input)->except('operation_key')->all())->call('save')->assertHasNoErrors()->assertDispatched('purchase-saved');
        $purchase = Purchase::firstOrFail();
        Livewire::test(MealPurchases::class)->assertSee('Historial de compras')->assertSee('D1')->set('storeId', (string) Str::uuid())->assertSee('No hay compras registradas');
        Livewire::test(MealPurchaseShow::class, ['purchase' => $purchase->id])->assertSee('LECHE TET')->assertSee('Total pagado');
        Livewire::test(PurchaseEditor::class)->call('open', $purchase->id)->assertSet('form.lines.0.packages', 2.0)->set('form.notes', 'Corregida')->call('save')->assertHasNoErrors();
        $this->assertSame('Corregida', $purchase->fresh()->notes);
        Livewire::test(MealShopping::class)->assertSee('Registrar compra');
    }

    public function test_variant_change_recalculates_stock_and_rejects_unrelated_line_ids(): void
    {
        $purchase = $this->save();
        $variant = $this->item->variants()->create(['content' => 250]);
        $input = $this->input;
        $input['lines'][0]['id'] = $purchase->lines->first()->id;
        $input['lines'][0]['shopping_item_variant_id'] = $variant->id;
        $edited = $this->save($input, $purchase->id);
        $this->assertSame(600.0, $this->item->fresh()->stock);
        $this->assertSame($variant->id, $edited->lines->first()->price->shopping_item_variant_id);
        $input['lines'][0]['id'] = (string) Str::uuid();
        try {
            $this->save($input, $purchase->id);
            $this->fail('Expected validation');
        } catch (ValidationException) {
        }
        $this->assertSame(600.0, $this->item->fresh()->stock);
    }

    public function test_store_merge_rejects_conflicting_ticket_references(): void
    {
        $this->input['ticket_reference'] = 'REF-1';
        $purchase = $this->save();
        $target = Store::create(['name' => 'Ara']);
        $input = $this->input;
        $input['store_id'] = $target->id;
        $input['operation_key'] = (string) Str::uuid();
        $this->save($input);
        try {
            $this->store->mergeInto($target);
            $this->fail('Expected validation');
        } catch (ValidationException) {
        }
        $this->assertSame($this->store->id, $purchase->fresh()->store_id);
        $this->assertNotNull($this->store->fresh());
    }

    public function test_catalog_editor_preserves_ticket_prices_and_blocks_referenced_variant_removal(): void
    {
        $purchase = $this->save();
        $price = $purchase->lines->first()->price;
        Livewire::test(MealIngredients::class)
            ->call('openForm', $this->item->id)
            ->assertSee('Última compra')
            ->set('prices', [])
            ->call('save')->assertHasNoErrors();
        $this->assertSame('10.00', $price->fresh()->amount);
        Livewire::test(MealIngredients::class)
            ->call('openForm', $this->item->id)->set('variants', [])->call('save')->assertHasErrors('variants');
        $this->assertSame(1, $this->item->variants()->count());
    }

    public function test_fractional_stock_rounding_matches_existing_stock_column_and_remains_reversible(): void
    {
        $variant = $this->item->variants()->first();
        $variant->update(['content' => 1]);
        $this->input['lines'][0]['packages'] = 0.333333;
        $purchase = $this->save();
        $this->assertSame(100.333, $this->item->fresh()->stock);
        $this->assertSame(0.333, $purchase->lines->first()->stock_added);
        $input = $this->input;
        $input['lines'][0]['id'] = $purchase->lines->first()->id;
        $input['lines'][0]['packages'] = 0.1;
        $this->save($input, $purchase->id);
        $this->assertSame(100.1, $this->item->fresh()->stock);
    }

    public function test_invoice_route_is_owned_and_requires_authentication(): void
    {
        $purchase = $this->save();
        $this->get(route('meals.purchases.show', $purchase->id))->assertOk()->assertSee('Comprobante de compra');
        $this->get(route('meals.purchases.show', (string) Str::uuid()))->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->get(route('meals.purchases.show', $purchase->id))->assertNotFound();
    }

    public function test_invoice_requires_authentication(): void
    {
        auth()->logout();
        $this->get(route('meals.purchases.show', (string) Str::uuid()))->assertRedirect(route('login'));
    }

    public function test_history_search_combines_with_filters_and_resets_pagination(): void
    {
        $this->input['ticket_reference'] = 'TICKET-UNICO';
        $purchase = $this->save();
        foreach (['D1', 'TICKET-UNICO', 'Leche'] as $term) {
            Livewire::test(MealPurchases::class)->set('search', $term)
                ->assertViewHas('purchases', fn ($rows) => $rows->total() === 1)
                ->set('from', '2026-09-02')->assertViewHas('purchases', fn ($rows) => $rows->total() === 0);
        }
        Livewire::test(MealPurchases::class)->call('gotoPage', 2)->set('search', 'Leche')
            ->assertViewHas('purchases', fn ($rows) => $rows->currentPage() === 1);
        $this->actingAs(User::factory()->create());
        Livewire::test(MealPurchases::class)->set('search', 'Leche')->assertDontSee($purchase->ticket_reference);
    }

    public function test_invoice_preserves_snapshots_null_prices_and_fractional_packages(): void
    {
        $this->input['lines'][0]['packages'] = 1.5;
        $purchase = $this->save();
        $this->item->update(['name' => 'Nombre nuevo']);
        Livewire::test(MealPurchaseShow::class, ['purchase' => $purchase->id])
            ->assertSee('Leche')->assertDontSee('Nombre nuevo')->assertSee('1,5 paquetes')
            ->assertSee('$ 15,00')->assertSee('$ 999,00')
            ->assertSee(route('meals.ingredients', ['ingredient' => $this->item->id]), false);
        $purchase->lines()->first()->update(['unit_price' => null]);
        $purchase->update(['total_paid' => null]);
        Livewire::test(MealPurchaseShow::class, ['purchase' => $purchase->id])
            ->assertSee('Suma de ítems (incompleta)')->assertSee('Sin registrar');
    }

    public function test_invoice_refreshes_after_edit_and_restores_history_state(): void
    {
        $purchase = $this->save();
        $component = Livewire::test(MealPurchaseShow::class, ['purchase' => $purchase->id])
            ->set('history', ['q' => 'Leche', 'page' => 2, 'per_page' => 10, 'from' => '2026-09-01', 'redirect' => 'https://example.com'])
            ->assertViewHas('backUrl', route('meals.purchases', ['q' => 'Leche', 'page' => 2, 'per_page' => 10, 'from' => '2026-09-01']));
        Livewire::test(PurchaseEditor::class)->call('open', $purchase->id)
            ->set('form.notes', 'Nueva nota de factura')->call('save')->assertHasNoErrors();
        $component->dispatch('purchase-saved')->assertSee('Nueva nota de factura');
        $this->assertSame(2100.0, $this->item->fresh()->stock);
    }

    public function test_ingredient_destination_filters_by_owned_id_and_can_be_cleared(): void
    {
        $other = ShoppingItem::create(['name' => 'Otro ingrediente', 'base_unit' => 'g', 'stock' => 0, 'status' => 'available']);
        Livewire::test(MealIngredients::class)->set('ingredientId', $this->item->id)
            ->assertViewHas('ingredients', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $this->item->id)
            ->call('clearFilters')->assertViewHas('ingredients', fn ($rows) => $rows->total() === 2);
        $this->actingAs(User::factory()->create());
        Livewire::test(MealIngredients::class)->set('ingredientId', $other->id)
            ->assertViewHas('ingredients', fn ($rows) => $rows->total() === 0);
    }
}
