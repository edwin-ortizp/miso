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
        config(['game.daily_challenge.templates' => ['checkin']]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_home_hero_shows_the_challenge_and_opens_the_checkin(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Reto de hoy · Uno al día')
            ->assertSee('Haz tu check-in de hoy')
            ->assertSeeHtml('role="progressbar"');
    }

    public function test_balls_shows_the_challenge_with_a_link(): void
    {
        Livewire::test(GameBalls::class)
            ->assertSee('Haz tu check-in de hoy')
            ->assertSeeHtml('checkin=1');
    }

    public function test_switch_hides_it(): void
    {
        Livewire::test(GameSettingsPage::class)->set('dailyChallenge', false)->call('save');

        $this->assertFalse(app(GameSettings::class)->for(auth()->id())['daily_challenge']);
        Livewire::test(Dashboard::class)->assertDontSee('Reto de hoy');
    }
}
