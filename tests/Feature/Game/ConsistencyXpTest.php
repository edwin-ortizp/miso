<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GameSettingsPage;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameWeek;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEnergyLog;
use App\Services\Game\GameFeed;
use App\Services\Game\WeekPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-042: +1 xp de equipo por el primer registro del día de cada fuente. */
class ConsistencyXpTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private GameWeek $week;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();

        $species = GameSpecies::where('name', 'Gotin')->first();
        foreach (['a', 'b'] as $line) {
            GameCompanion::create(['line_key' => $line, 'species_id' => $species->id, 'first_met_at' => now()]);
        }
        $this->week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<int, int> */
    private function teamXp(): array
    {
        return GameCompanion::orderBy('id')->pluck('xp')->map(fn ($xp) => (int) $xp)->all();
    }

    private function journal(int $daysAgo = 0): JournalEntry
    {
        return JournalEntry::create(['date' => today()->subDays($daysAgo), 'text' => 'Hoy fue un buen día']);
    }

    private function fillUp(): VehicleEnergyLog
    {
        $vehicle = Vehicle::create(['name' => 'Moto', 'vehicle_type' => 'motorcycle', 'power_source' => 'gasoline']);

        return VehicleEnergyLog::create(['vehicle_id' => $vehicle->id, 'recorded_on' => today(), 'energy_source' => 'gasoline', 'quantity' => 3, 'unit' => 'gal']);
    }

    public function test_first_record_of_the_day_gives_one_xp_to_each_member(): void
    {
        $before = $this->teamXp();
        $this->journal();

        $this->assertSame(array_map(fn ($xp) => $xp + 1, $before), $this->teamXp());
        $this->assertSame(1, GameEvent::where('idempotency_key', 'micro:wellbeing:2026-11-11')->count());
    }

    public function test_second_record_same_day_adds_nothing_and_next_day_adds_again(): void
    {
        $before = $this->teamXp();
        $this->journal();
        // Un registro de un día pasado cuenta para hoy: cargar el pasado no multiplica la xp.
        $this->journal(3);
        $this->assertSame(array_map(fn ($xp) => $xp + 1, $before), $this->teamXp());

        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'America/Bogota'));
        $this->journal(-1);
        $this->assertSame(array_map(fn ($xp) => $xp + 2, $before), $this->teamXp());
    }

    public function test_disabled_source_or_game_creates_no_events(): void
    {
        $this->gameSettings(['sources' => ['wellbeing' => false]]);
        $this->journal();
        $this->assertSame(0, GameEvent::where('idempotency_key', 'like', 'micro:%')->count());

        $this->gameSettings(['enabled' => false, 'sources' => ['vehicle' => true]]);
        $this->fillUp();
        $this->assertSame(0, GameEvent::where('idempotency_key', 'like', 'micro:%')->count());
    }

    public function test_without_team_nothing_happens(): void
    {
        $this->actingAs(User::factory()->create());
        $this->journal();

        $this->assertSame(0, GameEvent::where('kind', GameEvent::KIND_XP_GAINED)->count());
    }

    public function test_never_grants_balls_or_mission_progress(): void
    {
        $this->journal();
        $this->fillUp();

        $this->assertSame(0, GameEncounter::count());
        $this->assertSame(0, DB::table('game_mission_contributions')->count());
    }

    public function test_each_source_is_detected_from_its_real_record(): void
    {
        $before = $this->teamXp();
        $this->fillUp();
        $this->journal();
        RelationshipEvent::factory()->create([
            'user_id' => auth()->id(),
            'relationship_id' => Relationship::factory()->create(['user_id' => auth()->id()])->id,
        ]);
        Purchase::create(['purchased_at' => now(), 'operation_key' => (string) Str::uuid()]);

        foreach (['vehicle', 'wellbeing', 'people', 'home'] as $source) {
            $this->assertSame(1, GameEvent::where('idempotency_key', "micro:{$source}:2026-11-11")->count(), $source);
        }
        $this->assertSame(array_map(fn ($xp) => $xp + 4, $before), $this->teamXp());
    }

    public function test_deleting_the_record_does_not_subtract(): void
    {
        $before = $this->teamXp();
        $this->journal()->delete();

        $this->assertSame(array_map(fn ($xp) => $xp + 1, $before), $this->teamXp());
    }

    public function test_feed_names_the_source_without_celebrating(): void
    {
        $last = (int) GameEvent::max('id');
        $this->fillUp();

        $result = app(GameFeed::class)->since(auth()->id(), $last);
        $this->assertFalse($result['celebrate']);
        $this->assertStringStartsWith('Constancia · Vehículo · ', $result['toasts'][0]['detail']);
    }

    public function test_settings_page_saves_each_consistency_source(): void
    {
        Livewire::test(GameSettingsPage::class)
            ->assertSee('Constancia')
            ->assertSet('consistency.vehicle', true)
            ->set('consistency.vehicle', false)
            ->call('save');

        Livewire::test(GameSettingsPage::class)->assertSet('consistency.vehicle', false)->assertSet('consistency.home', true);
    }
}
