<?php

namespace Tests\Feature;

use App\Livewire\Meal\MealIngredients;
use App\Livewire\Meal\MealRecipes;
use App\Livewire\Meal\MealShopping;
use App\Livewire\Meal\MealWeekly;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\ShoppingItem;
use App\Models\ShoppingItemVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Tests\TestCase;

class MealPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_meal_slots_follow_daily_order_without_changing_legacy_identifiers(): void
    {
        $this->actingAs(User::factory()->create());
        $expected = ['desayuno' => 'Desayuno', 'comida' => 'Onces (mañana)', 'almuerzo' => 'Almuerzo', 'merienda' => 'Merienda (tarde)', 'cena' => 'Cena'];
        Livewire::test(MealWeekly::class)->assertSet('mealTypes', $expected);
        Livewire::test(MealRecipes::class)->assertSet('mealTypes', $expected);
        $this->assertSame($expected, \App\Livewire\Home\Dashboard::MEAL_TYPES);
    }

    public function test_recipe_requires_a_positive_quantity_for_each_ingredient(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(MealRecipes::class)
            ->call('openForm')
            ->set('name', 'Sopa de tomate')
            ->call('addIngredient')
            ->set('ingredients.0.name', 'Tomate')
            ->call('save')
            ->assertHasErrors(['ingredients.0.quantity' => 'required'])
            ->assertSee('La cantidad es obligatoria.')
            ->assertSet('showForm', true);

        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('recipe_ingredients', 0);
    }

    public function test_opening_dinner_loads_existing_components_notes_and_calories(): void
    {
        $this->actingAs(User::factory()->create());
        $entry = MealPlanEntry::create(['date' => '2026-07-20', 'meal_type' => 'cena', 'notes' => 'Preparar temprano', 'calories' => 420]);
        $entry->items()->create(['name' => 'Sopa', 'calories' => 420, 'position' => 0]);

        Livewire::test(MealWeekly::class)
            ->call('openForm', '2026-07-20', 'cena')
            ->assertSet('editingId', $entry->id)
            ->assertSet('formNotes', 'Preparar temprano')
            ->assertSet('formCalories', 420)
            ->assertSet('formItems.0.name', 'Sopa')
            ->assertSee('Editar cena')
            ->set('formNotes', 'Nota editada')
            ->call('save')
            ->assertHasNoErrors()
            ->call('openForm', '2026-07-20', 'cena')
            ->assertSet('formNotes', 'Nota editada')
            ->call('closeForm')
            ->call('openForm', '2026-07-20', 'desayuno')
            ->assertSet('editingId', null)
            ->assertSet('formItems', [])
            ->assertSet('formNotes', '');

        $this->assertDatabaseCount('meal_plan_entries', 1);
    }

    public function test_a_meal_can_combine_recipes_and_custom_items_with_calculated_calories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $recipe = Recipe::create([
            'name' => 'Huevos con tomate',
            'meal_type' => 'desayuno',
            'nutrition' => ['calories' => 300],
        ]);

        Livewire::test(MealWeekly::class)
            ->call('openForm', '2026-07-20', 'desayuno')
            ->call('addRow')
            ->set('formItems.0.kind', 'recipe')
            ->set('formItems.0.recipe_id', $recipe->id)
            ->set('formItems.0.portions', 2)
            ->call('addRow')
            ->set('formItems.1.kind', 'free')
            ->set('formItems.1.name', 'Café con leche')
            ->set('formItems.1.calories', 50)
            ->call('save')
            ->assertHasNoErrors();

        $entry = MealPlanEntry::with('items.recipe')->firstOrFail();
        $this->assertCount(2, $entry->items);
        $this->assertSame(650, $entry->effective_calories);
        $this->assertDatabaseHas('meal_plan_entry_items', [
            'meal_plan_entry_id' => $entry->id,
            'recipe_id' => $recipe->id,
            'portions' => 2,
        ]);
        $this->assertDatabaseHas('meal_plan_entry_items', [
            'meal_plan_entry_id' => $entry->id,
            'recipe_id' => null,
            'name' => 'Café con leche',
            'calories' => 50,
        ]);
    }

    public function test_recipe_options_exclude_recipes_used_in_other_rows_and_other_users_recipes(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);
        $selected = Recipe::create(['name' => 'Arepa de casa', 'meal_type' => 'desayuno']);
        $available = Recipe::create(['name' => 'Arepa con huevo', 'meal_type' => 'desayuno', 'favorite' => true]);
        Recipe::withoutEvents(function () use ($otherUser) {
            (new Recipe())->forceFill([
                'id' => (string) str()->uuid(),
                'user_id' => $otherUser->id,
                'name' => 'Arepa privada',
                'meal_type' => 'desayuno',
            ])->save();
        });

        $component = Livewire::test(MealWeekly::class)
            ->call('openForm', '2026-07-20', 'desayuno')
            ->call('addRow')
            ->set('formItems.0.kind', 'recipe')
            ->set('formItems.0.recipe_id', $selected->id)
            ->call('addRow')
            ->set('formItems.1.kind', 'recipe')
            ->assertSee($available->id)
            ->assertSee('Arepa con huevo')
            ->assertDontSee('Arepa privada')
            ->assertSet('formItems.0.name', 'Arepa de casa');

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
        $xpath = new \DOMXPath($document);
        $catalog = fn (int $index) => json_decode($xpath->query('//*[@id="meal-element-'.$index.'"]/..')->item(0)->getAttribute('data-options'), true);
        $this->assertSame([$available->id, $selected->id], array_column($catalog(0), 'value'));
        $this->assertSame([$available->id], array_column($catalog(1), 'value'));
        $this->assertSame('♥ Arepa con huevo', $catalog(1)[0]['label']);
    }

    public function test_shopping_requirements_include_all_recipes_and_multiply_portions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $ingredient = ShoppingItem::create(['name' => 'Tomate', 'base_unit' => 'g', 'status' => 'available', 'stock' => 1000, 'to_buy' => 0]);
        $recipe = Recipe::create(['name' => 'Salsa', 'meal_type' => 'comida', 'servings' => 1]);
        $recipe->recipeIngredients()->create([
            'shopping_item_id' => $ingredient->id,
            'quantity' => 2000,
            'unit' => 'g',
        ]);
        $entry = MealPlanEntry::create(['date' => now()->startOfWeek(), 'meal_type' => 'comida']);
        $entry->items()->create(['recipe_id' => $recipe->id, 'portions' => 2.5, 'position' => 0]);

        Livewire::test(MealShopping::class)
            ->assertViewHas('neededItems', function ($items) use ($ingredient) {
                $needed = $items->first();

                return $needed['shopping_item_id'] === $ingredient->id
                    && $needed['quantity'] === 5000.0
                    && $needed['missing'] === 4000.0;
            });
    }

    public function test_existing_variants_and_prices_load_and_are_updated_without_recreating_them(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $ingredient = ShoppingItem::create(['name' => 'Arroz', 'base_unit' => 'g', 'status' => 'available', 'stock' => 0, 'to_buy' => 0]);
        $variant = $ingredient->variants()->create(['content' => 500]);
        $exito = Store::create(['name' => 'Éxito']);
        $price = $variant->prices()->create(['store_id' => Store::create(['name' => 'D1'])->id, 'amount' => 4500, 'observed_on' => '2026-10-01', 'source' => 'manual']);

        $component = Livewire::test(MealIngredients::class)
            ->call('openForm', $ingredient->id)
            ->assertSet('baseUnitLocked', true)
            ->assertSet('variants.0.id', $variant->id)
            ->assertSet('prices.0.store_id', $price->store_id)
            ->assertSet('prices.0.variant_key', $variant->id)
            ->set('prices.0.amount', '4.700')
            ->call('addVariant')
            ->set('variants.1.brand', 'Diana')
            ->set('variants.1.content', 1)
            ->set('variants.1.content_unit', 'kg')
            ->call('addPrice');
        $component->set('prices.1.variant_key', $component->get('variants.1.key'))
            ->set('prices.1.store_id', $exito->id)
            ->set('prices.1.amount', '5.200')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('shopping_item_prices', ['id' => $price->id, 'amount' => 4700]);
        $this->assertSame(2, ShoppingItemVariant::count());
        $this->assertSame(1000.0, ShoppingItemVariant::whereNotNull('brand_id')->first()->content);
        $this->assertSame(2, Store::count());
    }

    public function test_arriving_with_an_ingredient_shows_it_as_a_removable_filter(): void
    {
        $this->actingAs(User::factory()->create());
        $coffee = ShoppingItem::create(['name' => 'Café', 'base_unit' => 'g', 'status' => 'available', 'stock' => 0, 'to_buy' => 0]);
        ShoppingItem::create(['name' => 'Arroz', 'base_unit' => 'g', 'status' => 'available', 'stock' => 0, 'to_buy' => 0]);

        Livewire::withQueryParams(['ingredient' => $coffee->id])
            ->test(MealIngredients::class)
            ->assertSee('Ingrediente: Café')
            ->assertDontSee('Arroz')
            ->call('removeFilter', 'ingredient')
            ->assertSet('ingredientId', '')
            ->assertDontSee('Ingrediente: Café')
            ->assertSee('Arroz');
    }

    public function test_variant_flavor_is_saved_and_loaded_from_the_ingredient_modal(): void
    {
        $this->actingAs(User::factory()->create());
        $ingredient = ShoppingItem::create(['name' => 'Palomitas', 'base_unit' => 'g', 'status' => 'available', 'stock' => 0, 'to_buy' => 0]);

        Livewire::test(MealIngredients::class)
            ->call('openForm', $ingredient->id)
            ->call('addVariant')->set('variants.0.brand', 'Act II')->set('variants.0.flavor', ' Mantequilla ')->set('variants.0.content', 90)
            ->call('addVariant')->set('variants.1.brand', 'Act II')->set('variants.1.flavor', 'Queso')->set('variants.1.content', 90)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['Mantequilla', 'Queso'], $ingredient->variants()->pluck('flavor')->all());
        Livewire::test(MealIngredients::class)
            ->call('openForm', $ingredient->id)
            ->assertSet('variants.0.flavor', fn ($flavor) => in_array($flavor, ['Mantequilla', 'Queso'], true));
    }

    public function test_removing_a_variant_drops_its_prices_and_prices_require_a_variant(): void
    {
        $this->actingAs(User::factory()->create());
        $ingredient = ShoppingItem::create(['name' => 'Arroz', 'base_unit' => 'g', 'status' => 'available', 'stock' => 0, 'to_buy' => 0]);
        $store = Store::create(['name' => 'D1']);
        $kept = $ingredient->variants()->create(['content' => 500]);
        $removed = $ingredient->variants()->create(['content' => 1000]);
        $removed->prices()->create(['store_id' => $store->id, 'amount' => 9000, 'observed_on' => '2026-10-01', 'source' => 'manual']);

        Livewire::test(MealIngredients::class)
            ->call('openForm', $ingredient->id)
            ->assertCount('prices', 1)
            ->call('removeVariant', 1)
            ->assertCount('prices', 0)
            ->call('addPrice')
            ->assertSet('prices.0.variant_key', $kept->id)
            ->set('prices.0.variant_key', '')
            ->set('prices.0.store_id', $store->id)
            ->set('prices.0.amount', '3.500')
            ->call('save')
            ->assertHasErrors('prices.0.variant_key')
            ->set('prices.0.variant_key', $kept->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $ingredient->variants()->count());
        $this->assertDatabaseHas('shopping_item_prices', ['shopping_item_variant_id' => $kept->id, 'amount' => 3500]);
    }

    public function test_manual_calorie_override_can_be_reset_to_the_component_calculation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $recipe = Recipe::create(['name' => 'Avena', 'meal_type' => 'desayuno', 'nutrition' => ['calories' => 200]]);

        Livewire::test(MealWeekly::class)
            ->call('openForm', '2026-07-20', 'desayuno')
            ->call('addRow')
            ->set('formItems.0.kind', 'recipe')
            ->set('formItems.0.recipe_id', $recipe->id)
            ->set('formCalories', 275)
            ->call('useCalculatedCalories')
            ->assertSet('formCalories', null)
            ->call('save');

        $entry = MealPlanEntry::with('items.recipe')->firstOrFail();
        $this->assertNull($entry->calories);
        $this->assertSame(200, $entry->effective_calories);
    }

    public function test_meal_components_cannot_link_another_users_entry_or_recipe(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerEntry = $owner->mealPlanEntries()->create(['date' => '2026-07-20', 'meal_type' => 'cena']);
        $otherRecipe = $other->recipes()->create(['name' => 'Receta ajena', 'meal_type' => 'cena']);
        $this->actingAs($other);

        $this->expectException(QueryException::class);
        $ownerEntry->items()->create([
            'recipe_id' => $otherRecipe->id,
            'portions' => 1,
            'position' => 0,
        ]);
    }
}
