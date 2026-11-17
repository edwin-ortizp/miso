<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Support\ToolCatalog;
use App\Mcp\Tools\Common\ManageCatalogFavoritesTool;
use App\Mcp\Tools\Water\LogWaterIntakeTool;
use App\Models\CatalogFavorite;
use App\Models\DrinkType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-150: favoritos de catálogos en el MCP. */
class LifeTrackerCatalogFavoritesMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorites_can_be_listed_set_and_unset_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $coffee = DrinkType::create(['name' => 'Café', 'icon' => '☕', 'hydration_factor' => 0.8]);

        LifeTrackerServer::actingAs($user)->tool(ManageCatalogFavoritesTool::class, ['domain' => 'drinks', 'action' => 'set', 'id' => $coffee->id])
            ->assertOk()->assertSee('Café');
        $this->assertSame(1, CatalogFavorite::withoutGlobalScopes()->where('user_id', $user->id)->count());

        LifeTrackerServer::actingAs($other)->tool(ManageCatalogFavoritesTool::class, ['domain' => 'drinks', 'action' => 'list'])
            ->assertOk()->assertDontSee('Café');
        LifeTrackerServer::actingAs($other)->tool(ManageCatalogFavoritesTool::class, ['domain' => 'drinks', 'action' => 'set', 'id' => $coffee->id])
            ->assertHasErrors();

        LifeTrackerServer::actingAs($user)->tool(ManageCatalogFavoritesTool::class, ['domain' => 'drinks', 'action' => 'unset', 'id' => $coffee->id])->assertOk();
        $this->assertSame(0, CatalogFavorite::withoutGlobalScopes()->count());
    }

    public function test_unknown_domains_are_rejected(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)->tool(ManageCatalogFavoritesTool::class, ['domain' => 'mood', 'action' => 'list'])->assertHasErrors();
    }

    public function test_an_ambiguous_drink_name_resolves_to_the_only_favorite(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        DrinkType::create(['name' => 'Té verde', 'icon' => '🍵', 'hydration_factor' => 1]);
        $black = DrinkType::create(['name' => 'Té negro', 'icon' => '🍵', 'hydration_factor' => 1]);

        LifeTrackerServer::actingAs($user)->tool(LogWaterIntakeTool::class, ['amount_ml' => 250, 'drink_type_name' => 'té'])->assertHasErrors();

        app(\App\Services\Catalog\CatalogPicker::class)->set('drinks', $black->id, true);
        LifeTrackerServer::actingAs($user)->tool(LogWaterIntakeTool::class, ['amount_ml' => 250, 'drink_type_name' => 'té'])->assertOk();
        $this->assertDatabaseHas('drink_logs', ['drink_type_id' => $black->id]);
    }

    public function test_the_tool_is_listed_in_the_common_module(): void
    {
        $names = collect(app(ToolCatalog::class)->tools())->pluck('name')->all();

        $this->assertContains('manage-catalog-favorites-tool', $names);
    }

    public function test_recipe_tools_keep_their_favorite_contract_on_the_shared_table(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        LifeTrackerServer::actingAs($user)->tool(\App\Mcp\Tools\Meal\CreateRecipeTool::class, ['name' => 'Ajiaco', 'meal_type' => 'comida', 'favorite' => true])->assertOk();
        LifeTrackerServer::actingAs($user)->tool(\App\Mcp\Tools\Meal\CreateRecipeTool::class, ['name' => 'Bandeja', 'meal_type' => 'comida'])->assertOk();

        $this->assertDatabaseHas('catalog_favorites', ['user_id' => $user->id, 'catalog_domain' => 'recipes']);
        LifeTrackerServer::actingAs($user)->tool(\App\Mcp\Tools\Meal\ListRecipesTool::class, ['favorites_only' => true])
            ->assertOk()->assertSee('Ajiaco')->assertDontSee('Bandeja');
    }
}
