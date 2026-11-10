<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\Goal;
use App\Models\User;
use App\Services\Game\EncounterOpener;
use App\Services\Game\GameFeed;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFeedTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $this->gameSettings(['daily_limit' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function feed(int $since = 0): array
    {
        return app(GameFeed::class)->since(auth()->id(), $since);
    }

    private function team(): GameCompanion
    {
        $companion = GameCompanion::create(['line_key' => 'gotin', 'species_id' => GameSpecies::where('name', 'Gotin')->value('id'), 'xp' => 0, 'level' => 1, 'first_met_at' => now()]);
        app(WeekPlanner::class)->forWeek(auth()->id(), now())->members()->first()->update(['ability_key' => 'hogar']);

        return $companion;
    }

    public function test_ball_and_grouped_team_xp_produce_toasts_and_a_celebration(): void
    {
        $this->team();
        $this->completeTask('Pagar arriendo');

        $result = $this->feed();

        $titles = array_column($result['toasts'], 'title');
        $this->assertContains('¡Ganaste una ball!', $titles);
        $this->assertContains('Tu equipo ganó experiencia', $titles);
        $xp = collect($result['toasts'])->firstWhere('type', 'xp');
        $this->assertSame('Gotin +3', $xp['detail']);
        $this->assertTrue($result['celebrate']);
        $this->assertSame(GameEvent::max('id'), $result['last_id']);
    }

    public function test_daily_limit_gives_only_the_xp_toast_without_celebration(): void
    {
        $this->team();
        $this->gameSettings(['daily_limit' => 0]);
        $this->completeTask('Sin cupo');

        $result = $this->feed();

        $this->assertSame(['xp'], array_column($result['toasts'], 'type'));
        $this->assertFalse($result['celebrate']);
    }

    public function test_level_up_and_evolution_are_announced(): void
    {
        GameSpecies::where('name', 'Brotalia')->update(['evolve_level' => 2]);
        $companion = GameCompanion::create(['line_key' => 'brotelo', 'species_id' => GameSpecies::where('name', 'Brotelo')->value('id'), 'xp' => 35, 'level' => 1, 'first_met_at' => now()]);
        $this->completeTask('X');
        $encounter = GameEncounter::latest('id')->first();
        $encounter->update(['species_id' => $companion->species_id]);
        app(EncounterOpener::class)->open($encounter->id, auth()->id());

        $titles = array_column($this->feed()['toasts'], 'title');

        $this->assertContains('¡Brotalia subió al nivel 2!', $titles);
        $this->assertContains('¡Brotelo evolucionó a Brotalia!', $titles);
    }

    public function test_mission_rewards_are_announced_once_per_stage(): void
    {
        $this->withWeeklyMissions();
        \App\Models\TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        GameMission::where('game_week_id', $week->id)->delete();
        GameMission::create(['game_week_id' => $week->id, 'type' => GameMission::TYPE_CATEGORY, 'title' => '2 de Hogar', 'params' => ['category' => 'hogar'], 'target' => 2]);
        foreach (['A', 'B'] as $title) {
            app(\App\Services\TaskGamificationService::class)->complete(\App\Models\Task::create(['title' => $title, 'category' => 'hogar', 'start_date' => today()]));
        }

        $missions = collect($this->feed()['toasts'])->where('type', 'mission')->values();

        $this->assertCount(2, $missions);                                 // mitad + cumplida (2 balls → 1 aviso)
        $this->assertSame('¡Misión cumplida!', $missions[1]['title']);
    }

    public function test_completed_goal_is_announced_as_goal(): void
    {
        Goal::create(['title' => 'Aprender a nadar', 'status' => 'active'])->update(['status' => 'completed']);

        $goal = collect($this->feed()['toasts'])->firstWhere('type', 'goal');

        $this->assertSame('¡Meta cumplida!', $goal['title']);
        $this->assertSame('Aprender a nadar', $goal['detail']);
    }

    public function test_nothing_new_after_the_last_seen_id(): void
    {
        $this->completeTask('A');
        $last = $this->feed()['last_id'];

        $this->assertSame([], $this->feed($last)['toasts']);
    }
}
