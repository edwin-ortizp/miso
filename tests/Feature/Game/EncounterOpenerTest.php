<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\User;
use App\Services\Game\EncounterOpener;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncounterOpenerTest extends TestCase
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

    public function test_opening_weeks_later_reveals_the_species_decided_at_grant(): void
    {
        $this->completeTask();
        $encounter = GameEncounter::first();
        Carbon::setTestNow(Carbon::parse('2026-12-20 10:00:00', 'America/Bogota'));

        $result = app(EncounterOpener::class)->open($encounter->id, auth()->id());

        $this->assertSame($encounter->species_id, $result['species_id']);
        $this->assertTrue($result['is_new']);
        $this->assertSame('Pagar arriendo', $result['source_label']);
        $this->assertSame(1, GameCompanion::count());
    }

    public function test_opening_twice_does_not_duplicate_rewards(): void
    {
        $this->completeTask();
        $encounter = GameEncounter::first();
        $opener = app(EncounterOpener::class);

        $first = $opener->open($encounter->id, auth()->id());
        $second = $opener->open($encounter->id, auth()->id());

        $this->assertSame($first, $second);
        $this->assertSame(1, GameCompanion::count());
        $this->assertSame(1, GameEvent::where('kind', GameEvent::KIND_DISCOVERED)->count());
    }

    public function test_repeat_of_an_evolved_line_adds_xp_instead_of_a_second_companion(): void
    {
        config(['game.team_xp' => 0]); // Aísla la xp de apertura de la xp de equipo (CHG-021).
        $evolved = GameSpecies::where('name', 'Brotalia')->first();
        GameCompanion::create(['line_key' => 'brotelo', 'species_id' => $evolved->id, 'xp' => 5, 'first_met_at' => now()]);
        $this->completeTask();
        $encounter = GameEncounter::first();
        $encounter->update(['species_id' => GameSpecies::where('name', 'Brotelo')->value('id')]);

        $result = app(EncounterOpener::class)->open($encounter->id, auth()->id());

        $this->assertFalse($result['is_new']);
        $this->assertSame(10, $result['xp_gained']);
        $this->assertSame(1, GameCompanion::count());
        $companion = GameCompanion::first();
        $this->assertSame(15, $companion->xp);
        $this->assertSame($evolved->id, $companion->species_id);
    }

    public function test_cannot_open_another_users_ball(): void
    {
        $this->completeTask();
        $encounter = GameEncounter::first();
        $intruder = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        app(EncounterOpener::class)->open($encounter->id, $intruder->id);
    }

    public function test_pity_forces_a_new_line_after_threshold(): void
    {
        $this->gameSettings(['pity_threshold' => 2, 'daily_limit' => 50]);
        // Solo una línea conocida disponible en común: se fuerza novedad tras 2 balls sin ella.
        GameSpecies::whereNotIn('line_key', ['brotelo', 'gotin'])->update(['active' => false]);
        GameSpecies::where('line_key', 'gotin')->update(['rarity' => 'common']);
        GameCompanion::create(['line_key' => 'brotelo', 'species_id' => GameSpecies::where('name', 'Brotelo')->value('id'), 'first_met_at' => now()]);
        GameCompanion::create(['line_key' => 'gotin', 'species_id' => GameSpecies::where('name', 'Gotin')->value('id'), 'first_met_at' => now()]);
        foreach (range(1, 3) as $i) {
            $this->completeTask("T{$i}");
        }
        GameSpecies::where('line_key', 'chispin')->update(['active' => true]);
        $this->completeTask('T4');

        $last = GameEvent::where('kind', GameEvent::KIND_ACTIVITY)->latest('id')->first();
        $this->assertTrue($last->effect['forced_new']);
        $this->assertTrue($last->effect['new_line']);
    }
}
