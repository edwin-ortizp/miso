<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameBalls;
use App\Livewire\Game\GameCollection;
use App\Livewire\Game\GameSettingsPage;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\GameSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameUiTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pages_render_with_empty_states(): void
    {
        $this->get(route('game.balls'))->assertOk()->assertSee('No tienes balls por abrir');
        $this->get(route('game.collection'))->assertOk()->assertSee('0 de 6 descubiertas');
        $this->get(route('game.settings'))->assertOk()->assertSee('Máximo de balls por día');
    }

    public function test_open_ball_from_ui_survives_reload(): void
    {
        $this->completeTask('Pagar arriendo');
        $encounter = GameEncounter::first();

        // Recargar antes de abrir: la ball sigue ahí.
        $this->get(route('game.balls'))->assertSee('1 por abrir')->assertSee('Pagar arriendo');

        Livewire::test(GameBalls::class)
            ->call('open', $encounter->id)
            ->assertSet('showReveal', true)
            ->assertSee('¡Nueva!')
            ->assertSee('Llegó por: Pagar arriendo');

        // Recargar después de abrir: ya no está pendiente y aparece en la colección.
        $this->get(route('game.balls'))->assertSee('0 por abrir');
        $name = GameSpecies::find($encounter->species_id)->name;
        Livewire::test(GameCollection::class)->assertSee('1 de 6 descubiertas')->assertSee($name);
    }

    public function test_cannot_open_another_users_ball_from_ui(): void
    {
        $this->completeTask();
        $encounter = GameEncounter::first();
        $this->actingAs(User::factory()->create());

        Livewire::test(GameBalls::class)
            ->call('open', $encounter->id)
            ->assertSet('showReveal', false)
            ->assertSee('Tu ball sigue guardada');

        $this->assertNull($encounter->fresh()->opened_at);
        $this->assertSame(0, GameCompanion::withoutGlobalScopes()->count());
    }

    public function test_other_users_balls_are_not_listed(): void
    {
        $this->completeTask('Secreto ajeno');
        $this->actingAs(User::factory()->create());

        $this->get(route('game.balls'))->assertDontSee('Secreto ajeno')->assertSee('0 por abrir');
    }

    public function test_settings_are_saved_and_disabling_keeps_progress(): void
    {
        $this->completeTask();

        Livewire::test(GameSettingsPage::class)
            ->set('enabled', false)
            ->set('dailyLimit', 3)
            ->call('save')
            ->assertSee('Ajustes guardados.');

        $settings = app(GameSettings::class)->for(auth()->id());
        $this->assertFalse($settings['enabled']);
        $this->assertSame(3, $settings['daily_limit']);
        $this->assertSame(1, GameEncounter::count());
    }

    public function test_daily_limit_out_of_range_is_rejected(): void
    {
        Livewire::test(GameSettingsPage::class)->set('dailyLimit', 500)->call('save')->assertHasErrors('dailyLimit');
    }
}
