<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameSeasonView;
use App\Models\Game\GameSeason;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-079 · T3: el camino en la pestaña Temporada y sus estados. */
class GameSeasonPathViewTest extends TestCase
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

    private function season(string $start, string $status = GameSeason::ACTIVE): GameSeason
    {
        return GameSeason::create([
            'user_id' => auth()->id(), 'status' => $status, 'name' => 'Brisa de otoño', 'narrative' => 'x', 'config' => [],
            'source' => 'manual', 'starts_at' => Carbon::parse($start), 'ends_at' => Carbon::parse($start)->addWeeks(4),
        ]);
    }

    public function test_active_season_shows_seven_stops_and_next_reward(): void
    {
        $this->season('2026-11-08');

        Livewire::test(GameSeasonView::class)
            ->assertSee('Camino de la temporada')
            ->assertSee('0 de 80 puntos')
            ->assertSee('Haz tu primera actividad para avanzar.')
            ->assertSee('Parada 1 · 1 ball, faltan 4 puntos')
            ->assertSee('Parada 7 · 2 balls, faltan 80 puntos')
            ->assertSee('Cada actividad suma 1 (hasta 5 al día); cada misión cumplida, 3.');
    }

    public function test_without_active_season_there_is_no_broken_path(): void
    {
        $this->season('2026-12-01');

        Livewire::test(GameSeasonView::class)->assertDontSee('Camino de la temporada');
    }
}
