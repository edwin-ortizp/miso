<?php

namespace Tests\Feature;

use App\Livewire\Meal\MealPreparations;
use App\Livewire\Meal\MealWeekly;
use App\Models\MealInventoryMovement;
use App\Models\MealInventoryOperation;
use App\Models\MealPlanEntry;
use App\Models\MealPreparation;
use App\Models\Recipe;
use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\InventoryLedger;
use App\Services\Meal\MealInventory;
use App\Services\Meal\MealNeeds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MealInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_legacy_consumption_can_be_reversed_after_opening_without_replaying_old_history(): void
    {
        $product = $this->product('Huevos', 'unit', 6);
        $original = MealInventoryOperation::create(['user_id' => $this->owner->id, 'operation_key' => 'legacy-consumption', 'action' => 'consume', 'fingerprint' => str_repeat('a', 64)]);
        $movement = $original->movements()->create(['user_id' => $this->owner->id, 'shopping_item_id' => $product->id, 'name' => 'Huevos', 'unit' => 'unit', 'delta' => -2]);
        $entry = MealPlanEntry::create(['user_id' => $this->owner->id, 'date' => '2026-11-02', 'meal_type' => 'cena', 'status' => 'consumed', 'consumption_mode' => 'home', 'consumption_operation_id' => $original->id]);
        app(InventoryLedger::class)->initialize($this->owner->id, [$product->id]);
        $result = $this->inventory->revert($this->owner->id, $entry->id, 'reverse-legacy');
        $this->assertSame(8.0, $product->fresh()->stock);
        $this->assertSame(-2.0, $movement->fresh()->delta);
        $this->assertNull($movement->fresh()->balance_after);
        $compensation = MealInventoryMovement::where('operation_id', $result['operation_id'])->sole();
        $this->assertSame($movement->id, $compensation->compensates_movement_id);
        $this->assertSame('6.000', $compensation->balance_before);
        $this->assertSame('8.000', $compensation->balance_after);
    }

    private User $owner;

    private MealInventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);
        $this->inventory = app(MealInventory::class);
    }

    private function product(string $name = 'Leche', string $unit = 'ml', float $stock = 1000): ShoppingItem
    {
        return ShoppingItem::create(['name' => $name, 'base_unit' => $unit, 'stock' => $stock, 'status' => 'available']);
    }

    private function recipe(ShoppingItem $product): Recipe
    {
        $recipe = Recipe::create(['name' => 'Sopa', 'servings' => 6, 'meal_type' => 'cena', 'nutrition' => ['calories' => 100]]);
        $recipe->recipeIngredients()->create(['shopping_item_id' => $product->id, 'quantity' => 600, 'unit' => $product->base_unit]);

        return $recipe;
    }

    private function plan(array $items, string $date = '2026-10-05', string $type = 'cena'): int
    {
        return $this->inventory->plan($this->owner->id, ['date' => $date, 'meal_type' => $type, 'items' => $items])['meal_id'];
    }

    private function rejected(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected a rejected inventory operation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, implode(' ', $exception->validator->errors()->all()));
        }
    }

    public function test_linked_free_meal_aggregates_converts_and_reverts_exact_amounts(): void
    {
        $milk = $this->product();
        $cake = $this->product('Torta', 'g', 500);
        $id = $this->plan([['name' => 'Leche con torta', 'calories' => 300, 'ingredients' => [
            ['shopping_item_id' => $milk->id, 'quantity' => 0.2, 'unit' => 'L'],
            ['shopping_item_id' => $milk->id, 'quantity' => 50, 'unit' => 'ml'],
            ['shopping_item_id' => $cake->id, 'quantity' => 50, 'unit' => 'g'],
        ]]]);
        $this->assertSame(1000.0, $milk->fresh()->stock);
        $result = $this->inventory->consume($this->owner->id, $id, [], 'consume-test');
        $this->assertSame($result, $this->inventory->consume($this->owner->id, $id, [], 'consume-test'));
        $this->assertSame(750.0, $milk->fresh()->stock);
        $this->assertSame(450.0, $cake->fresh()->stock);
        $this->assertSame(2, MealInventoryMovement::where('movement_type', 'consume')->count());
        $this->assertSame(2, MealInventoryMovement::where('movement_type', 'opening')->count());
        $this->inventory->revert($this->owner->id, $id, 'revert-test');
        $this->assertSame(1000.0, $milk->fresh()->stock);
        $this->assertSame(500.0, $cake->fresh()->stock);
        $this->assertSame('planned', MealPlanEntry::find($id)->status);
    }

    public function test_six_prepared_portions_reserved_on_three_days_debit_ingredients_once(): void
    {
        $milk = $this->product();
        $recipe = $this->recipe($milk);
        $result = $this->inventory->cook($this->owner->id, ['recipe_id' => $recipe->id, 'portions' => 6], 'cook-once');
        $this->assertSame($result, $this->inventory->cook($this->owner->id, ['recipe_id' => $recipe->id, 'portions' => 6], 'cook-once'));
        $prep = MealPreparation::findOrFail($result['preparation_id']);
        $ids = [];
        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $date) {
            $ids[] = $this->plan([['preparation_id' => $prep->id, 'portions' => 2]], $date);
        }
        $this->assertSame(400.0, $milk->fresh()->stock);
        $this->assertSame(6.0, $prep->reserved());
        $this->assertSame(0.0, $prep->summary()['available']);
        $this->rejected(fn () => $this->plan([['preparation_id' => $prep->id]], '2026-10-08'), 'porciones suficientes');
        $this->assertDatabaseCount('meal_plan_entries', 3);
        foreach ($ids as $id) {
            $this->inventory->consume($this->owner->id, $id);
        }
        $this->assertSame(0.0, $prep->remaining());
        $this->assertSame(0.0, $prep->reserved());
        $this->assertSame(400.0, $milk->fresh()->stock);
        $this->inventory->revert($this->owner->id, $ids[0]);
        $this->assertSame(2.0, $prep->remaining());
        $this->assertSame(2.0, $prep->reserved());
        $this->assertSame(400.0, $milk->fresh()->stock);
    }

    public function test_outside_consumption_preserves_plan_and_releases_reservation(): void
    {
        $product = $this->product();
        $recipe = $this->recipe($product);
        $cooked = $this->inventory->cook($this->owner->id, ['recipe_id' => $recipe->id, 'portions' => 6]);
        $prep = MealPreparation::find($cooked['preparation_id']);
        $id = $this->plan([['preparation_id' => $prep->id, 'portions' => 2]]);
        $this->inventory->consume($this->owner->id, $id, ['mode' => 'outside', 'calories' => 700, 'notes' => 'Restaurante']);
        $entry = MealPlanEntry::with('items')->find($id);
        $this->assertCount(1, $entry->items);
        $this->assertSame(700, $entry->consumption['calories']);
        $this->assertSame(0.0, $prep->reserved());
        $this->assertSame(6.0, $prep->remaining());
        $this->assertSame(400.0, $product->fresh()->stock);
        $this->inventory->revert($this->owner->id, $id);
        $this->assertSame(2.0, $prep->reserved());
        $outside = $this->inventory->consumeAt($this->owner->id, ['date' => '2026-10-06', 'meal_type' => 'almuerzo', 'mode' => 'outside'], 'outside-new');
        $this->assertSame('consumed', MealPlanEntry::find($outside['meal_id'])->status);
        $this->assertSame([], MealPlanEntry::find($outside['meal_id'])->items->all());
    }

    public function test_reverting_outside_is_atomic_when_released_portions_were_reserved_elsewhere(): void
    {
        $recipe = $this->recipe($this->product());
        $prepId = $this->inventory->cook($this->owner->id, ['recipe_id' => $recipe->id, 'portions' => 2])['preparation_id'];
        $first = $this->plan([['preparation_id' => $prepId, 'portions' => 2]]);
        $this->inventory->consume($this->owner->id, $first, ['mode' => 'outside']);
        $this->plan([['preparation_id' => $prepId, 'portions' => 2]], '2026-10-06');
        $this->rejected(fn () => $this->inventory->revert($this->owner->id, $first), 'porciones suficientes');
        $this->assertSame('consumed', MealPlanEntry::find($first)->status);
    }

    public function test_insufficient_stock_rolls_back_all_products_and_operation(): void
    {
        $milk = $this->product();
        $cake = $this->product('Torta', 'g', 5);
        $id = $this->plan([['name' => 'Merienda', 'ingredients' => [
            ['shopping_item_id' => $milk->id, 'quantity' => 250], ['shopping_item_id' => $cake->id, 'quantity' => 50],
        ]]]);
        $before = MealInventoryOperation::count();
        $this->rejected(fn () => $this->inventory->consume($this->owner->id, $id, [], 'failed'), 'faltan 45');
        $this->assertSame(1000.0, $milk->fresh()->stock);
        $this->assertSame(5.0, $cake->fresh()->stock);
        $this->assertDatabaseCount('meal_inventory_movements', 0);
        $this->assertSame($before, MealInventoryOperation::count());
        $this->assertSame('planned', MealPlanEntry::find($id)->status);
    }

    public function test_consumed_meal_is_immutable_and_recipe_edits_do_not_affect_reversal(): void
    {
        $milk = $this->product();
        $recipe = $this->recipe($milk);
        $id = $this->plan([['recipe_id' => $recipe->id, 'portions' => 2]]);
        $this->inventory->consume($this->owner->id, $id);
        $this->assertSame(800.0, $milk->fresh()->stock);
        $recipe->recipeIngredients()->update(['quantity' => 900]);
        $this->rejected(fn () => $this->inventory->plan($this->owner->id, ['date' => '2026-10-05', 'meal_type' => 'cena', 'items' => [['name' => 'Otra cosa']], 'mode' => 'replace']), 'Revierte');
        $this->rejected(fn () => $this->inventory->rearrange($this->owner->id, $id, 'delete'), 'Revierte');
        $this->rejected(fn () => $this->inventory->rearrange($this->owner->id, $id, 'move', ['date' => '2026-10-06', 'meal_type' => 'cena']), 'Revierte');
        $milk->update(['base_unit' => 'g']);
        $this->rejected(fn () => $this->inventory->revert($this->owner->id, $id), 'unidad base');
        $this->assertSame(800.0, $milk->fresh()->stock);
        $milk->update(['base_unit' => 'ml']);
        $this->inventory->revert($this->owner->id, $id);
        $this->assertSame(1000.0, $milk->fresh()->stock);
    }

    public function test_move_copy_swap_and_partial_edit_preserve_other_meals(): void
    {
        $first = $this->plan([['name' => 'Sopa'], ['name' => 'Pan']]);
        $lunch = $this->plan([['name' => 'Almuerzo']], '2026-10-05', 'almuerzo');
        $target = $this->plan([['name' => 'Ensalada']], '2026-10-06');
        $items = $this->inventory->entry($this->owner->id, $first)->items;
        $this->inventory->plan($this->owner->id, ['entry_id' => $first, 'date' => '2026-10-05', 'meal_type' => 'cena', 'mode' => 'replace', 'items' => [
            ['id' => $items[0]->id, 'name' => 'Sopa corregida'], ['id' => $items[1]->id, 'name' => 'Pan'],
        ]]);
        $copy = $this->inventory->rearrange($this->owner->id, $first, 'copy', ['date' => '2026-10-07', 'meal_type' => 'cena'])['meal_id'];
        $this->inventory->rearrange($this->owner->id, $first, 'move', ['date' => '2026-10-06', 'meal_type' => 'cena']);
        $this->assertNull(MealPlanEntry::find($first));
        $this->assertSame(['Ensalada', 'Sopa corregida', 'Pan'], MealPlanEntry::find($target)->items->pluck('name')->all());
        $this->inventory->rearrange($this->owner->id, $target, 'swap', ['date' => '2026-10-07', 'meal_type' => 'cena']);
        $this->assertCount(2, MealPlanEntry::find($target)->items);
        $this->assertCount(3, MealPlanEntry::find($copy)->items);
        $this->assertSame('Almuerzo', MealPlanEntry::find($lunch)->items->sole()->name);
    }

    public function test_edit_delete_and_copy_validate_preparation_reservations(): void
    {
        $prepId = $this->inventory->cook($this->owner->id, ['recipe_id' => $this->recipe($this->product())->id, 'portions' => 2])['preparation_id'];
        $id = $this->plan([['preparation_id' => $prepId, 'portions' => 2]]);
        $this->rejected(fn () => $this->inventory->rearrange($this->owner->id, $id, 'copy', ['date' => '2026-10-06', 'meal_type' => 'cena']), 'porciones suficientes');
        $this->assertDatabaseCount('meal_plan_entries', 1);
        $this->rejected(fn () => $this->inventory->cancelPreparation($this->owner->id, $prepId), 'sin consumos ni reservas');
        $this->inventory->rearrange($this->owner->id, $id, 'delete');
        $this->assertSame(0.0, MealPreparation::find($prepId)->reserved());
        $this->inventory->cancelPreparation($this->owner->id, $prepId);
        $this->assertTrue(MealPreparation::find($prepId)->cancelled);
        $this->assertSame(1000.0, ShoppingItem::sole()->stock);
    }

    public function test_users_and_operation_payloads_are_isolated(): void
    {
        $id = $this->plan([['name' => 'Texto libre']]);
        $result = $this->inventory->consume($this->owner->id, $id, [], 'stable');
        $this->assertNotEmpty($result['warnings']);
        $this->rejected(fn () => $this->inventory->consume($this->owner->id, $id, ['mode' => 'outside'], 'stable'), 'otros datos');
        $other = User::factory()->create();
        $this->rejected(fn () => $this->inventory->consume($other->id, $id), 'no te pertenece');
        $product = $this->product();
        $this->rejected(fn () => $this->inventory->normalizeItems($other->id, [['name' => 'Ajeno', 'ingredients' => [['shopping_item_id' => $product->id, 'quantity' => 1]]]]), 'no te pertenece');
    }

    public function test_needs_suggestions_and_shopping_generation_are_repeatable(): void
    {
        $milk = $this->product(stock: 50);
        $milk->update(['consume_by' => today()->addDays(2)]);
        $variant = $milk->variants()->create(['content' => 100, 'is_preferred' => true]);
        $recipe = $this->recipe($milk);
        $this->plan([['recipe_id' => $recipe->id, 'portions' => 2]]);
        $preview = app(MealNeeds::class)->preview($this->owner->id, '2026-10-05', '2026-10-11');
        $this->assertSame(200.0, $preview['needs'][0]['quantity']);
        $this->assertSame(150.0, $preview['needs'][0]['missing']);
        $this->assertSame(2, $preview['needs'][0]['packages']);
        $this->inventory->generateShopping($this->owner->id, '2026-10-05', '2026-10-11');
        $this->inventory->generateShopping($this->owner->id, '2026-10-05', '2026-10-11');
        $this->assertSame(2.0, $milk->fresh()->to_buy);
        $milk->update(['to_buy' => 5]);
        $this->inventory->generateShopping($this->owner->id, '2026-10-05', '2026-10-11', [$milk->id => $variant->id]);
        $this->assertSame(5.0, $milk->fresh()->to_buy);
        $suggestions = app(MealNeeds::class)->suggestions($this->owner->id);
        $this->assertFalse($suggestions['recipes'][0]['can_cook']);
        $this->assertCount(1, $suggestions['expiring_products']);
        $this->assertSame(1, $suggestions['recipes'][0]['expiring_products']);
    }

    public function test_previews_exclude_consumed_and_prepared_meals_and_expose_incomplete_data(): void
    {
        $milk = $this->product();
        $recipe = $this->recipe($milk);
        $prepId = $this->inventory->cook($this->owner->id, ['recipe_id' => $recipe->id, 'portions' => 2])['preparation_id'];
        $this->plan([['preparation_id' => $prepId, 'portions' => 2]]);
        $consumed = $this->plan([['recipe_id' => $recipe->id]], '2026-10-06');
        $this->inventory->consume($this->owner->id, $consumed);
        $this->assertSame([], app(MealNeeds::class)->preview($this->owner->id, '2026-10-05', '2026-10-11')['needs']);
        $this->plan([['recipe_id' => $recipe->id]], '2026-10-07');
        $recipe->recipeIngredients()->update(['unit' => 'pechuga']);
        $preview = app(MealNeeds::class)->preview($this->owner->id, '2026-10-05', '2026-10-11');
        $this->assertNull($preview['needs'][0]['missing']);
        $this->rejected(fn () => $this->inventory->generateShopping($this->owner->id, '2026-10-05', '2026-10-11'), 'incompletas');
    }

    public function test_web_consumption_and_preparation_use_shared_services(): void
    {
        $milk = $this->product();
        Livewire::test(MealWeekly::class)->call('openForm', '2026-10-05', 'cena')->call('addRow')->set('formItems.0.kind', 'ingredient')
            ->set('formItems.0.product_id', $milk->id)->set('formItems.0.quantity', 250)
            ->call('consume', 'home')->assertHasNoErrors()->assertSet('formStatus', 'consumed')->call('revertConsumption')->assertSet('formStatus', 'planned');
        $this->assertSame(1000.0, $milk->fresh()->stock);
        Livewire::test(MealPreparations::class)->call('openForm')->set('recipeId', $this->recipe($milk)->id)->set('portions', 6)->call('save')->assertHasNoErrors();
        $this->assertSame(400.0, $milk->fresh()->stock);
        $this->get('/meals/preparations')->assertOk()->assertSee('Sopa');
        $this->get('/meals/suggestions')->assertOk()->assertSee('Qué puedo cocinar');
    }
}
