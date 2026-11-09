<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Shopping\GetInventoryDocumentTool;
use App\Mcp\Tools\Shopping\ListInventoryDocumentsTool;
use App\Models\MealInventoryMovement;
use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryDocumentsMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_filter_does_not_hide_any_of_the_32_document_lines_and_reads_do_not_write(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $lines = [];
        foreach (range(1, 32) as $index) {
            $product = ShoppingItem::create(['name' => 'MCP producto '.$index, 'base_unit' => 'unit', 'stock' => 0, 'status' => 'available']);
            $lines[] = ['shopping_item_id' => $product->id, 'quantity' => 2, 'unit' => 'unit'];
        }
        $operation = app(InventoryLedger::class)->record($user->id, 'purchase', $lines, ['channel' => 'mcp', 'origin' => ['reference' => 'MCP-32']]);
        $before = MealInventoryMovement::count();
        LifeTrackerServer::actingAs($user)->tool(ListInventoryDocumentsTool::class, ['item_id' => $product->id, 'type' => 'purchase', 'channel' => 'mcp', 'search' => 'MCP-32'])->assertOk()->assertSee($operation['operation_id'])->assertSee('"product_count":32');
        $response = LifeTrackerServer::actingAs($user)->tool(GetInventoryDocumentTool::class, ['operation_id' => $operation['operation_id']])->assertOk();
        foreach (range(1, 32) as $index) {
            $response->assertSee('MCP producto '.$index);
        }
        $this->assertSame($before, MealInventoryMovement::count());
        $this->assertSame(64.0, (float) ShoppingItem::sum('stock'));
        $other = User::factory()->create();
        LifeTrackerServer::actingAs($other)->tool(ListInventoryDocumentsTool::class, [])->assertOk()->assertDontSee($operation['operation_id']);
        LifeTrackerServer::actingAs($other)->tool(GetInventoryDocumentTool::class, ['operation_id' => $operation['operation_id']])->assertHasErrors()->assertSee('Registro de inventario no disponible.');
        LifeTrackerServer::actingAs($other)->tool(GetInventoryDocumentTool::class, ['operation_id' => (string) Str::uuid()])->assertHasErrors()->assertSee('Registro de inventario no disponible.');
    }

    public function test_mcp_filters_and_page_limits_are_validated(): void
    {
        $user = User::factory()->create();
        foreach ([['per_page' => 101], ['page' => 0], ['type' => 'inventado'], ['from' => '2026-11-02', 'to' => '2026-11-01']] as $filter) {
            LifeTrackerServer::actingAs($user)->tool(ListInventoryDocumentsTool::class, $filter)->assertHasErrors();
        }
        LifeTrackerServer::actingAs($user)->tool(GetInventoryDocumentTool::class, [])->assertHasErrors();
    }
}
