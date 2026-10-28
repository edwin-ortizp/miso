<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Shopping\CreateInventoryItemTool;
use App\Mcp\Tools\Shopping\ListShoppingItemsTool;
use App\Mcp\Tools\Shopping\ListStorageLocationsTool;
use App\Mcp\Tools\Shopping\UpdateShoppingItemTool;
use App\Models\ShoppingItem;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class InventoryMcpTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);
    }

    private function item(string $name, array $attributes = []): ShoppingItem
    {
        return $this->owner->shoppingItems()->create([
            'name' => $name, 'base_unit' => 'unit', 'stock' => 1, 'to_buy' => 0,
            'next_purchase' => false, 'status' => 'available', ...$attributes,
        ]);
    }

    public function test_locations_expose_hierarchy_and_archive_state_without_other_users(): void
    {
        $kitchen = StorageLocation::create(['name' => 'Cocina']);
        StorageLocation::create(['name' => 'Nevera', 'parent_id' => $kitchen->id]);
        $old = StorageLocation::create(['name' => 'Vieja']);
        $old->archive();
        $this->actingAs(User::factory()->create());
        StorageLocation::create(['name' => 'Ajena']);

        LifeTrackerServer::actingAs($this->owner)->tool(ListStorageLocationsTool::class, [])
            ->assertOk()->assertSee('Cocina › Nevera')->assertDontSee('Vieja')->assertDontSee('Ajena')
            ->assertStructuredContent(fn (AssertableJson $json) => $json->has('locations', 2)->etc());
        LifeTrackerServer::actingAs($this->owner)->tool(ListStorageLocationsTool::class, ['include_archived' => true])
            ->assertOk()->assertSee('Vieja')->assertDontSee('Ajena')
            ->assertStructuredContent(fn (AssertableJson $json) => $json->where('locations.2.archived', true)->etc());
    }

    public function test_inventory_filters_include_children_unassigned_and_positive_stock(): void
    {
        $kitchen = StorageLocation::create(['name' => 'Cocina']);
        $fridge = StorageLocation::create(['name' => 'Nevera', 'parent_id' => $kitchen->id]);
        $this->item('Leche', ['storage_location_id' => $fridge->id]);
        $this->item('Sal', ['storage_location_id' => $kitchen->id, 'stock' => 0]);
        $this->item('Pan');
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, [])
            ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json->has('items', 0)->etc());
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, ['only_pending' => false, 'storage_location_id' => $kitchen->id])
            ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json->has('items', 2)->where('items.0.name', 'Leche')->where('items.1.name', 'Sal')->etc());
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, ['only_pending' => false, 'storage_location_id' => $kitchen->id, 'in_stock' => true])
            ->assertOk()->assertSee('Leche')->assertDontSee('Sal');
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, ['only_pending' => false, 'storage_location_id' => null])
            ->assertOk()->assertSee('Pan')->assertDontSee('Leche');
    }

    public function test_catalog_pagination_does_not_truncate_or_repeat_products(): void
    {
        for ($i = 0; $i < 102; $i++) {
            $this->item(sprintf('Producto %03d', $i));
        }
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, ['only_pending' => false])
            ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json->has('items', 100)
            ->where('pagination.total', 102)->where('pagination.next_page', 2)->where('items.99.name', 'Producto 099')->etc());
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, ['only_pending' => false, 'page' => 2])
            ->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json->has('items', 2)
            ->where('pagination.next_page', null)->where('items.0.name', 'Producto 100')->etc());
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, ['page' => 0])->assertHasErrors();
    }

    public function test_count_replaces_stock_and_location_can_be_cleared_without_changing_other_products(): void
    {
        $fridge = StorageLocation::create(['name' => 'Nevera']);
        $item = $this->item('Huevos', ['stock' => 10]);
        $untouched = $this->item('Pan', ['stock' => 3]);
        foreach ([6.5, 6.5, 0] as $stock) {
            LifeTrackerServer::actingAs($this->owner)->tool(UpdateShoppingItemTool::class, [
                'item_id' => $item->id, 'stock' => $stock, 'storage_location_id' => $fridge->id,
            ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('item.stock', $stock)->where('item.storage_location.path', 'Nevera')->etc());
            $this->assertSame((float) $stock, $item->fresh()->stock);
        }
        $fridge->archive();
        LifeTrackerServer::actingAs($this->owner)->tool(UpdateShoppingItemTool::class, [
            'item_id' => $item->id, 'stock' => 1,
        ])->assertOk();
        $this->assertSame($fridge->id, $item->fresh()->storage_location_id);
        LifeTrackerServer::actingAs($this->owner)->tool(UpdateShoppingItemTool::class, [
            'item_id' => $item->id, 'stock' => 2, 'storage_location_id' => $fridge->id,
        ])->assertOk();
        LifeTrackerServer::actingAs($this->owner)->tool(UpdateShoppingItemTool::class, [
            'item_id' => $item->id, 'storage_location_id' => null,
        ])->assertOk();
        $this->assertNull($item->fresh()->storage_location_id);
        $this->assertSame(3.0, $untouched->fresh()->stock);
        $this->assertFalse($item->fresh()->next_purchase);
        $this->assertSame(0, $item->purchaseLines()->count());
    }

    public function test_invalid_locations_and_negative_stock_do_not_mutate_products(): void
    {
        $item = $this->item('Huevos');
        $old = StorageLocation::create(['name' => 'Vieja']);
        $old->archive();
        $this->actingAs(User::factory()->create());
        $foreign = StorageLocation::create(['name' => 'Ajena']);
        foreach ([$old->id, $foreign->id, fake()->uuid()] as $location) {
            LifeTrackerServer::actingAs($this->owner)->tool(UpdateShoppingItemTool::class, [
                'item_id' => $item->id, 'stock' => 20, 'storage_location_id' => $location,
            ])->assertHasErrors();
            LifeTrackerServer::actingAs($this->owner)->tool(CreateInventoryItemTool::class, [
                'name' => 'Nuevo', 'base_unit' => 'g', 'stock' => 20, 'storage_location_id' => $location,
            ])->assertHasErrors();
        }
        LifeTrackerServer::actingAs($this->owner)->tool(ListShoppingItemsTool::class, ['storage_location_id' => $foreign->id])->assertHasErrors();
        LifeTrackerServer::actingAs(User::factory()->create())->tool(UpdateShoppingItemTool::class, ['item_id' => $item->id, 'stock' => 20])->assertHasErrors();
        LifeTrackerServer::actingAs($this->owner)->tool(UpdateShoppingItemTool::class, ['item_id' => $item->id, 'stock' => -1])->assertHasErrors();
        $this->assertSame(1.0, $item->fresh()->stock);
        $this->assertSame(1, ShoppingItem::where('user_id', $this->owner->id)->count());
    }

    public function test_new_inventory_product_is_not_a_purchase_and_duplicates_return_candidates(): void
    {
        $fridge = StorageLocation::create(['name' => 'Nevera']);
        LifeTrackerServer::actingAs($this->owner)->tool(CreateInventoryItemTool::class, [
            'name' => 'Leche', 'base_unit' => 'ml', 'stock' => 250.5, 'storage_location_id' => $fridge->id,
        ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('item.name', 'Leche')->where('item.base_unit', 'ml')->where('item.stock', 250.5)
            ->where('item.storage_location.path', 'Nevera')->etc());
        $item = ShoppingItem::firstOrFail();
        $this->assertFalse($item->next_purchase);
        $this->assertSame(0.0, $item->to_buy);
        LifeTrackerServer::actingAs($this->owner)->tool(CreateInventoryItemTool::class, ['name' => 'Leche', 'base_unit' => 'ml', 'stock' => 10])
            ->assertHasErrors()->assertSee($item->id);
        $item->aliases()->create(['alias' => 'Lácteo', 'normalized_alias' => 'lacteo']);
        LifeTrackerServer::actingAs($this->owner)->tool(CreateInventoryItemTool::class, ['name' => 'Lácteo', 'base_unit' => 'ml', 'stock' => 10])
            ->assertHasErrors()->assertSee($item->id);
        $this->item('Leche de almendra');
        LifeTrackerServer::actingAs($this->owner)->tool(CreateInventoryItemTool::class, ['name' => 'Leche', 'base_unit' => 'ml', 'stock' => 10])
            ->assertHasErrors()->assertSee('Leche de almendra');
        foreach ([['base_unit' => 'kg'], ['stock' => -1], ['name' => '   ']] as $invalid) {
            LifeTrackerServer::actingAs($this->owner)->tool(CreateInventoryItemTool::class, [
                'name' => 'Arroz', 'base_unit' => 'g', 'stock' => 0, ...$invalid,
            ])->assertHasErrors();
        }
        LifeTrackerServer::actingAs($this->owner)->tool(CreateInventoryItemTool::class, ['name' => 'Arroz', 'base_unit' => 'g', 'stock' => 0])->assertOk();
        $this->assertSame(250.5, $item->fresh()->stock);
    }
}
