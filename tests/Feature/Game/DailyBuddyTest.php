<?php

namespace Tests\Feature\Game;

use App\Livewire\Home\Dashboard;
use App\Livewire\Home\WallPanel;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Game\GetGameDirectorContextTool;
use App\Mcp\Tools\Game\ProposeGameQuotesTool;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameQuote;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\DailyBuddy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-043: integrante del día con frase en el panel de pared y el dashboard. */
class DailyBuddyTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function companion(): GameCompanion
    {
        $species = GameSpecies::where('name', 'Gotin')->first();

        return GameCompanion::create(['line_key' => 'a', 'species_id' => $species->id, 'first_met_at' => now()]);
    }

    /** @return array<int, string> */
    private function quotes(int $n = 7): array
    {
        return array_map(fn ($i) => "Frase amable número {$i}.", range(1, $n));
    }

    public function test_panel_and_dashboard_show_a_companion_with_a_quote(): void
    {
        $companion = $this->companion();

        foreach ([WallPanel::class, Dashboard::class] as $component) {
            Livewire::test($component)
                ->assertSee('Gotin · Nv '.$companion->level)
                ->assertSee('Ver ficha de Gotin')
                ->assertSee('/game/collection?species='.$companion->species_id, false);
        }
    }

    public function test_panel_gives_the_fifth_day_column_to_the_companion(): void
    {
        $this->companion();
        $html = Livewire::test(WallPanel::class)->html();

        $now = substr($html, strpos($html, 'class="wall-now"'), strpos($html, 'class="wall-next"') - strpos($html, 'class="wall-now"'));
        $this->assertStringNotContainsString('game-buddy', $now);
        $this->assertStringContainsString('wall-days has-buddy', $html);
        $this->assertStringContainsString('game-buddy-stage', $html);
        $this->assertSame(4, substr_count($html, 'wire:key="day-'));
    }

    public function test_panel_column_has_type_stage_mood_and_ball_notice(): void
    {
        $this->companion();
        GameSpecies::where('name', 'Gotin')->update(['types' => ['Agua']]);

        Livewire::test(WallPanel::class)
            ->assertSeeHtml('game-stage--')
            ->assertSee('Día libre, disfrútalo')
            ->assertDontSee('Tienes 1 ball');

        $this->completeTask('Regar plantas');
        Livewire::test(WallPanel::class)->assertSee('Tienes 1 ball');
    }

    public function test_another_quote_changes_only_the_quote(): void
    {
        $this->companion();
        $panel = Livewire::test(WallPanel::class);
        $before = $panel->get('buddy');

        $panel->call('anotherQuote');
        $after = $panel->get('buddy');

        $this->assertNotSame($before['quote'], $after['quote']);
        $this->assertSame($before['species_id'], $after['species_id']);
        $panel->assertSee($after['quote']);
    }

    public function test_without_companions_the_panel_keeps_five_days(): void
    {
        $html = Livewire::test(WallPanel::class)->html();

        $this->assertStringNotContainsString('game-buddy-stage', $html);
        $this->assertSame(5, substr_count($html, 'wire:key="day-'));
    }

    public function test_empty_state_appears_on_the_dashboard_but_not_on_the_panel(): void
    {
        Livewire::test(WallPanel::class)->assertDontSee('Abre tu primera ball');
        Livewire::test(Dashboard::class)->assertSee('Abre tu primera ball');
    }

    public function test_the_panel_refresh_keeps_the_same_companion(): void
    {
        $this->companion();
        $panel = Livewire::test(WallPanel::class);
        $buddy = $panel->get('buddy');

        $panel->dispatch('panel-changed')->assertSet('buddy', $buddy);
    }

    public function test_empty_collection_invites_to_open_the_first_ball(): void
    {
        Livewire::test(Dashboard::class)->assertSee('Abre tu primera ball para conocer a tu compañero.')->assertSee('Ir a Balls');
    }

    public function test_hidden_when_the_game_is_off(): void
    {
        $this->companion();
        $this->gameSettings(['enabled' => false]);

        Livewire::test(Dashboard::class)->assertSet('buddy', null)->assertDontSee('Ver ficha de');
    }

    public function test_old_director_quotes_are_not_picked_but_seeds_always_are(): void
    {
        GameQuote::query()->delete();
        $old = GameQuote::create(['user_id' => $this->user->id, 'text' => 'Vieja', 'source' => GameQuote::SOURCE_DIRECTOR]);
        $old->forceFill(['created_at' => now()->subDays(61)])->save();
        $this->assertContains(app(DailyBuddy::class)->quote($this->user->id), GameQuote::SEED);

        GameQuote::create(['user_id' => null, 'text' => 'Semilla', 'source' => GameQuote::SOURCE_SEED]);
        $this->assertSame('Semilla', app(DailyBuddy::class)->quote($this->user->id));
    }

    public function test_seed_quotes_pass_the_validator(): void
    {
        $this->assertCount(30, GameQuote::SEED);
        foreach (array_chunk(GameQuote::SEED, 10) as $chunk) {
            $this->assertSame([], app(\App\Services\Game\QuoteValidator::class)->validate($chunk)['errors']);
        }
    }

    public function test_director_adds_quotes_once_per_week(): void
    {
        $server = LifeTrackerServer::actingAs($this->user);
        $server->tool(GetGameDirectorContextTool::class, [])->assertSee('"quotes_due": true');

        $server->tool(ProposeGameQuotesTool::class, ['quotes' => $this->quotes()])->assertOk()->assertSee('"status":"ok"');
        $this->assertSame(7, GameQuote::where('user_id', $this->user->id)->count());

        $server->tool(ProposeGameQuotesTool::class, ['quotes' => $this->quotes(8)])->assertSee('"status":"ya_propuestas"');
        $this->assertSame(7, GameQuote::where('user_id', $this->user->id)->count());
        $server->tool(GetGameDirectorContextTool::class, [])->assertSee('"quotes_due": false');
    }

    public function test_invalid_quotes_are_rejected_entirely(): void
    {
        $server = LifeTrackerServer::actingAs($this->user);
        $server->tool(ProposeGameQuotesTool::class, ['quotes' => $this->quotes(3)])->assertHasErrors();

        $bad = $this->quotes();
        $bad[0] = 'Debes hacerlo hoy.';
        $bad[1] = str_repeat('a', 141);
        $server->tool(ProposeGameQuotesTool::class, ['quotes' => $bad])->assertHasErrors(['debes']);

        $this->assertSame(0, GameQuote::where('user_id', $this->user->id)->count());
    }

    public function test_paused_director_cannot_add_quotes(): void
    {
        $this->gameSettings(['director_enabled' => false]);

        LifeTrackerServer::actingAs($this->user)->tool(ProposeGameQuotesTool::class, ['quotes' => $this->quotes()])
            ->assertSee('"status":"director_paused"');
        $this->assertSame(0, GameQuote::where('user_id', $this->user->id)->count());
    }
}
