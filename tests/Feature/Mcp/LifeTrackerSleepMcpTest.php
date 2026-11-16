<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Common\DeleteEntryTool;
use App\Mcp\Tools\Health\ListSleepTool;
use App\Mcp\Tools\Health\LogSleepTool;
use App\Models\SleepLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LifeTrackerSleepMcpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_log_tool_records_a_night_and_compares_it_with_the_goal(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(LogSleepTool::class, ['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 06:30', 'quality' => 4])
            ->assertOk()->assertSee('7 h 30 min')->assertSee('calidad 4/5')->assertSee('Faltaron 30 min para tu meta');

        $log = SleepLog::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('mcp', $log->source);
    }

    public function test_log_tool_is_idempotent_and_rejects_bad_input(): void
    {
        $user = User::factory()->create();
        $server = LifeTrackerServer::actingAs($user);
        $args = ['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 06:30'];

        $server->tool(LogSleepTool::class, $args)->assertOk();
        $server->tool(LogSleepTool::class, $args)->assertOk();
        $this->assertSame(1, SleepLog::count());

        $server->tool(LogSleepTool::class, ['slept_at' => '2026-11-16 07:00', 'woke_at' => '2026-11-16 06:00'])->assertHasErrors();
        $server->tool(LogSleepTool::class, ['slept_at' => '2026-11-16 02:00', 'woke_at' => '2026-11-16 08:00'])->assertHasErrors();
        $server->tool(LogSleepTool::class, [...$args, 'quality' => 8])->assertHasErrors();
        $this->assertSame(1, SleepLog::count());
    }

    public function test_list_tool_returns_summary_goal_and_records(): void
    {
        $user = User::factory()->create(['daily_sleep_hours' => 7]);
        $user->sleepLogs()->create(['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 07:00']);
        $user->sleepLogs()->create(['slept_at' => '2026-11-14 01:00', 'woke_at' => '2026-11-14 06:00']);

        LifeTrackerServer::actingAs($user)
            ->tool(ListSleepTool::class, [])
            ->assertOk()->assertSee('"goal":"7 h"')->assertSee('"meeting_goal":1')->assertSee('"average":"6 h 30 min"')->assertSee('"meets_goal":true');

        LifeTrackerServer::actingAs($user)
            ->tool(ListSleepTool::class, ['summary_only' => true])
            ->assertOk()->assertDontSee('"records":[');
    }

    public function test_sensitive_records_are_hidden_unless_requested(): void
    {
        $user = User::factory()->create();
        $user->sleepLogs()->create(['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 06:00', 'is_sensitive' => false]);
        $user->sleepLogs()->create(['slept_at' => '2026-11-14 22:00', 'woke_at' => '2026-11-15 04:10', 'is_sensitive' => true, 'notes' => 'Reservada']);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(ListSleepTool::class, [])->assertSee('hidden_sensitive')->assertDontSee('Reservada');
        $server->tool(ListSleepTool::class, ['include_sensitive' => true])->assertSee('Reservada');
    }

    public function test_never_returns_another_users_sleep_and_delete_works_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $log = $owner->sleepLogs()->create(['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 06:30', 'notes' => 'SoloDelDueño']);

        LifeTrackerServer::actingAs($other)->tool(ListSleepTool::class, [])->assertOk()->assertDontSee('SoloDelDueño');
        LifeTrackerServer::actingAs($other)->tool(DeleteEntryTool::class, ['type' => 'sleep', 'id' => $log->id, 'confirm' => true])->assertHasErrors();
        $this->assertSame(1, SleepLog::withoutGlobalScopes()->count());

        LifeTrackerServer::actingAs($owner)->tool(DeleteEntryTool::class, ['type' => 'sleep', 'id' => $log->id, 'confirm' => true])->assertOk();
        $this->assertSame(0, SleepLog::withoutGlobalScopes()->count());
    }
}
