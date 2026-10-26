<?php

namespace Tests\Feature\Meal;

use App\Livewire\Meal\MealIngredients;
use App\Livewire\Meal\MealSettings;
use App\Livewire\Meal\MealShopping;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Shopping\AddShoppingItemTool;
use App\Models\MealCategory;
use App\Models\ShoppingItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class MealSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_defaults_are_created_for_new_users_and_settings_routes_work(): void
    {
        $this->assertEqualsCanonicalizing(array_values(ShoppingItem::CATEGORIES), array_values(MealCategory::options()));
        $this->get(route('meals.settings'))->assertOk()->assertSee('Tiendas');
        $this->get(route('meals.settings.categories'))->assertOk()->assertSee('Categorías de productos');
    }

    public function test_category_crud_preserves_keys_rejects_duplicates_and_protects_used_categories(): void
    {
        $component = Livewire::test(MealSettings::class, ['section' => 'categories']);
        $component->call('openForm')->set('name', 'Suplementos')->call('save')->assertHasNoErrors()->assertSet('showForm', false);
        $category = MealCategory::where('name', 'Suplementos')->firstOrFail();
        $item = ShoppingItem::create(['name' => 'Proteína', 'category_id' => $category->id, 'base_unit' => 'g', 'stock' => 0, 'status' => 'available']);
        $component->call('openForm', $category->id)->set('name', 'Nutrición')->call('save')->assertHasNoErrors();
        $this->assertSame($category->id, $item->fresh()->category_id);
        $this->assertSame('Nutrición', MealCategory::options()[$category->id]);
        $component->call('openForm')->set('name', ' NUTRICION ')->call('save')->assertHasErrors('name');
        $component->call('closeForm')->call('delete', $category->id)->assertSee('no se puede eliminar');
        $item->update(['category_id' => null]);
        $component->call('delete', $category->id);
        $this->assertDatabaseMissing('meal_categories', ['id' => $category->id]);
    }

    public function test_default_category_rename_and_delete_remain_after_reload(): void
    {
        $category = MealCategory::where('key', 'otros')->firstOrFail();
        Livewire::test(MealSettings::class, ['section' => 'categories'])->call('openForm', $category->id)->set('name', 'Varios')->call('save')->assertHasNoErrors();
        $this->assertSame('Varios', MealCategory::options()[$category->id]);
        Livewire::test(MealSettings::class, ['section' => 'categories'])->call('delete', $category->id);
        $this->assertArrayNotHasKey($category->id, MealCategory::options());
    }

    public function test_categories_are_available_in_ingredients_shopping_import_and_mcp(): void
    {
        $category = MealCategory::create(['key' => 'custom', 'name' => 'Categoría propia', 'normalized_name' => 'categoria propia']);
        Livewire::test(MealIngredients::class)->call('openForm')->set('name', 'Producto propio')->set('baseUnit', 'g')->set('categoryId', $category->id)->call('save')->assertHasNoErrors();
        Livewire::test(MealShopping::class)->call('openForm')->set('itemName', 'Otro producto')->set('itemBaseUnit', 'g')->set('itemCategory', $category->id)->call('save')->assertHasNoErrors();
        LifeTrackerServer::actingAs(auth()->user())->tool(AddShoppingItemTool::class, ['name' => 'Producto MCP', 'base_unit' => 'g', 'category_id' => $category->id])->assertOk();
        $this->assertDatabaseHas('shopping_items', ['name' => 'Producto MCP', 'category_id' => $category->id]);
        app(\App\Services\Meal\IngredientImportService::class)->apply([[
            'action' => 'create', 'new_name' => 'Producto importado', 'category_id' => $category->id,
            'quantity' => null, 'unit' => 'g', 'normalized' => 'producto importado', 'term' => 'Producto importado',
        ]], 'ingredients');
        $this->assertDatabaseHas('shopping_items', ['name' => 'Producto importado', 'category_id' => $category->id]);
    }

    public function test_catalogs_and_writes_are_isolated_per_user(): void
    {
        $category = MealCategory::create(['key' => 'private', 'name' => 'Categoría privada', 'normalized_name' => 'categoria privada']);
        $store = Store::create(['name' => 'Tienda privada']);
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->assertArrayNotHasKey($category->id, MealCategory::options());
        Livewire::test(MealSettings::class)->assertDontSee('Tienda privada')->call('openForm', $store->id)->assertNotFound();
        Livewire::test(MealSettings::class, ['section' => 'categories'])->assertDontSee('Categoría privada')->call('delete', $category->id)->assertNotFound();
        Livewire::test(MealShopping::class)->call('openForm')->set('itemName', 'No válido')->set('itemBaseUnit', 'g')->set('itemCategory', $category->id)->call('save')->assertHasErrors('itemCategory');
        $this->assertDatabaseHas('meal_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('stores', ['id' => $store->id]);
    }

    public function test_store_crud_similar_names_and_deletion_protection(): void
    {
        $component = Livewire::test(MealSettings::class)->call('openForm')->set('name', 'Éxito')->call('save')->assertHasNoErrors();
        $store = Store::where('name', 'Éxito')->firstOrFail();
        $component->call('openForm')->set('name', 'exito')->call('save')->assertHasErrors('name');
        $component->set('name', 'Éxito Norte')->call('save')->assertSet('confirmSimilar', true)->call('save')->assertHasNoErrors();
        $component->call('openForm', $store->id)->set('name', 'Mercado principal')->call('save')->assertHasNoErrors();
        $item = ShoppingItem::create(['name' => 'Arroz', 'base_unit' => 'g', 'stock' => 0, 'status' => 'available']);
        $item->variants()->create(['content' => 1000])->prices()->create(['store_id' => $store->id, 'amount' => 5000, 'observed_on' => today(), 'source' => 'manual']);
        $component->call('delete', $store->id)->assertSee('precios o compras registrados');
        $this->assertDatabaseHas('stores', ['id' => $store->id]);
        $empty = Store::create(['name' => 'Vacía']);
        $component->call('delete', $empty->id);
        $this->assertDatabaseMissing('stores', ['id' => $empty->id]);
    }

    public function test_store_merge_transfers_prices_and_rejects_invalid_targets(): void
    {
        $source = Store::create(['name' => 'Origen']);
        $target = Store::create(['name' => 'Destino']);
        $foreign = Store::withoutEvents(fn () => Store::forceCreate(['user_id' => User::factory()->create()->id, 'name' => 'Ajena', 'normalized_name' => 'ajena']));
        $item = ShoppingItem::create(['name' => 'Arroz', 'base_unit' => 'g', 'stock' => 0, 'status' => 'available']);
        $price = $item->variants()->create(['content' => 1000])->prices()->create(['store_id' => $source->id, 'amount' => 5000, 'observed_on' => today(), 'source' => 'manual']);
        Livewire::test(MealSettings::class)->call('openMerge', $source->id)->set('mergeTargetId', $source->id)->call('merge')->assertHasErrors('mergeTargetId')
            ->set('mergeTargetId', $foreign->id)->call('merge')->assertHasErrors('mergeTargetId')
            ->set('mergeTargetId', $target->id)->call('merge')->assertHasNoErrors()->assertSet('showMerge', false);
        $this->assertSame($target->id, $price->fresh()->store_id);
        $this->assertDatabaseMissing('stores', ['id' => $source->id]);
    }

    public function test_search_and_pagination_reset(): void
    {
        Store::create(['name' => 'Tienda única']);
        Livewire::test(MealSettings::class)->call('gotoPage', 2)->set('search', 'única')
            ->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->currentPage() === 1)
            ->set('search', 'inexistente')->assertViewHas('records', fn ($rows) => $rows->total() === 0);
    }

    public function test_migration_preserves_existing_classification_per_user_and_can_roll_back(): void
    {
        $user = auth()->user();
        $other = User::factory()->create();
        $migration = require database_path('migrations/2026_10_12_000001_create_meal_categories_table.php');
        $migration->down();
        $rows = [];
        foreach ([$user, $other] as $owner) {
            foreach (['lacteos', 'mi_categoria', null, ''] as $key) {
                $id = (string) \Illuminate\Support\Str::uuid();
                \Illuminate\Support\Facades\DB::table('shopping_items')->insert([
                    'id' => $id, 'user_id' => $owner->id, 'name' => 'Legado', 'category' => $key,
                    'base_unit' => 'g', 'stock' => 0, 'status' => 'available', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $rows[] = [$id, $owner->id, $key];
            }
        }
        $migration->up();
        $this->assertFalse(Schema::hasColumn('shopping_items', 'category'));
        foreach ($rows as [$id, $ownerId, $key]) {
            $item = ShoppingItem::withoutGlobalScopes()->findOrFail($id);
            if ($key === null || $key === '') {
                $this->assertNull($item->category_id);
            } else {
                $category = MealCategory::withoutGlobalScopes()->findOrFail($item->category_id);
                $this->assertSame($ownerId, $category->user_id);
                $this->assertSame($key, $category->key);
                $this->assertSame($key === 'lacteos' ? 'Lácteos y huevos' : $key, $category->name);
            }
        }
        $migration->down();
        foreach ($rows as [$id, $ownerId, $key]) {
            $this->assertDatabaseHas('shopping_items', ['id' => $id, 'category' => $key ?: null]);
        }
        $migration->up();
    }

    public function test_model_rejects_a_category_owned_by_another_user(): void
    {
        $category = MealCategory::firstOrFail();
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        ShoppingItem::create(['name' => 'Inválido', 'category_id' => $category->id]);
    }

    public function test_database_rejects_category_associations_between_users(): void
    {
        $category = MealCategory::firstOrFail();
        $other = User::factory()->create();
        $this->actingAs($other);
        $item = ShoppingItem::create(['name' => 'Sin categoría', 'status' => 'active']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('shopping_items')->where('id', $item->id)->update(['category_id' => $category->id]);
    }
}
