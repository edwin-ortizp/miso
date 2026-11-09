<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameSeasonView;
use App\Models\Game\GameSeason;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\GameSettings;
use App\Services\Game\SeasonManager;
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

    public function test_director_switch_in_settings_pauses_proposals(): void
    {
        Livewire::test(\App\Livewire\Game\GameSettingsPage::class)
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
