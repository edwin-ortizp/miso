<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameTeamMember;
use App\Models\Game\GameWeek;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\WeekPlanner;
use App\Services\HabitGamificationService;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityEffectsTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private GameWeek $week;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        TaskCategory::create(['name' => 'Trabajo', 'key' => 'trabajo']);
        $this->gameSettings(['daily_limit' => 50]);

        $species = GameSpecies::where('name', 'Gotin')->first();
        foreach (['a', 'b'] as $line) {
            GameCompanion::create(['line_key' => $line, 'species_id' => $species->id, 'first_met_at' => now()]);
        }
        $this->week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        // Afinidades fijas para que la prueba sea determinista.
        $members = $this->week->members()->get();
        $members[0]->update(['ability_key' => 'hogar', 'ability_label' => 'Hogar']);
        $members[1]->update(['ability_key' => 'trabajo', 'ability_label' => 'Trabajo']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mission(string $type, int $target, array $params = []): GameMission
    {
        return GameMission::create(['game_week_id' => $this->week->id, 'type' => $type, 'title' => "Misión {$type}", 'params' => $params, 'target' => $target]);
    }

    private function completeIn(string $category, string $title): Task
    {
        $task = Task::create(['title' => $title, 'start_date' => today(), 'category' => $category]);
        app(TaskGamificationService::class)->complete($task);

        return $task->refresh();
    }

    private function missionBalls(): int
    {
        return GameEncounter::whereHas('event', fn ($q) => $q->where('kind', GameEvent::KIND_MISSION_REWARD))->count();
    }

    public function test_team_gets_xp_and_the_affine_member_gets_the_bonus(): void
    {
        $this->completeIn('hogar', 'Barrer');

        $members = $this->week->members()->with('companion')->get();
        $this->assertSame(9, $members[0]->companion->xp);
        $this->assertSame(3, $members[1]->companion->xp);
        $this->assertSame('Equipo de la semana · Barrer', GameEvent::where('effect->team', true)->value('source_label'));
    }

    public function test_team_xp_counts_even_when_the_daily_limit_is_reached(): void
    {
        $this->gameSettings(['daily_limit' => 0]);
        $this->completeIn('trabajo', 'Informe');

        $this->assertSame(0, GameEncounter::count());
        $this->assertSame(9, $this->week->members()->with('companion')->get()[1]->companion->xp);
    }

    public function test_mission_gives_one_ball_at_half_and_two_at_full_once(): void
    {
        $mission = $this->mission(GameMission::TYPE_CATEGORY, 4, ['category' => 'hogar']);

        $this->completeIn('hogar', 'H1');
        $this->assertSame(0, $this->missionBalls());
        $this->completeIn('hogar', 'H2');
        $this->assertSame(1, $this->missionBalls());
        $this->completeIn('trabajo', 'T1');
        $this->completeIn('hogar', 'H3');
        $this->completeIn('hogar', 'H4');
        $this->assertSame(3, $this->missionBalls());
        $this->completeIn('hogar', 'H5');

        $this->assertSame(4, $mission->fresh()->progress);
        $this->assertSame(3, $this->missionBalls());
        $this->assertNotNull($mission->fresh()->full_rewarded_at);
    }

    public function test_mission_balls_do_not_count_for_the_daily_limit(): void
    {
        $this->gameSettings(['daily_limit' => 2]);
        $this->mission(GameMission::TYPE_CATEGORY, 2, ['category' => 'hogar']);

        $this->completeIn('hogar', 'H1');
        $this->completeIn('hogar', 'H2');

        $this->assertSame(3, $this->missionBalls());
        $activityBalls = GameEncounter::whereHas('event', fn ($q) => $q->where('kind', GameEvent::KIND_ACTIVITY))->count();
        $this->assertSame(2, $activityBalls);
    }

    public function test_reopening_and_completing_again_does_not_advance_twice(): void
    {
        $mission = $this->mission(GameMission::TYPE_CATEGORY, 4, ['category' => 'hogar']);
        $task = $this->completeIn('hogar', 'H1');
        $service = app(TaskGamificationService::class);
        $service->reopen($task);
        $service->complete($task->refresh());

        $this->assertSame(1, $mission->fresh()->progress);
    }

    public function test_variety_counts_distinct_scopes(): void
    {
        $mission = $this->mission(GameMission::TYPE_VARIETY, 3);
        $this->completeIn('hogar', 'H1');
        $this->completeIn('hogar', 'H2');
        $this->completeIn('trabajo', 'T1');
        $this->habit('Caminar');
        app(HabitGamificationService::class)->toggle(\App\Models\HabitDefinition::first()->id, '2026-11-11');

        $this->assertSame(3, $mission->fresh()->progress);
    }

    public function test_habit_mission_counts_distinct_days(): void
    {
        $mission = $this->mission(GameMission::TYPE_HABITS, 2, ['period' => 'morning']);
        $habit = $this->habit('Caminar');
        $service = app(HabitGamificationService::class);

        $service->toggle($habit->id, '2026-11-11');
        $service->toggle($habit->id, '2026-11-11');
        $service->toggle($habit->id, '2026-11-11');
        $this->assertSame(1, $mission->fresh()->progress);

        $service->toggle($habit->id, '2026-11-12');
        $this->assertSame(2, $mission->fresh()->progress);
    }

    public function test_an_idle_week_loses_nothing(): void
    {
        $this->completeIn('hogar', 'H1');
        $xpBefore = GameCompanion::sum('xp');
        $ballsBefore = GameEncounter::count();

        Carbon::setTestNow(Carbon::parse('2026-11-30 10:00:00', 'America/Bogota'));
        app(WeekPlanner::class)->forWeek(auth()->id(), now());

        $this->assertSame($xpBefore, GameCompanion::sum('xp'));
        $this->assertSame($ballsBefore, GameEncounter::count());
        $this->assertSame(2, GameTeamMember::where('game_week_id', '!=', $this->week->id)->count());
    }
}
