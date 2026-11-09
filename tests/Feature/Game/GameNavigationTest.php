<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameCollection;
use App\Livewire\Game\GameSettingsPage;
use App\Livewire\Task\TaskList;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\EncounterOpener;
use App\Services\Game\GameSettings;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameNavigationTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        GameSpecies::where('name', 'Brotelo')->update(['types' => json_encode(['planta'])]);
        GameSpecies::where('name', 'Chispin')->update(['types' => json_encode(['fuego'])]);
        $this->gameSettings(['daily_limit' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function discover(string $name): GameSpecies
    {
        $species = GameSpecies::where('name', $name)->first();
        GameDiscovery::create(['species_id' => $species->id, 'discovered_at' => now()]);

        return $species;
    }

    public function test_tabs_and_sidebar_show_counters(): void
    {
        $this->completeTask('A');
        $this->completeTask('B');
        $this->completeTask('C');
        $this->discover('Brotelo');

        $this->get(route('game.collection'))->assertOk()
            ->assertSee('3 por abrir')             // pestaña y menú (nombre accesible)
            ->assertSee('1 de 6 descubiertas')
            ->assertSee('1/6');

        app(EncounterOpener::class)->open(GameEncounter::first()->id, auth()->id());
        $this->get(route('game.balls'))->assertSee('2 por abrir');
    }

    public function test_counters_hidden_at_zero_and_isolated_per_user(): void
    {
        $this->completeTask('A');
        $this->actingAs(User::factory()->create());

        $this->get(route('game.balls'))->assertOk()->assertDontSee('por abrir</span>', false)->assertDontSee('1 por abrir');
    }

    public function test_other_modules_tabs_are_unchanged(): void
    {
        $this->get(route('habits'))->assertOk()->assertDontSee('md-count-badge', false);
    }

    public function test_collection_filters_search_and_url(): void
    {
        $this->discover('Brotelo');
        $this->discover('Chispin');

        Livewire::test(GameCollection::class)
            ->set('state', 'missing')
            ->assertSee('#002')->assertDontSee('#001')
            ->set('state', 'found')
            ->assertSee('Brotelo')->assertSee('Chispin')->assertDontSee('#005')
            ->set('type', 'fuego')
            ->assertSee('Chispin')->assertDontSee('Brotelo')
            ->set('type', '')
            ->set('rarity', 'common')
            ->set('search', 'brot')
            ->assertSee('Brotelo')->assertDontSee('Chispin')
            ->call('clearFilters')
            ->assertSet('state', 'all')->assertSet('search', '');

        $this->get(route('game.collection', ['state' => 'missing', 'q' => '6']))->assertOk()->assertSee('#006')->assertDontSee('#001');
    }

    public function test_search_by_name_does_not_reveal_undiscovered_species(): void
    {
        Livewire::test(GameCollection::class)->set('search', 'Rocon')->assertSee('Ningún resultado con estos filtros');
    }

    public function test_team_filter_and_pagination(): void
    {
        foreach (range(1, 70) as $n) {
            GameSpecies::create(['number' => 100 + $n, 'name' => "Extra {$n}", 'line_key' => "extra-{$n}"]);
        }
        $species = $this->discover('Gotin');
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => $species->id, 'first_met_at' => now()]);
        app(WeekPlanner::class)->forWeek(auth()->id(), now());

        Livewire::test(GameCollection::class)
            ->assertSee('Mostrando')
            ->assertSee('1–60')
            ->set('state', 'team')
            ->assertSee('Gotin')->assertDontSee('#001');
    }

    public function test_species_query_opens_the_sheet(): void
    {
        $species = $this->discover('Brotelo');

        Livewire::withQueryParams(['species' => $species->id])
            ->test(GameCollection::class)
            ->assertSet('showSheet', true)
            ->assertSee('Evoluciones');
    }

    public function test_unified_settings_save_everything(): void
    {
        $this->get(route('game.settings'))->assertOk()->assertSee('Juego')->assertSee('Director activo')->assertSee('Juego de temporadas');

        Livewire::test(GameSettingsPage::class)
            ->set('seasonsEnabled', false)
            ->set('directorEnabled', false)
            ->call('save');

        $settings = app(GameSettings::class)->for(auth()->id());
        $this->assertFalse($settings['seasons_enabled']);
        $this->assertFalse($settings['director_enabled']);
        $this->get(route('game.season'))->assertDontSee('name="game-director"', false);
    }

    public function test_missions_and_team_link_to_their_destinations(): void
    {
        $this->withWeeklyMissions();
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);
        $species = $this->discover('Gotin');
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => $species->id, 'first_met_at' => now()]);
        $week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
        GameMission::where('game_week_id', $week->id)->delete();
        GameMission::create(['game_week_id' => $week->id, 'type' => GameMission::TYPE_CATEGORY, 'title' => 'Completa 2 tareas de Hogar', 'params' => ['category' => 'hogar'], 'target' => 2]);

        $html = $this->get(route('game.week'))->assertOk()->assertSee('Ver tareas')->getContent();

        $this->assertStringContainsString(e(route('tasks.list', ['category' => ['hogar']])), $html);
        $this->assertStringContainsString(e(route('game.collection', ['species' => $species->id])), $html);

        // El enlace aplica el filtro de categoría que ya existe en Tareas.
        Livewire::withQueryParams(['category' => ['hogar']])->test(TaskList::class)->assertSet('categoryFilter', ['hogar']);
    }
}
