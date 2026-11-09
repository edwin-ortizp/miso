<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Shopping\ListInventoryMovementsTool;
use App\Models\MealInventoryMovement;
use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\InventoryHistory;
use App\Services\Meal\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHistoryMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_uses_the_same_persisted_balances_and_explicit_owner_as_the_web_projection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = ShoppingItem::create(['name' => 'Huevos', 'base_unit' => 'unit', 'stock' => 6, 'status' => 'available']);
        app(InventoryLedger::class)->adjust($user->id, $product->id, 3, 'unit', ['channel' => 'web']);
        app(InventoryLedger::class)->count($user->id, $product->id, 7, 'unit', ['channel' => 'mcp']);
        $data = app(InventoryHistory::class)->movements($user->id, $product->id, ['channel' => 'mcp']);
        $before = MealInventoryMovement::count();
        LifeTrackerServer::actingAs($user)->tool(ListInventoryMovementsTool::class, ['item_id' => $product->id, 'channel' => 'mcp', 'per_page' => 1])->assertOk()->assertSee($data['movements'][0]['id'])->assertSee('9.000')->assertSee('7.000');
        $this->assertSame($before, MealInventoryMovement::count());
        $this->assertSame(7.0, $product->fresh()->stock);
        LifeTrackerServer::actingAs(User::factory()->create())->tool(ListInventoryMovementsTool::class, ['item_id' => $product->id])->assertHasErrors()->assertDontSee('Huevos');
        LifeTrackerServer::actingAs($user)->tool(ListInventoryMovementsTool::class, [])->assertHasErrors();
    }
}
