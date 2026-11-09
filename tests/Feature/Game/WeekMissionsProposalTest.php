<?php

namespace Tests\Feature\Game;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Game\GetGameDirectorContextTool;
use App\Mcp\Tools\Game\ProposeGameWeekMissionsTool;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\SeasonManager;
use App\Services\Game\WeekPlanner;
use App\Services\TaskGamificationService;
use App\Services\TaskRecurrenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeekMissionsProposalTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->seedSpecies();
        $this->withWeeklyMissions();
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        $this->habit('Caminar');
        $this->gameSettings(['daily_limit' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(string $title, array $extra = []): Task
    {
        return Task::create(['title' => $title, 'category' => 'hogar', 'start_date' => today()] + $extra);
    }

    /** @return array<int, array<string, mixed>> */
    private function proposal(array $taskIds, int $target = 2): array
    {
        return [
            ['type' => 'specific_tasks', 'title' => 'Cierra 2 de estas tareas de Hogar', 'target' => $target, 'task_ids' => $taskIds],
            ['type' => 'habit_groups', 'title' => 'Tus hábitos de la mañana 3 días', 'target' => 3, 'period' => 'morning'],
            ['type' => 'variety', 'title' => 'Avanza en 2 ámbitos', 'target' => 2],
        ];
    }

    private function ids(Task ...$tasks): array
    {
        return array_map(fn (Task $task) => (string) $task->id, $tasks);
    }

    public function test_context_lists_open_non_private_tasks_without_descriptions(): void
    {
        $this->task('Arreglar la llave', ['description' => 'Detalle íntimo', 'end_date' => now()->subDays(2)]);
        $this->task('Secreto', ['is_private' => true]);
        $this->completeTask('Ya hecha');

        LifeTrackerServer::actingAs($this->user)->tool(GetGameDirectorContextTool::class, [])
            ->assertOk()
            ->assertSee('Arreglar la llave')
            ->assertSee('"overdue": true')
            ->assertSee('"habit_periods"')
            ->assertDontSee('Detalle íntimo')
            ->assertDontSee('Secreto')
            ->assertDontSee('Ya hecha');
    }

    public function test_valid_proposal_replaces_missions_without_progress(): void
    {
        [$a, $b, $c] = [$this->task('Llave'), $this->task('Lavandería'), $this->task('Filtro')];
        app(WeekPlanner::class)->forWeek($this->user->id, now());

        LifeTrackerServer::actingAs($this->user)->tool(ProposeGameWeekMissionsTool::class, ['missions' => $this->proposal($this->ids($a, $b, $c))])
            ->assertOk()->assertSee('applied');

        $missions = GameMission::all();
        $this->assertCount(3, $missions);
        $this->assertTrue($missions->every(fn ($m) => $m->source === GameMission::SOURCE_DIRECTOR));
        $specific = $missions->firstWhere('type', GameMission::TYPE_SPECIFIC);
        $this->assertSame('Llave', $specific->params['tasks'][0]['title']);
    }

    public function test_missions_with_progress_are_kept(): void
    {
        $week = app(WeekPlanner::class)->forWeek($this->user->id, now());
        GameMission::where('game_week_id', $week->id)->first()->update(['progress' => 1]);
        $before = GameMission::pluck('title')->all();

        $result = app(SeasonManager::class)->proposeWeekMissions($this->user->id, $this->proposal($this->ids($this->task('A'), $this->task('B'))));

        $this->assertSame('ya_con_avance', $result['status']);
        $this->assertSame($before, GameMission::pluck('title')->all());
    }

    public function test_invalid_tasks_and_penalty_words_are_rejected(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other);
        $foreign = Task::create(['title' => 'Ajena', 'start_date' => today()]);
        $this->actingAs($this->user);
        $private = $this->task('Privada', ['is_private' => true]);
        $done = $this->completeTask('Hecha');
        $ok = $this->task('Ok');
        $manager = app(SeasonManager::class);

        foreach ([$foreign, $private, $done] as $bad) {
            $result = $manager->proposeWeekMissions($this->user->id, $this->proposal($this->ids($ok, $bad)));
            $this->assertSame('rejected', $result['status']);
        }

        $penalty = $this->proposal($this->ids($ok, $this->task('Otra')));
        $penalty[0]['title'] = 'Debes cerrar lo atrasado';
        $this->assertStringContainsString('debes', implode(' ', $manager->proposeWeekMissions($this->user->id, $penalty)['errors']));

        $unknown = $this->proposal($this->ids($ok, $this->task('Otra 2')));
        $unknown[0]['reward'] = 99;
        $this->assertSame('rejected', $manager->proposeWeekMissions($this->user->id, $unknown)['status']);
    }

    public function test_specific_mission_counts_each_task_once_and_rewards(): void
    {
        [$a, $b] = [$this->task('Llave'), $this->task('Lavandería')];
        app(SeasonManager::class)->proposeWeekMissions($this->user->id, $this->proposal($this->ids($a, $b, $this->task('Filtro'))));
        $mission = GameMission::where('type', GameMission::TYPE_SPECIFIC)->first();
        $service = app(TaskGamificationService::class);

        $service->complete($a);
        $service->reopen($a->refresh());
        $service->complete($a->refresh());
        $this->assertSame(1, $mission->fresh()->progress);

        $service->complete($b);
        $this->assertSame(2, $mission->fresh()->progress);
        $this->assertNotNull($mission->fresh()->full_rewarded_at);
        $missionBalls = GameEncounter::whereHas('event', fn ($q) => $q->where('kind', GameEvent::KIND_MISSION_REWARD))->count();
        $this->assertGreaterThanOrEqual(3, $missionBalls); // 1 de mitad + 2 de completado (más las de otras misiones si avanzaron)
    }

    public function test_recurrent_task_occurrence_counts(): void
    {
        $recurrent = $this->task('Regar', ['is_recurrent' => true, 'recurrence' => ['frequency' => 'daily', 'interval' => 1]]);
        app(SeasonManager::class)->proposeWeekMissions($this->user->id, $this->proposal($this->ids($recurrent, $this->task('Otra')), 1));

        app(TaskRecurrenceService::class)->completeAndScheduleAutomatically($recurrent->refresh(), app(TaskGamificationService::class));

        $this->assertSame(1, GameMission::where('type', GameMission::TYPE_SPECIFIC)->value('progress'));
    }

    public function test_week_view_shows_tasks_links_and_director_label(): void
    {
        [$a, $b] = [$this->task('Llave'), $this->task('Lavandería')];
        app(SeasonManager::class)->proposeWeekMissions($this->user->id, $this->proposal($this->ids($a, $b)));
        app(TaskGamificationService::class)->complete($a);
        Task::whereKey($b->id)->delete();

        $html = $this->get(route('game.week'))->assertOk()
            ->assertSee('Propuesta por el director')
            ->assertSee('Llave')->assertSee('Completada')
            ->assertSee('Lavandería')->assertSee('Tarea eliminada')
            ->getContent();
        $this->assertStringNotContainsString(e(route('tasks.list', ['edit' => $b->id])), $html);
    }

    public function test_pending_task_links_to_its_editor(): void
    {
        [$a, $b] = [$this->task('Llave'), $this->task('Lavandería')];
        app(SeasonManager::class)->proposeWeekMissions($this->user->id, $this->proposal($this->ids($a, $b)));

        $this->get(route('game.week'))->assertOk()->assertSee(e(route('tasks.list', ['edit' => $a->id])), false);
    }

    public function test_second_proposal_in_the_same_week_changes_nothing(): void
    {
        [$a, $b, $c, $d] = [$this->task('Llave'), $this->task('Lavandería'), $this->task('Filtro'), $this->task('Pintar')];
        $tool = fn (array $ids) => LifeTrackerServer::actingAs($this->user)->tool(ProposeGameWeekMissionsTool::class, ['missions' => $this->proposal($ids)]);

        $tool($this->ids($a, $b))->assertOk()->assertSee('applied');
        $before = GameMission::orderBy('id')->get(['id', 'title', 'params'])->toArray();

        $tool($this->ids($a, $b))->assertOk()->assertSee('ya_propuestas');   // reintento idéntico
        $tool($this->ids($c, $d))->assertOk()->assertSee('ya_propuestas');   // propuesta distinta

        $this->assertSame($before, GameMission::orderBy('id')->get(['id', 'title', 'params'])->toArray());
        LifeTrackerServer::actingAs($this->user)->tool(GetGameDirectorContextTool::class, [])
            ->assertSee('"director_proposed": true')
            ->assertSee('"replaceable": false');
    }

    public function test_next_week_accepts_a_new_proposal(): void
    {
        [$a, $b] = [$this->task('Llave'), $this->task('Lavandería')];
        app(SeasonManager::class)->proposeWeekMissions($this->user->id, $this->proposal($this->ids($a, $b)));

        Carbon::setTestNow(Carbon::parse('2026-11-16 08:00:00', 'America/Bogota'));
        $result = app(SeasonManager::class)->proposeWeekMissions($this->user->id, $this->proposal($this->ids($a, $b)));

        $this->assertSame('applied', $result['status']);
    }

    public function test_other_users_cannot_see_or_use_tasks(): void
    {
        $this->task('Mía');
        $other = User::factory()->create();

        LifeTrackerServer::actingAs($other)->tool(GetGameDirectorContextTool::class, [])->assertOk()->assertDontSee('Mía');
    }
}
