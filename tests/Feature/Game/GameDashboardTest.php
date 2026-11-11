<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameBalls;
use App\Livewire\Game\GameSettingsPage;
use App\Livewire\Home\Dashboard;
use App\Models\Game\GameEvent;
use App\Models\User;
use App\Services\Game\GameSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-076 · T3: racha en el hero del Inicio, en Balls y su ajuste. */
class GameDashboardTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function act(string $source): void
    {
        GameEvent::withoutGlobalScopes()->create([
            'user_id' => auth()->id(), 'idempotency_key' => Str::uuid()->toString(), 'kind' => GameEvent::KIND_ACTIVITY,
            'source_label' => 'x', 'effect' => ['source' => $source, 'ball' => false], 'occurred_at' => now(),
        ]);
    }

    public function test_hero_shows_the_streak_line_without_history(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Empieza hoy')
            ->assertSee('Hoy 0 de 3 acciones · te faltan 3.')
            ->assertSeeHtml('game-day-tile');
    }

    public function test_hero_shows_progress_and_completed_day(): void
    {
        $this->act('tasks');
        $this->act('habits');
        Livewire::test(Dashboard::class)->assertSee('Hoy 2 de 3 acciones · te falta 1.');

        $this->act('habits');
        Livewire::test(Dashboard::class)->assertSee('1 día')->assertSee('Hoy ya está: 3 de 3 acciones.');
    }

    public function test_broken_streak_is_not_mentioned_on_a_return_day(): void
    {
        // Mejor racha previa de 1 día y una ausencia larga: hoy hay bienvenida de regreso.
        DB::table('user_active_days')->insert([
            'user_id' => auth()->id(), 'date' => '2026-11-01', 'first_channel' => 'direct', 'records_count' => 3,
            'streak_met' => true, 'streak_threshold' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(Dashboard::class)
            ->assertDontSee('Tu mejor racha fue de')
            ->assertSee('Empieza hoy');
    }

    public function test_hidden_streak_is_not_rendered(): void
    {
        app(GameSettings::class)->save(auth()->id(), ['streak_visible' => false]);

        Livewire::test(Dashboard::class)->assertDontSee('Acciones de hoy para la racha');
        Livewire::test(GameBalls::class)->assertDontSee('Tu racha');
    }

    public function test_balls_page_shows_the_streak_card(): void
    {
        Livewire::test(GameBalls::class)
            ->assertSee('Tu racha')
            ->assertSee('Gracia: 2 de 2 en noviembre')
            ->assertSee('¿Cómo funciona?')
            ->assertSeeHtml('aria-label="Últimos 7 días"');
    }

    public function test_settings_save_and_validate_the_threshold(): void
    {
        Livewire::test(GameSettingsPage::class)
            ->set('streakThreshold', 11)->call('save')->assertHasErrors('streakThreshold')
            ->set('streakThreshold', 5)->set('streakVisible', false)->call('save')->assertHasNoErrors();

        $settings = app(GameSettings::class)->for(auth()->id());
        $this->assertSame(5, $settings['streak_threshold']);
        $this->assertFalse($settings['streak_visible']);
    }
}
