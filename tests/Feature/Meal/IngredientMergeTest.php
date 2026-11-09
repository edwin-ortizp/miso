<?php

namespace Tests\Feature\Meal;

use App\Livewire\Meal\MealIngredients;
use App\Models\MealInventoryMovement;
use App\Models\PurchaseLine;
use App\Models\Recipe;
use App\Models\ShoppingItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Meal\IngredientMergeService;
use App\Services\Meal\MealInventory;
use App\Services\Meal\PurchaseRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IngredientMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suggested_target_with_projected_columns_is_reloaded_with_ownership_under_lock(): void
    {
        $this->actingAs(User::factory()->create());
        $target = $this->product('Fresa', ['stock' => 100, 'base_unit' => 'g']);
        $source = $this->product('Fresa congelada', ['stock' => 500, 'base_unit' => 'g']);
        $suggestion = IngredientMergeService::suggestedTarget($source);
        $this->assertNull($suggestion->user_id);
        app(IngredientMergeService::class)->merge($source, $suggestion, 'congelada');
        $this->assertSame(600.0, $target->fresh()->stock);
        $this->assertModelMissing($source);
    }

    public function test_merge_preserves_original_identity_and_allows_meal_reversal_and_purchase_edit_afterwards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $source = $this->product('Producto origen', ['stock' => 4]);
        $target = $this->product('Producto destino', ['stock' => 2]);
        $purchase = app(PurchaseRecorder::class)->record($source, null, 6)['purchase'];
        $meal = app(MealInventory::class);
        $mealId = $meal->plan($user->id, ['date' => '2026-11-02', 'meal_type' => 'cena', 'items' => [['name' => 'Comida', 'ingredients' => [['shopping_item_id' => $source->id, 'quantity' => 3]]]]])['meal_id'];
        $consumed = $meal->consume($user->id, $mealId);
        $original = MealInventoryMovement::where('operation_id', $consumed['operation_id'])->sole();
        $snapshot = $original->only(['historical_product_id', 'name', 'unit', 'delta', 'balance_before', 'balance_after']);
        app(IngredientMergeService::class)->merge($source, $target, null);
        $this->assertSame(9.0, $target->fresh()->stock);
        $this->assertNull($original->fresh()->shopping_item_id);
        $this->assertSame($snapshot, $original->fresh()->only(array_keys($snapshot)));
        $meal->revert($user->id, $mealId);
        $this->assertSame(12.0, $target->fresh()->stock);
        $line = $purchase->fresh('lines')->lines->sole();
        app(PurchaseRecorder::class)->save(['purchased_at' => $purchase->purchased_at->toDateTimeString(), 'operation_key' => (string) Str::uuid(),
            'lines' => [['id' => $line->id, 'shopping_item_id' => $target->id, 'packages' => 5]]], $purchase->id);
        $this->assertSame(11.0, $target->fresh()->stock);
        $this->assertSame($snapshot, $original->fresh()->only(array_keys($snapshot)));
    }

    public function test_merge_rejects_other_users_products_even_without_an_authenticated_scope(): void
    {
        $this->actingAs(User::factory()->create());
        $source = $this->product('Propio', ['stock' => 1]);
        $this->actingAs(User::factory()->create());
        $target = $this->product('Ajeno', ['stock' => 1]);
        auth()->logout();
        $this->expectException(ValidationException::class);
        app(IngredientMergeService::class)->merge($source, $target, null);
    }

    private function product(string $name, array $attributes = []): ShoppingItem
    {
        return ShoppingItem::create($attributes + ['name' => $name, 'base_unit' => 'unit', 'status' => 'available', 'stock' => 0, 'to_buy' => 0]);
    }

    public function test_converting_a_product_into_a_variant_moves_everything_and_keeps_its_name_as_alias(): void
    {
        $this->actingAs(User::factory()->create());
        $store = Store::create(['name' => 'D1']);
        $target = $this->product('Aromática', ['stock' => 1]);
        $source = $this->product('Aromática de manzanilla', ['stock' => 2, 'next_purchase' => true]);
        $variant = $source->variants()->create(['content' => 20]);
        $variant->prices()->create(['store_id' => $store->id, 'amount' => 3500, 'observed_on' => '2026-10-01', 'source' => 'manual']);
        app(PurchaseRecorder::class)->save(['operation_key' => (string) Str::uuid(), 'store_id' => $store->id, 'purchased_at' => '2026-10-01 10:00:00',
            'lines' => [['shopping_item_id' => $source->id, 'shopping_item_variant_id' => $variant->id, 'packages' => 1, 'unit_price' => 3500]]]);
        ShoppingItem::whereKey($source->id)->update(['next_purchase' => true]); // La compra lo saca de la lista; se vuelve a agregar.
        $recipe = Recipe::create(['name' => 'Infusión', 'servings' => 1, 'meal_type' => 'cena']);
        $recipe->recipeIngredients()->create(['shopping_item_id' => $source->id, 'quantity' => 1, 'unit' => 'unit']);

        Livewire::test(MealIngredients::class)
            ->call('openMerge', $source->id)
            ->assertSet('mergeTargetId', $target->id)
            ->assertSet('mergeFlavor', 'manzanilla')
            ->assertSee('Convertir en variante')
            ->call('confirmMerge')
            ->assertHasNoErrors()
            ->assertSet('mergeSourceId', null);

        $this->assertModelMissing($source);
        $target->refresh();
        $this->assertSame(23.0, $target->stock); // 1 + 2 + 20 de la compra registrada
        $this->assertTrue($target->next_purchase);
        $this->assertSame('manzanilla', $variant->fresh()->flavor);
        $this->assertSame($target->id, $variant->fresh()->shopping_item_id);
        $this->assertSame(2, $target->prices()->count()); // El precio manual y el del ticket
        $this->assertSame($target->id, PurchaseLine::first()->shopping_item_id);
        $this->assertSame($target->id, $recipe->recipeIngredients()->first()->shopping_item_id);
        $this->assertSame(['Aromática de manzanilla'], $target->aliases()->pluck('alias')->all());
    }

    public function test_products_with_different_base_units_cannot_be_combined(): void
    {
        $this->actingAs(User::factory()->create());
        $target = $this->product('Fresa', ['base_unit' => 'g']);
        $source = $this->product('Fresa congelada');

        Livewire::test(MealIngredients::class)
            ->call('openMerge', $source->id)
            ->assertSet('mergeTargetId', $target->id)
            ->assertSet('mergeFlavor', 'congelada')
            ->call('confirmMerge')
            ->assertHasErrors('mergeTargetId');

        $this->assertModelExists($source);
    }

    public function test_recipes_using_both_products_add_their_quantities(): void
    {
        $this->actingAs(User::factory()->create());
        $target = $this->product('Fríjol');
        $source = $this->product('Fríjol en lata');
        $recipe = Recipe::create(['name' => 'Bandeja', 'servings' => 1, 'meal_type' => 'comida']);
        $recipe->recipeIngredients()->create(['shopping_item_id' => $target->id, 'quantity' => 1, 'unit' => 'unit']);
        $recipe->recipeIngredients()->create(['shopping_item_id' => $source->id, 'quantity' => 2, 'unit' => 'unit']);

        app(IngredientMergeService::class)->merge($source, $target, 'en lata', false);

        $this->assertSame(1, $recipe->recipeIngredients()->count());
        $this->assertEquals(3, $recipe->recipeIngredients()->first()->quantity);
        $this->assertSame(1, $target->variants()->where('flavor', 'en lata')->count());
        $this->assertSame(0, $target->aliases()->count());
    }

    public function test_suggestions_come_from_the_longest_matching_name(): void
    {
        $this->assertSame('infantil', IngredientMergeService::suggestedFlavor('Enjuague bucal infantil', 'Enjuague bucal'));
        $this->assertSame('', IngredientMergeService::suggestedFlavor('Arroz', 'Aromática'));
    }
}
