<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameBalls;
use App\Livewire\Game\GameCollection;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameSpecies;
use App\Models\Task;
use App\Models\User;
use App\Services\Game\CompanionProgress;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpeciesSheetTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        GameSpecies::where('name', 'Brotelo')->update(['description' => 'Duerme al sol.', 'types' => json_encode(['planta'])]);
        GameSpecies::where('name', 'Brotalia')->update(['description' => 'Florece de noche.']);
        $this->gameSettings(['daily_limit' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function openBallOf(string $name, string $title): void
    {
        $this->completeTask($title);
        $encounter = GameEncounter::latest('id')->first();
        $encounter->update(['species_id' => GameSpecies::where('name', $name)->value('id')]);
        Livewire::test(GameBalls::class)->call('open', $encounter->id);
    }

    public function test_discovered_species_shows_bio_types_and_companion(): void
    {
        $this->openBallOf('Brotelo', 'Pagar arriendo');
        $id = GameSpecies::where('name', 'Brotelo')->value('id');

        Livewire::test(GameCollection::class)
            ->call('showSpecies', $id)
            ->assertSet('showSheet', true)
            ->assertSee('Duerme al sol.')
            ->assertSee('planta')
            ->assertSee('Tu ejemplar')->assertSee('Nivel 1')->assertSee('Poder')
            ->assertSee('Evoluciona en el nivel 10')
            ->assertSee('Pagar arriendo');
    }

    public function test_undiscovered_species_shows_nothing_but_the_silhouette(): void
    {
        $id = GameSpecies::where('name', 'Brotalia')->value('id');

        Livewire::test(GameCollection::class)
            ->call('showSpecies', $id)
            ->assertSee('Aún no la descubres')
            ->assertDontSee('Florece de noche.');
    }

    public function test_history_is_newest_first_and_survives_source_deletion(): void
    {
        $this->openBallOf('Brotelo', 'Primera');
        $this->openBallOf('Brotelo', 'Segunda');
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00', 'America/Bogota'));
        $this->openBallOf('Brotelo', 'Tercera');
        Task::where('title', 'Primera')->delete();

        $html = Livewire::test(GameCollection::class)
            ->call('showSpecies', GameSpecies::where('name', 'Brotelo')->value('id'))
            ->html();

        $this->assertTrue(strpos($html, 'Tercera') < strpos($html, 'Segunda'));
        $this->assertTrue(strpos($html, 'Segunda') < strpos($html, 'Primera'));
    }

    public function test_previous_stage_stays_discovered_after_evolving(): void
    {
        GameSpecies::where('name', 'Brotalia')->update(['evolve_level' => 2]);
        $this->openBallOf('Brotelo', 'A');
        $companion = GameCompanion::first();
        $companion->update(['xp' => 35, 'level' => 1]);
        $this->openBallOf('Brotelo', 'B');

        $this->assertSame('Brotalia', $companion->fresh()->species->name);
        Livewire::test(GameCollection::class)
            ->assertSee('2 de 6 descubiertas')
            ->call('showSpecies', GameSpecies::where('name', 'Brotelo')->value('id'))
            ->assertSee('Duerme al sol.')
            ->assertSee('Tu ejemplar de esta línea es Brotalia');
    }

    public function test_branch_choice_from_the_sheet(): void
    {
        $branch = GameSpecies::create(['number' => 7, 'name' => 'Chispalma', 'line_key' => 'chispin', 'stage' => 2, 'rarity' => 'common', 'evolve_level' => 2]);
        GameSpecies::where('name', 'Chispazo')->update(['evolve_level' => 2]);
        $this->openBallOf('Chispin', 'A');
        $companion = GameCompanion::first();
        $companion->update(['xp' => 40, 'level' => 2]);

        Livewire::test(GameCollection::class)
            ->call('showSpecies', $companion->species_id)
            ->assertSee('Listo para evolucionar')
            ->assertSee('Evolucionar a Chispalma')
            ->call('evolve', $companion->id, $branch->id)
            ->assertSee('¡Evolucionó a Chispalma!');

        $this->assertSame('Chispalma', $companion->fresh()->species->name);
    }

    public function test_cannot_evolve_another_users_companion(): void
    {
        $this->openBallOf('Chispin', 'A');
        $companion = GameCompanion::first();
        $this->actingAs(User::factory()->create());

        Livewire::test(GameCollection::class)
            ->call('showSpecies', $companion->species_id)
            ->call('evolve', $companion->id, GameSpecies::where('name', 'Chispazo')->value('id'))
            ->assertSee('Esa evolución aún no está disponible.');
        $this->assertSame('Chispin', GameCompanion::withoutGlobalScopes()->find($companion->id)->species->name);
    }
}
