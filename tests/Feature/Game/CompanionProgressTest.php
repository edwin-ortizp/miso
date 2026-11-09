<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\CompanionProgress;
use App\Services\Game\EncounterOpener;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CompanionProgressTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        config(['game.daily_limit' => 50]);
        $this->gameSettings(['daily_limit' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function companion(string $speciesName, int $xp = 0): GameCompanion
    {
        $species = GameSpecies::where('name', $speciesName)->first();
        $progress = app(CompanionProgress::class);

        return GameCompanion::create([
            'line_key' => $species->line_key, 'species_id' => $species->id,
            'xp' => $xp, 'level' => $progress->levelFor($xp), 'first_met_at' => now(),
        ]);
    }

    /** Abre una ball forzada a la especie dada y devuelve el resultado. */
    private function openBallOf(string $speciesName, string $title): array
    {
        $this->completeTask($title);
        $encounter = GameEncounter::latest('id')->first();
        $encounter->update(['species_id' => GameSpecies::where('name', $speciesName)->value('id')]);

        return app(EncounterOpener::class)->open($encounter->id, auth()->id());
    }

    public function test_level_is_derived_from_xp_and_capped(): void
    {
        $progress = app(CompanionProgress::class);

        $this->assertSame(1, $progress->levelFor(0));
        $this->assertSame(1, $progress->levelFor(39));
        $this->assertSame(2, $progress->levelFor(40));
        $this->assertSame(100, $progress->levelFor(1_000_000));
    }

    public function test_level_up_is_recorded_with_its_origin(): void
    {
        $this->companion('Brotelo', 35);

        $result = $this->openBallOf('Brotelo', 'Pagar arriendo');

        $this->assertSame(1, $result['level_from']);
        $this->assertSame(2, $result['level_to']);
        $event = GameEvent::where('kind', CompanionProgress::KIND_LEVEL_UP)->first();
        $this->assertSame('Pagar arriendo', $event->source_label);
    }

    public function test_evolves_automatically_at_required_level_and_keeps_previous_stage(): void
    {
        // Etapa 2 por defecto en nivel 10 → 360 xp. Con 355 + 10 evoluciona.
        $companion = $this->companion('Brotelo', 355);
        app(CompanionProgress::class)->discover(auth()->id(), $companion->species_id);

        $result = $this->openBallOf('Brotelo', 'Regar');

        $this->assertTrue($result['evolved']);
        $this->assertSame('Brotelo', $result['evolved_from_name']);
        $this->assertSame('Brotalia', $result['evolved_to_name']);
        $this->assertSame('Brotalia', $companion->fresh()->species->name);
        $this->assertSame(2, GameDiscovery::count());
        $this->assertSame(1, GameEvent::where('kind', CompanionProgress::KIND_EVOLVED)->count());
        $this->assertSame(1, GameCompanion::count());
    }

    public function test_species_evolve_level_overrides_the_default(): void
    {
        GameSpecies::where('name', 'Brotalia')->update(['evolve_level' => 2]);
        $this->companion('Brotelo', 35);

        $this->assertTrue($this->openBallOf('Brotelo', 'X')['evolved']);
    }

    public function test_branching_line_waits_for_the_users_choice(): void
    {
        GameSpecies::create(['number' => 7, 'name' => 'Chispalma', 'line_key' => 'chispin', 'stage' => 2, 'rarity' => 'common']);
        $companion = $this->companion('Chispin', 355);

        $result = $this->openBallOf('Chispin', 'Y');

        $this->assertFalse($result['evolved']);
        $this->assertTrue($result['ready_to_evolve']);
        $this->assertSame('Chispin', $companion->fresh()->species->name);

        $target = app(CompanionProgress::class)->evolveTo($companion->id, GameSpecies::where('name', 'Chispalma')->value('id'), auth()->id());
        $this->assertSame('Chispalma', $target->name);
        $this->assertSame('Chispalma', $companion->fresh()->species->name);
    }

    public function test_evolve_to_rejects_invalid_targets_or_low_level(): void
    {
        $companion = $this->companion('Chispin', 0);

        $this->expectException(InvalidArgumentException::class);
        app(CompanionProgress::class)->evolveTo($companion->id, GameSpecies::where('name', 'Chispazo')->value('id'), auth()->id());
    }

    public function test_opening_twice_never_gives_double_xp(): void
    {
        config(['game.team_xp' => 0]); // Aísla la xp de apertura de la xp de equipo (CHG-021).
        $companion = $this->companion('Brotelo', 0);
        $this->completeTask('Z');
        $encounter = GameEncounter::latest('id')->first();
        $encounter->update(['species_id' => $companion->species_id]);
        $opener = app(EncounterOpener::class);

        $opener->open($encounter->id, auth()->id());
        $opener->open($encounter->id, auth()->id());

        $this->assertSame(10, $companion->fresh()->xp);
    }

    public function test_new_line_gets_welcome_xp_and_a_discovery(): void
    {
        $result = $this->openBallOf('Gotin', 'Nueva');

        $this->assertTrue($result['is_new']);
        $this->assertSame(5, GameCompanion::first()->xp);
        $this->assertSame(1, GameDiscovery::count());
    }
}
