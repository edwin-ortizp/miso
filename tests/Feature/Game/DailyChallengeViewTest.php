<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameBalls;
use App\Livewire\Game\GameSettingsPage;
use App\Livewire\Home\Dashboard;
use App\Models\User;
use App\Services\Game\GameSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


/** CHG-077 · T3: el reto en el hero del Inicio, en Balls y su interruptor. */
class DailyChallengeViewTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        config(['game.daily_challenge.templates' => ['water_logs'], 'game.daily_challenge.targets.water_logs' => [2, 2]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_home_hero_shows_the_challenge_once_with_a_single_checkin_call(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Reto de hoy')->assertSeeHtml('aria-label="Tu día"')
            ->assertSee('Registra agua 2 veces')
            ->assertDontSee('Haz tu check-in de hoy')
            ->assertSeeHtml('role="progressbar"');
    }

    /** CHG-089: cada mosaico explica su mecánica con un ⓘ y el umbral configurado. */
    public function test_home_tiles_explain_streak_and_challenge(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSeeHtml('aria-label="Cómo funciona la racha"')
            ->assertSeeHtml('aria-label="Cómo funciona el reto del día"')
            ->assertSee('Tu racha sube cuando haces 3 acciones en al menos 2 áreas distintas')
            ->assertSee('Si lo cumples, ganas una ball extra. Es aparte de la racha');
    }

    public function test_checkin_is_not_a_default_challenge(): void
    {
        $defaults = (require config_path('game.php'))['daily_challenge']['templates'];
        $this->assertNotContains('checkin', $defaults);
    }

    public function test_balls_shows_the_challenge_with_a_link(): void
    {
        Livewire::test(GameBalls::class)
            ->assertSee('Registra agua 2 veces')
            ->assertSeeHtml('water/daily');
    }

    public function test_switch_hides_it(): void
    {
        Livewire::test(GameSettingsPage::class)->set('dailyChallenge', false)->call('save');

        $this->assertFalse(app(GameSettings::class)->for(auth()->id())['daily_challenge']);
        Livewire::test(Dashboard::class)->assertDontSee('Reto de hoy');
    }
}
