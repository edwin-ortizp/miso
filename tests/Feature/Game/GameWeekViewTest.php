<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameWeekViewTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_empty_week_shows_friendly_states(): void
    {
        $this->get(route('game.week'))->assertOk()
            ->assertSee('Semana del 9 al 15 nov')
            ->assertSee('Tu equipo se arma cuando descubras tu primer Pokémon.')
            ->assertSee('Esta semana no hay misiones');
    }

    public function test_team_and_missions_are_shown(): void
    {
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => GameSpecies::where('name', 'Gotin')->value('id'), 'level' => 3, 'first_met_at' => now()]);
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        GameMission::create(['game_week_id' => $week->id, 'type' => GameMission::TYPE_CATEGORY, 'title' => 'Completa 4 tareas de Hogar', 'params' => ['category' => 'hogar'], 'target' => 4, 'progress' => 2, 'half_rewarded_at' => now()]);

        $this->get(route('game.week'))->assertOk()
            ->assertSee('Gotin')
            ->assertSee('Nv 3')
            ->assertSee('Afinidad')->assertSee('Poder')
            ->assertSee('Completa 4 tareas de Hogar')
            ->assertSee('Ball recibida');
    }

    public function test_other_users_week_is_not_visible(): void
    {
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => GameSpecies::where('name', 'Gotin')->value('id'), 'first_met_at' => now()]);
        app(WeekPlanner::class)->forWeek(auth()->id(), now());
        $this->actingAs(User::factory()->create());

        $this->get(route('game.week'))->assertOk()->assertDontSee('Gotin');
    }
}
