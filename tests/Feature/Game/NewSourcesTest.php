<?php

namespace Tests\Feature\Game;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Focus\LogFocusSessionTool;
use App\Models\DrinkLog;
use App\Models\ExerciseLog;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\Goal;
use App\Models\GoalEntry;
use App\Models\GoalNumericEntry;
use App\Models\ModuleSetting;
use App\Models\PomodoroSession;
use App\Models\User;
use App\Services\Game\MissionProposalValidator;
use App\Services\Game\SeasonValidator;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewSourcesTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota')); // miércoles
        $this->user = User::factory()->create(['daily_water_goal' => 2000, 'daily_exercise_minutes' => 30]);
        $this->actingAs($this->user);
        $this->seedSpecies();
        $this->gameSettings(['daily_limit' => 50]);
        ModuleSetting::create(['module' => 'pomodoro', 'settings' => ['weekday_goal_minutes' => 50, 'weekend_goal_minutes' => 20]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function balls(string $source): int
    {
        return GameEvent::where('kind', GameEvent::KIND_ACTIVITY)->where('effect->source', $source)->where('effect->ball', true)->count();
    }

    private function pomodoro(int $minutes): PomodoroSession
    {
        return PomodoroSession::create([
            'date' => today(), 'start_time' => ['timestamp' => now()->timestamp], 'end_time' => ['timestamp' => now()->addMinutes($minutes)->timestamp],
            'duration' => $minutes * 60, 'completed' => true,
        ]);
    }

    public function test_focus_grants_once_when_the_daily_goal_is_reached(): void
    {
        $this->pomodoro(25);
        $this->assertSame(0, $this->balls('focus'));

        $this->pomodoro(25);
        $this->assertSame(1, $this->balls('focus'));
        $this->assertSame('Foco del día', GameEvent::where('effect->source', 'focus')->value('source_label'));

        $this->pomodoro(25);
        $this->assertSame(1, $this->balls('focus'));
    }

    public function test_focus_via_mcp_counts(): void
    {
        LifeTrackerServer::actingAs($this->user)->tool(LogFocusSessionTool::class, ['minutes' => 55])->assertOk();

        $this->assertSame(1, $this->balls('focus'));
    }

    public function test_goal_progress_once_per_goal_and_day(): void
    {
        $read = Goal::create(['title' => 'Leer 12 libros', 'status' => 'active']);
        $run = Goal::create(['title' => 'Correr', 'status' => 'active']);

        GoalEntry::create(['goal_id' => $read->id, 'text' => 'Capítulo 1', 'date' => today()]);
        GoalNumericEntry::create(['goal_id' => $read->id, 'value' => 1, 'date' => today()]);
        GoalEntry::create(['goal_id' => $run->id, 'text' => '5 km', 'date' => today()]);

        $this->assertSame(2, $this->balls('goals'));
        $this->assertSame('Meta: Leer 12 libros', GameEvent::where('effect->source', 'goals')->orderBy('id')->value('source_label'));
    }

    public function test_completed_goal_gives_a_special_ball_once_outside_the_daily_limit(): void
    {
        $this->gameSettings(['daily_limit' => 0]);
        $goal = Goal::create(['title' => 'Aprender a nadar', 'status' => 'active']);

        $goal->update(['status' => 'completed']);
        $goal->update(['status' => 'active']);
        $goal->update(['status' => 'completed']);

        $this->assertSame(1, GameEncounter::count());
        $this->assertSame('Meta cumplida: Aprender a nadar', GameEvent::where('idempotency_key', 'goal-done:'.$goal->id)->value('source_label'));
    }

    public function test_special_balls_do_not_consume_the_daily_limit(): void
    {
        $this->gameSettings(['daily_limit' => 1]);
        Goal::create(['title' => 'Meta', 'status' => 'active'])->update(['status' => 'completed']);
        $this->completeTask('Una tarea');

        $this->assertSame(2, GameEncounter::count());
    }

    public function test_water_grants_once_when_the_goal_is_crossed_and_deleting_keeps_it(): void
    {
        $first = DrinkLog::create(['drink_type' => 'agua', 'time' => '08:00', 'timestamp' => now()->timestamp, 'date' => today(), 'amount' => 1500, 'hydration_value' => 1500]);
        $this->assertSame(0, $this->balls('health'));

        DrinkLog::create(['drink_type' => 'agua', 'time' => '08:00', 'timestamp' => now()->timestamp, 'date' => today(), 'amount' => 600, 'hydration_value' => 600]);
        DrinkLog::create(['drink_type' => 'agua', 'time' => '08:00', 'timestamp' => now()->timestamp, 'date' => today(), 'amount' => 300, 'hydration_value' => 300]);
        $this->assertSame(1, $this->balls('health'));
        $this->assertSame('Agua del día', GameEvent::where('idempotency_key', 'water:2026-11-11')->value('source_label'));

        $first->delete();
        $this->assertSame(1, GameEncounter::count());
    }

    public function test_exercise_grants_once_per_day(): void
    {
        ExerciseLog::create(['date' => today(), 'duration' => 20]);
        ExerciseLog::create(['date' => today(), 'duration' => 15]);
        ExerciseLog::create(['date' => today(), 'duration' => 15]);

        $this->assertSame(1, GameEvent::where('idempotency_key', 'exercise:2026-11-11')->count());
    }

    public function test_disabled_sources_grant_nothing_but_modules_save(): void
    {
        $this->gameSettings(['sources' => ['tasks' => true, 'habits' => true, 'focus' => false, 'goals' => false, 'health' => false]]);

        $this->pomodoro(60);
        GoalEntry::create(['goal_id' => Goal::create(['title' => 'X', 'status' => 'active'])->id, 'text' => 'a', 'date' => today()]);
        DrinkLog::create(['drink_type' => 'agua', 'time' => '08:00', 'timestamp' => now()->timestamp, 'date' => today(), 'amount' => 3000, 'hydration_value' => 3000]);

        $this->assertSame(0, GameEncounter::count());
        $this->assertSame(1, PomodoroSession::count());
        $this->assertSame(1, DrinkLog::count());
    }

    public function test_health_affinity_gets_the_bonus_and_variety_counts_the_scope(): void
    {
        $this->withWeeklyMissions();
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => GameSpecies::where('name', 'Gotin')->value('id'), 'first_met_at' => now()]);
        $week = app(WeekPlanner::class)->forWeek($this->user->id, now());
        $week->members()->first()->update(['ability_key' => 'health', 'ability_label' => 'Salud']);
        GameMission::where('game_week_id', $week->id)->delete();
        $variety = GameMission::create(['game_week_id' => $week->id, 'type' => GameMission::TYPE_VARIETY, 'title' => 'Avanza en 2 ámbitos', 'target' => 2]);

        DrinkLog::create(['drink_type' => 'agua', 'time' => '08:00', 'timestamp' => now()->timestamp, 'date' => today(), 'amount' => 2000, 'hydration_value' => 2000]);
        $this->pomodoro(50);

        $this->assertSame(9 + 3, GameCompanion::first()->xp); // agua: 3 + 6 por afinidad; foco: 3
        $this->assertSame(2, $variety->fresh()->progress);
    }

    public function test_director_accepts_the_new_scopes(): void
    {
        $season = app(SeasonValidator::class)->validate(['name' => 'T', 'narrative' => 'N', 'team_bonus' => ['health' => 1.5, 'focus' => 1.2]], $this->user->id);
        $this->assertSame([], $season['errors']);

        $missions = app(MissionProposalValidator::class)->validate([
            ['type' => 'category_tasks', 'title' => 'Cumple tu foco 3 días', 'target' => 3, 'category' => 'focus'],
        ], $this->user->id);
        $this->assertSame([], $missions['errors']);
    }

    public function test_other_users_actions_do_not_affect_my_game(): void
    {
        $other = User::factory()->create(['daily_water_goal' => 1000]);
        $this->actingAs($other);
        DrinkLog::create(['drink_type' => 'agua', 'time' => '08:00', 'timestamp' => now()->timestamp, 'date' => today(), 'amount' => 2000, 'hydration_value' => 2000]);

        $this->actingAs($this->user);
        $this->assertSame(0, GameEncounter::count());
        $this->assertSame(1, GameEncounter::withoutGlobalScopes()->count());
    }
}
