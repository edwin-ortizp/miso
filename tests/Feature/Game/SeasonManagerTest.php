<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSeason;
use App\Models\Game\GameSpecies;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\GameRules;
use App\Services\Game\SeasonManager;
use App\Services\Game\WeekPlanner;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonManagerTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        TaskCategory::create(['name' => 'Salud', 'key' => 'salud']);
        $this->gameSettings(['daily_limit' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function propose(array $overrides = []): array
    {
        return app(SeasonManager::class)->propose(auth()->id(), $overrides + SeasonValidatorTest::validProposal());
    }

    public function test_valid_proposal_activates_immediately_when_there_is_none(): void
    {
        $result = $this->propose();

        $this->assertSame('active', $result['status']);
        $season = $result['season'];
        $this->assertSame('Activa', $season->displayStatus());
        $this->assertTrue($season->starts_at->lte(now()));
        $this->assertSame('2026-12-07', $season->ends_at->toDateString()); // lunes de la semana + 4 semanas
        $this->assertSame($season->id, app(GameRules::class)->for(auth()->id(), now())['season_id']);
    }

    public function test_invalid_proposal_is_rejected_and_the_game_keeps_its_rules(): void
    {
        $result = $this->propose(['team_bonus' => ['salud' => 3]]);

        $this->assertSame('rejected', $result['status']);
        $this->assertSame(GameSeason::REJECTED, $result['season']->status);
        $this->assertNull(app(GameRules::class)->for(auth()->id(), now())['season_id']);
    }

    public function test_not_due_while_the_active_season_has_more_than_a_week_left(): void
    {
        $this->propose();
        $result = $this->propose(['name' => 'Otra']);

        $this->assertSame('not_due', $result['status']);
        $this->assertSame(1, GameSeason::count());
    }

    public function test_next_season_is_scheduled_when_the_current_ends_soon(): void
    {
        $first = $this->propose()['season'];
        Carbon::setTestNow(Carbon::parse('2026-12-02 10:00:00', 'America/Bogota'));

        $next = $this->propose(['name' => 'Temporada del Río'])['season'];

        $this->assertTrue($next->starts_at->equalTo($first->ends_at));
        $this->assertSame('Programada', $next->displayStatus());
        $this->assertSame('not_due', $this->propose(['name' => 'Tercera'])['status']);
    }

    public function test_paused_director_rejects(): void
    {
        $this->gameSettings(['director_enabled' => false]);

        $result = $this->propose();

        $this->assertSame('rejected', $result['status']);
        $this->assertSame(['El director está en pausa.'], $result['errors']);
    }

    public function test_team_bonus_is_applied_and_recorded(): void
    {
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => GameSpecies::where('name', 'Gotin')->value('id'), 'first_met_at' => now()]);
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $week->members()->first()->update(['ability_key' => 'hogar']);
        $season = $this->propose(['team_bonus' => ['salud' => 1.5]])['season'];

        $task = Task::create(['title' => 'Cita médica', 'start_date' => today(), 'category' => 'salud']);
        app(TaskGamificationService::class)->complete($task);

        $this->assertSame(5, GameCompanion::first()->xp); // round(3 × 1,5)
        $event = GameEvent::where('effect->team', true)->first();
        $this->assertSame($season->id, $event->effect['season_id']);
    }

    public function test_balls_use_season_weights_and_featured_line(): void
    {
        // Solo "rare" con peso → toda ball sale de esa rareza.
        $this->propose(['rarity_weights' => ['common' => 0, 'uncommon' => 0, 'rare' => 10, 'legendary' => 0], 'featured_line' => 'rocon']);
        foreach (['A', 'B', 'C'] as $title) {
            $this->completeTask($title);
        }

        $this->assertSame(['rare'], GameEncounter::with('species')->get()->pluck('species.rarity')->unique()->values()->all());
        $this->assertNotNull(GameEvent::where('kind', GameEvent::KIND_ACTIVITY)->first()->config_snapshot['season_id']);
    }

    public function test_missions_follow_the_season_focus_and_targets(): void
    {
        $this->withWeeklyMissions();
        $this->propose(['missions' => ['types' => ['category_tasks'], 'target' => [5, 5], 'focus' => ['salud']]]);

        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now()->addWeek());
        $missions = $week->missions()->get();

        $this->assertCount(1, $missions);
        $this->assertSame(GameMission::TYPE_CATEGORY, $missions[0]->type);
        $this->assertSame('salud', $missions[0]->params['category']);
        $this->assertSame(5, $missions[0]->target);
    }

    public function test_revert_ends_now_and_keeps_everything_earned(): void
    {
        $season = $this->propose()['season'];
        $this->completeTask('Antes');
        $balls = GameEncounter::count();

        app(SeasonManager::class)->revert(auth()->id(), $season->id);

        $this->assertSame('Revertida', $season->fresh()->displayStatus());
        $this->assertNull(app(GameRules::class)->for(auth()->id(), now())['season_id']);
        $this->assertSame($balls, GameEncounter::count());
        $this->assertSame('active', $this->propose(['name' => 'Nueva'])['status']);
    }

    public function test_disabled_seasons_fall_back_to_base_rules(): void
    {
        $this->propose();
        $this->gameSettings(['seasons_enabled' => false]);

        $this->assertNull(app(GameRules::class)->for(auth()->id(), now())['season_id']);
    }

    public function test_seasons_are_isolated_between_users(): void
    {
        $this->propose();
        $this->actingAs(User::factory()->create());

        $this->assertSame(0, GameSeason::count());
        $this->assertNull(app(GameRules::class)->for(auth()->id(), now())['season_id']);
    }
}
