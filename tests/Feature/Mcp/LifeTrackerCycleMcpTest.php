<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Common\DeleteEntryTool;
use App\Mcp\Tools\Health\ListCycleTool;
use App\Mcp\Tools\Health\LogCycleTool;
use App\Models\CycleDayLog;
use App\Models\MenstrualCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LifeTrackerCycleMcpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_start_end_and_day_follow_the_same_rules_as_the_screen(): void
    {
        $user = User::factory()->create();
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(LogCycleTool::class, ['action' => 'start', 'date' => '2026-11-15'])->assertOk()->assertSee('empezó el 2026-11-15');
        $server->tool(LogCycleTool::class, ['action' => 'start', 'date' => '2026-11-16'])->assertHasErrors();       // ya hay uno en curso
        $server->tool(LogCycleTool::class, ['action' => 'end'])->assertOk()->assertSee('3 días');
        $server->tool(LogCycleTool::class, ['action' => 'end'])->assertHasErrors();                                  // ya no hay uno abierto
        $server->tool(LogCycleTool::class, ['action' => 'day', 'flow' => 'light', 'symptoms' => ['cramps']])->assertOk()->assertSee('flujo ligero')->assertSee('cólicos');
        $server->tool(LogCycleTool::class, ['action' => 'day', 'date' => '2026-11-25', 'flow' => 'light'])->assertHasErrors();
        $server->tool(LogCycleTool::class, ['action' => 'day', 'flow' => 'inventado'])->assertHasErrors();
        $server->tool(LogCycleTool::class, ['action' => 'start', 'date' => '2026-12-31'])->assertHasErrors();

        $cycle = MenstrualCycle::firstOrFail();
        $this->assertTrue($cycle->is_sensitive);
        $this->assertSame('mcp', $cycle->source);
        $this->assertSame(1, CycleDayLog::count());
    }

    public function test_the_same_start_is_not_duplicated(): void
    {
        $user = User::factory()->create();
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(LogCycleTool::class, ['action' => 'start', 'date' => '2026-11-15'])->assertOk();
        $server->tool(LogCycleTool::class, ['action' => 'start', 'date' => '2026-11-15'])->assertOk();

        $this->assertSame(1, MenstrualCycle::count());
    }

    public function test_list_returns_no_data_without_include_sensitive_only_how_many_are_hidden(): void
    {
        $user = User::factory()->create();
        $user->menstrualCycles()->create(['started_on' => '2026-10-18', 'ended_on' => '2026-10-22']);
        $user->cycleDayLogs()->create(['date' => '2026-11-16', 'flow' => 'heavy', 'notes' => 'Reservada']);

        LifeTrackerServer::actingAs($user)->tool(ListCycleTool::class, [])
            ->assertOk()->assertSee('"hidden_sensitive":2')->assertDontSee('2026-10-18')->assertDontSee('heavy')->assertDontSee('Reservada');

        LifeTrackerServer::actingAs($user)->tool(ListCycleTool::class, ['include_sensitive' => true])
            ->assertOk()->assertSee('2026-10-18')->assertSee('heavy')->assertSee('Reservada')->assertDontSee('hidden_sensitive');
    }

    public function test_non_sensitive_records_are_visible_without_the_flag(): void
    {
        $user = User::factory()->create();
        $user->menstrualCycles()->create(['started_on' => '2026-10-18', 'is_sensitive' => false]);

        LifeTrackerServer::actingAs($user)->tool(ListCycleTool::class, [])->assertOk()->assertSee('2026-10-18');
    }

    public function test_estimate_is_cautious_and_needs_three_starts(): void
    {
        $user = User::factory()->create();
        foreach (['2026-09-20', '2026-10-18'] as $date) {
            $user->menstrualCycles()->create(['started_on' => $date, 'ended_on' => Carbon::parse($date)->addDays(4)]);
        }

        LifeTrackerServer::actingAs($user)->tool(ListCycleTool::class, ['include_sensitive' => true])
            ->assertOk()->assertSee('Hacen falta al menos 3 inicios');

        $user->menstrualCycles()->create(['started_on' => '2026-11-15']);

        LifeTrackerServer::actingAs($user)->tool(ListCycleTool::class, ['include_sensitive' => true])
            ->assertOk()->assertSee('2026-12-11')->assertSee('2026-12-15')->assertSee('No es un método anticonceptivo')->assertSee('"cycle_day_today":3');
    }

    public function test_never_returns_or_deletes_another_users_cycle(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $cycle = $owner->menstrualCycles()->create(['started_on' => '2026-10-18']);

        LifeTrackerServer::actingAs($other)->tool(ListCycleTool::class, ['include_sensitive' => true])->assertOk()->assertDontSee('2026-10-18');
        LifeTrackerServer::actingAs($other)->tool(DeleteEntryTool::class, ['type' => 'menstrual_cycle', 'id' => $cycle->id, 'confirm' => true])->assertHasErrors();
        $this->assertSame(1, MenstrualCycle::withoutGlobalScopes()->count());

        LifeTrackerServer::actingAs($owner)->tool(DeleteEntryTool::class, ['type' => 'menstrual_cycle', 'id' => $cycle->id, 'confirm' => true])->assertOk();
        $this->assertSame(0, MenstrualCycle::withoutGlobalScopes()->count());
    }
}
