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

    public function test_cards_show_full_activity_and_reveal_has_description_and_footer_actions(): void
    {
        $title = 'Preparar todos los materiales para la próxima reunión y compartir las conclusiones con el equipo';
        $this->completeTask($title);
        $this->completeTask('Un pequeño paso para ordenar la casa');
        $this->completeTask('Dedicar un momento a una idea nueva');
        $this->get(route('game.balls'))->assertOk()->assertSee($title)->assertSee('Abrir Poké Ball');
        $encounter = GameEncounter::first();
        $species = GameSpecies::find($encounter->species_id);
        $species->update(['description' => str_repeat('Le gusta descansar al sol y explorar con sus compañeros. ', 12), 'types' => ['planta']]);
        $component = Livewire::test(GameBalls::class)->call('open', $encounter->id)
            ->assertSet('showReveal', true)->assertSet('revealKey', 1)
            ->assertSee('Le gusta descansar al sol')->assertSee('planta')->assertSee('Mostrar ahora');
        $doc = new \DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$component->html());
        $xpath = new \DOMXPath($doc);
        $this->assertSame(1, $xpath->query('//footer//a[contains(text(), "Ver ficha")]')->length);
        $this->assertSame(1, $xpath->query('//footer//button[contains(text(), "Abrir otra")]')->length);
    }

    public function test_reopening_is_idempotent_and_last_ball_has_no_open_another_action(): void
    {
        $this->completeTask();
        $encounter = GameEncounter::first();
        $component = Livewire::test(GameBalls::class)->call('open', $encounter->id);
        $xp = GameCompanion::first()->xp;
        $component->call('open', $encounter->id)->assertSet('revealKey', 2)
            ->assertSee('Aún no tenemos una descripción de este Pokémon')->assertDontSee('Abrir otra');
        $this->assertSame($xp, GameCompanion::first()->xp);
        $component->call('openNext')->assertSet('showReveal', false);
    }

    public function test_repeated_companion_can_level_and_evolve_with_original_species_bio_identified(): void
    {
        $this->completeTask('Un paso que suma experiencia');
        $encounter = GameEncounter::first();
        $original = GameSpecies::where('name', 'Brotelo')->first();
        $original->update(['description' => 'La descripción de Brotelo.']);
        $encounter->update(['species_id' => $original->id]);
        GameCompanion::create([
            'species_id' => $original->id, 'line_key' => $original->line_key,
            'xp' => 355, 'level' => 9, 'first_met_at' => now(),
        ]);
        Livewire::test(GameBalls::class)->call('open', $encounter->id)
            ->assertSee('¡Evolución!')->assertSee('Brotalia')->assertSee('Subió al nivel 10')
            ->assertSee('Sobre Brotelo')->assertSee('La descripción de Brotelo.');
        $this->assertSame(1, GameCompanion::count());
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
