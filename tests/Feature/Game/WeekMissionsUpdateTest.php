<?php

namespace Tests\Feature\Game;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Game\UpdateGameWeekMissionsTool;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameMission;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\SeasonManager;
use App\Services\Game\WeekPlanner;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeekMissionsUpdateTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $this->withWeeklyMissions();
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        Task::create(['title' => 'Pendiente', 'category' => 'hogar']);
        $this->habit();
        app(WeekPlanner::class)->forWeek(auth()->id(), now());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function replacement(int $id): array
    {
        return ['id' => $id, 'type' => 'category_tasks', 'title' => 'Una tarea de Hogar', 'category' => 'hogar', 'target' => 1];
    }

    public function test_mcp_updates_one_mission_and_keeps_completed_missions_and_rewards(): void
    {
        $missions = GameMission::orderBy('id')->get();
        $completed = $missions[0];
        app(TaskGamificationService::class)->complete(Task::first());
        Task::create(['title' => 'Otro pendiente', 'category' => 'hogar']);
        $this->assertNotNull($completed->fresh()->full_rewarded_at);
        $before = $completed->fresh()->toArray();
        $balls = GameEncounter::count();
        $contributions = DB::table('game_mission_contributions')->get()->toArray();
        $editable = $missions[1];
        $payload = ['missions' => [$this->replacement($editable->id)]];

        LifeTrackerServer::actingAs(auth()->user())->tool(UpdateGameWeekMissionsTool::class, $payload)->assertOk()->assertSee('applied');
        $updatedAt = $editable->fresh()->updated_at;
        LifeTrackerServer::actingAs(auth()->user())->tool(UpdateGameWeekMissionsTool::class, $payload)->assertOk()->assertSee('unchanged');

        $this->assertSame($before, $completed->fresh()->toArray());
        $this->assertSame($balls, GameEncounter::count());
        $this->assertEquals($contributions, DB::table('game_mission_contributions')->get()->toArray());
        $this->assertSame(3, GameMission::count());
        $this->assertEquals($updatedAt, $editable->fresh()->updated_at);
        $context = app(SeasonManager::class)->context(auth()->id());
        $this->assertSame(1, $context['pending_tasks_by_category']['hogar']);
        $rows = collect($context['current_week']['missions'])->keyBy('id');
        $this->assertFalse($rows[$completed->id]['editable']);
        $this->assertTrue($rows[$editable->id]['editable']);
        $this->assertSame('hogar', $rows[$editable->id]['params']['category']);
    }

    public function test_mixed_request_rejects_atomically_after_progress_or_rewards(): void
    {
        [$a, $b] = GameMission::orderBy('id')->take(2)->get()->all();
        foreach ([['progress' => 1], ['progress' => 0, 'half_rewarded_at' => now()], ['half_rewarded_at' => null, 'full_rewarded_at' => now()]] as $state) {
            $b->update($state);
            $before = GameMission::orderBy('id')->get()->toArray();
            $result = app(SeasonManager::class)->updateWeekMissions(auth()->id(), [$this->replacement($a->id), $this->replacement($b->id)]);
            $this->assertSame('rejected', $result['status']);
            $this->assertSame($before, GameMission::orderBy('id')->get()->toArray());
        }
    }

    public function test_foreign_old_missing_and_duplicate_ids_are_rejected(): void
    {
        $own = GameMission::first();
        $old = app(WeekPlanner::class)->forWeek(auth()->id(), now()->subWeek())->missions()->first();
        $other = User::factory()->create();
        $foreignWeek = app(WeekPlanner::class)->forWeek($other->id, now());
        $foreign = GameMission::withoutGlobalScopes()->create(['user_id' => $other->id, 'game_week_id' => $foreignWeek->id, 'type' => 'variety', 'title' => 'Ajena', 'target' => 2]);
        $before = GameMission::withoutGlobalScopes()->orderBy('id')->get()->toArray();
        foreach ([$old->id, $foreign->id, 999999] as $id) {
            $this->assertSame('rejected', app(SeasonManager::class)->updateWeekMissions(auth()->id(), [$this->replacement($own->id), $this->replacement($id)])['status']);
        }
        $this->assertSame('rejected', app(SeasonManager::class)->updateWeekMissions(auth()->id(), [$this->replacement($own->id), $this->replacement($own->id)])['status']);
        $this->assertSame($before, GameMission::withoutGlobalScopes()->orderBy('id')->get()->toArray());
    }

    public function test_invalid_proposal_and_paused_director_change_nothing(): void
    {
        $mission = GameMission::first();
        $invalid = $this->replacement($mission->id);
        $invalid['target'] = 2; // Solo hay un pendiente.
        $this->assertSame('rejected', app(SeasonManager::class)->updateWeekMissions(auth()->id(), [$invalid])['status']);
        $invalid['category'] = [];
        $this->assertSame('rejected', app(SeasonManager::class)->updateWeekMissions(auth()->id(), [$invalid])['status']);
        $this->gameSettings(['director_enabled' => false]);
        LifeTrackerServer::actingAs(auth()->user())->tool(UpdateGameWeekMissionsTool::class, ['missions' => [$this->replacement($mission->id)]])->assertHasErrors();
        $this->assertSame($mission->toArray(), $mission->fresh()->toArray());
    }

    public function test_recurrent_completion_still_advances_category_once_and_rewards_once(): void
    {
        $mission = GameMission::where('type', 'category_tasks')->first();
        $task = Task::create(['title' => 'Recurrente', 'category' => 'hogar', 'is_recurrent' => true]);
        $service = app(TaskGamificationService::class);
        $service->complete($task);
        $this->assertSame(1, $mission->fresh()->progress);
        $this->assertNotNull($mission->fresh()->full_rewarded_at);
        $balls = GameEncounter::count();
        $service->reopen($task->refresh());
        $service->complete($task->refresh());
        $this->assertSame($balls, GameEncounter::count());
    }

    public function test_progress_after_context_read_prevents_a_stale_update(): void
    {
        $mission = GameMission::where('type', 'category_tasks')->first();
        $context = app(SeasonManager::class)->context(auth()->id());
        $this->assertTrue(collect($context['current_week']['missions'])->firstWhere('id', $mission->id)['editable']);
        app(TaskGamificationService::class)->complete(Task::first());
        $before = $mission->fresh()->toArray();

        $this->assertSame('rejected', app(SeasonManager::class)->updateWeekMissions(auth()->id(), [$this->replacement($mission->id)])['status']);
        $this->assertSame($before, $mission->fresh()->toArray());
    }
}
