<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameSeasonView;
use App\Livewire\Game\GameSettingsPage;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameSeason;
use App\Models\Game\GameSpecies;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\GameSettings;
use App\Services\Game\SeasonManager;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameSeasonViewTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        TaskCategory::create(['name' => 'Salud', 'key' => 'salud']);
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_empty_state_and_switches(): void
    {
        $this->get(route('game.season'))->assertOk()
            ->assertSee('Aún no hay temporada. El juego usa sus reglas base.')
            ->assertSee('se activan o pausan en')
            ->assertSee('Todavía no hay temporadas.');
    }

    public function test_active_season_rules_history_and_revert(): void
    {
        // Primero una inválida (queda rechazada) y luego la válida.
        app(SeasonManager::class)->propose(auth()->id(), ['team_bonus' => ['salud' => 3]] + SeasonValidatorTest::validProposal());
        app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal());
        $season = GameSeason::where('status', GameSeason::ACTIVE)->first();

        Livewire::test(GameSeasonView::class)
            ->assertSee('Temporada del Bosque')
            ->assertSee('Las criaturas del bosque despiertan.')
            ->assertSee('Misiones: impulsa Salud')
            ->assertSee('Bonos: Salud ×1,5 · Hábitos ×1,2')
            ->assertSee('Destacada: línea Brotelo')
            ->assertSee('Rechazada')
            ->call('askRevert', $season->id)
            ->assertSet('confirmRevert', true)
            ->call('revert')
            ->assertSee('Temporada revertida. No perdiste nada de lo que ganaste.')
            ->assertSee('Aún no hay temporada.');

        $this->assertSame(GameSeason::REVERTED, $season->fresh()->status);
    }

    public function test_adventure_keeps_existing_mission_target_and_shows_real_rewards(): void
    {
        app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal());
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $mission = $week->missions()->create([
            'user_id' => auth()->id(), 'type' => 'variety', 'title' => 'Explora distintos ámbitos',
            'target' => 4, 'progress' => 2, 'params' => [],
        ]);

        GameCompanion::create([
            'line_key' => 'brotelo', 'species_id' => GameSpecies::where('name', 'Brotelo')->value('id'),
            'level' => 1, 'xp' => 0, 'first_met_at' => now(),
        ]);
        $this->get(route('game.season'))->assertOk()
            ->assertSee('season-landscape.svg')->assertSee('Estás aquí')->assertSee('Brotelo')
            ->assertSee('aria-valuemax="4"', false)->assertSee('Al llegar a 2/4')
            ->assertSee('Mitad')->assertSee('Completa')->assertSee('+2')->assertSee('+50 % de experiencia')
            ->assertSee('Para tu equipo al completar actividades de Salud.');
        $this->assertSame(4, $mission->fresh()->target);
        $this->assertSame(2, $mission->fresh()->progress);
        $this->assertDatabaseCount('game_missions', $week->missions()->count());
    }

    public function test_three_quest_cards_show_task_links_and_received_rewards(): void
    {
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $task = Task::create(['title' => 'Preparar los materiales para compartir una idea con el equipo sin perder los ejemplos', 'start_date' => today()]);
        $week->missions()->create([
            'user_id' => auth()->id(), 'type' => 'specific_tasks',
            'title' => 'Da espacio a esas pequeñas ideas que quieres convertir en algo concreto',
            'target' => 2, 'progress' => 0, 'params' => ['tasks' => [
                ['id' => $task->id, 'code' => 'TEC-001', 'title' => $task->title],
                ['id' => 'removed-task', 'code' => 'TEC-002', 'title' => 'Una idea que ya cambió de rumbo'],
            ]],
        ]);
        $week->missions()->create([
            'user_id' => auth()->id(), 'type' => 'habit_groups', 'title' => 'Un momento para tus hábitos de la tarde',
            'target' => 4, 'progress' => 2, 'params' => ['period' => 'afternoon'], 'half_rewarded_at' => now(),
        ]);
        $week->missions()->create([
            'user_id' => auth()->id(), 'type' => 'variety', 'title' => 'Explora tres ámbitos distintos',
            'target' => 3, 'progress' => 3, 'params' => [], 'half_rewarded_at' => now(), 'full_rewarded_at' => now(),
        ]);
        $this->get(route('game.season'))->assertOk()
            ->assertSee('Por comenzar')->assertSee('Recompensa parcial recibida')->assertSee('¡Misión cumplida!')
            ->assertSee('Recibida')->assertSee($task->title)->assertSee('Tarea eliminada')
            ->assertSee(route('tasks.list', ['edit' => $task->id]), false)
            ->assertSee(route('habits'), false)->assertDontSee('Objetivo:');
    }

    public function test_rewards_follow_configuration_and_large_targets_have_accessible_progress(): void
    {
        config(['game.mission_rewards.half' => 2, 'game.mission_rewards.full' => 4]);
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $week->missions()->create([
            'user_id' => auth()->id(), 'type' => 'variety', 'title' => 'Una misión con una meta mayor',
            'target' => 8, 'progress' => 3, 'params' => [],
        ]);
        $this->get(route('game.season'))->assertOk()
            ->assertSee('+2')->assertSee('+4')->assertSee('Al llegar a 4/8')
            ->assertSee('aria-valuetext="3 de 8 ámbitos"', false)->assertSee('En marcha');
        $this->get(route('game.week'))->assertOk()
            ->assertSee('game-quest__art', false)->assertSee('Una misión con una meta mayor'); // CHG-037: Semana también usa tarjetas de aventura.
    }

    public function test_view_without_week_does_not_generate_missions(): void
    {
        $this->withWeeklyMissions();
        $this->get(route('game.season'))->assertOk()->assertSee('Tu semana está por comenzar');
        $this->assertDatabaseCount('game_weeks', 0);
        $this->assertDatabaseCount('game_missions', 0);
    }

    public function test_season_and_missions_are_isolated_by_user(): void
    {
        app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal());
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $week->missions()->create([
            'user_id' => auth()->id(), 'type' => 'variety', 'title' => 'Misión de otra persona',
            'target' => 4, 'progress' => 1, 'params' => [],
        ]);
        $this->actingAs(User::factory()->create());
        $this->get(route('game.season'))->assertOk()
            ->assertDontSee('Temporada del Bosque')->assertDontSee('Misión de otra persona')
            ->assertSee('Tu semana está por comenzar');
    }

    public function test_completed_missions_are_celebrated_and_paused_season_is_not_active(): void
    {
        app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal());
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $week->missions()->create([
            'user_id' => auth()->id(), 'type' => 'variety', 'title' => 'Explora',
            'target' => 2, 'progress' => 2, 'params' => [], 'full_rewarded_at' => now(),
        ]);
        $this->get(route('game.season'))->assertOk()->assertSee('¡Todas las misiones completadas!');
        $this->gameSettings(['seasons_enabled' => false]);
        $this->get(route('game.season'))->assertOk()->assertSee('Sin temporada activa');
    }

    public function test_first_and_last_day_have_correct_temporal_stage(): void
    {
        $season = app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal())['season'];
        Carbon::setTestNow($season->starts_at);
        $this->get(route('game.season'))->assertOk()->assertSee(((int) ceil($season->starts_at->diffInDays($season->ends_at))).' días restantes')
            ->assertSee('aria-current="step"', false);
        Carbon::setTestNow($season->ends_at->copy()->subHour());
        $this->get(route('game.season'))->assertOk()->assertSee('1 día restante')
            ->assertSee('Semana 4');
        Carbon::setTestNow($season->ends_at);
        $this->get(route('game.season'))->assertOk()->assertSee('Sin temporada activa');
    }

    public function test_scheduled_season_is_announced_without_showing_its_bonuses_as_active(): void
    {
        $season = app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal())['season'];
        $season->update(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeeks(5)]);
        $this->get(route('game.season'))->assertOk()
            ->assertSee('Sin temporada activa')->assertSee('Siguiente:')
            ->assertDontSee('+50 % de experiencia');
    }

    public function test_director_switch_in_settings_pauses_proposals(): void
    {
        Livewire::test(GameSettingsPage::class)
            ->set('directorEnabled', false)
            ->call('save')
            ->assertSee('Ajustes guardados.');

        $this->assertFalse(app(GameSettings::class)->for(auth()->id())['director_enabled']);
        $this->assertSame('rejected', app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal())['status']);
    }

    public function test_cannot_revert_another_users_season(): void
    {
        $season = app(SeasonManager::class)->propose(auth()->id(), SeasonValidatorTest::validProposal())['season'];
        $this->actingAs(User::factory()->create());

        Livewire::test(GameSeasonView::class)->call('askRevert', $season->id)->call('revert')
            ->assertSee('No pudimos revertir esa temporada.');
        $this->assertSame(GameSeason::ACTIVE, $season->fresh()->status);
    }
}
