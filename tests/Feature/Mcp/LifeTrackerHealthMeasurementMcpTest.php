<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Common\DeleteEntryTool;
use App\Mcp\Tools\Health\ListHealthMeasurementsTool;
use App\Mcp\Tools\Health\LogHealthMeasurementTool;
use App\Models\HealthMeasurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifeTrackerHealthMeasurementMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_tool_records_a_blood_pressure(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(LogHealthMeasurementTool::class, ['type' => 'blood_pressure', 'value' => 118, 'value2' => 76, 'pulse' => 70])
            ->assertOk()
            ->assertSee('118/76 mmHg');

        $m = HealthMeasurement::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(118.0, $m->value);
        $this->assertSame(76.0, $m->value2);
        $this->assertSame(70, $m->pulse);
        $this->assertSame('mmHg', $m->unit);
        $this->assertSame('mcp', $m->source);
        $this->assertFalse($m->isOutOfRange());
    }

    public function test_out_of_range_value_is_flagged_neutrally_with_referral(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(LogHealthMeasurementTool::class, ['type' => 'blood_pressure', 'value' => 160, 'value2' => 100])
            ->assertOk()
            ->assertSee('Fuera del rango de referencia general')
            ->assertSee('profesional de salud');
    }

    public function test_log_tool_rejects_impossible_values(): void
    {
        $user = User::factory()->create();
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(LogHealthMeasurementTool::class, ['type' => 'blood_pressure', 'value' => 80, 'value2' => 90])->assertHasErrors();
        $server->tool(LogHealthMeasurementTool::class, ['type' => 'blood_pressure', 'value' => 400, 'value2' => 80])->assertHasErrors();
        $server->tool(LogHealthMeasurementTool::class, ['type' => 'blood_pressure', 'value' => 120])->assertHasErrors();
        $server->tool(LogHealthMeasurementTool::class, ['type' => 'desconocido', 'value' => 1])->assertHasErrors();

        $this->assertSame(0, HealthMeasurement::count());
    }

    public function test_list_tool_hides_sensitive_unless_requested(): void
    {
        $user = User::factory()->create();
        $user->healthMeasurements()->create(['type' => 'blood_pressure', 'value' => 120, 'value2' => 80, 'unit' => 'mmHg', 'measured_at' => now(), 'is_sensitive' => false]);
        $user->healthMeasurements()->create(['type' => 'blood_pressure', 'value' => 150, 'value2' => 95, 'unit' => 'mmHg', 'measured_at' => now(), 'is_sensitive' => true]);

        LifeTrackerServer::actingAs($user)
            ->tool(ListHealthMeasurementsTool::class, [])
            ->assertOk()
            ->assertSee('hidden_sensitive')
            ->assertDontSee('150');

        LifeTrackerServer::actingAs($user)
            ->tool(ListHealthMeasurementsTool::class, ['include_sensitive' => true])
            ->assertOk()
            ->assertSee('150');
    }

    public function test_list_tool_never_returns_another_users_measurements(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->healthMeasurements()->create(['type' => 'blood_pressure', 'value' => 133, 'value2' => 88, 'unit' => 'mmHg', 'measured_at' => now()]);

        LifeTrackerServer::actingAs($other)
            ->tool(ListHealthMeasurementsTool::class, [])
            ->assertOk()
            ->assertDontSee('133');
    }

    public function test_delete_entry_tool_removes_a_measurement(): void
    {
        $user = User::factory()->create();
        $m = $user->healthMeasurements()->create(['type' => 'blood_pressure', 'value' => 120, 'value2' => 80, 'unit' => 'mmHg', 'measured_at' => now()]);

        LifeTrackerServer::actingAs($user)
            ->tool(DeleteEntryTool::class, ['type' => 'health_measurement', 'id' => $m->id, 'confirm' => true])
            ->assertOk();

        $this->assertSame(0, HealthMeasurement::count());
    }
}
