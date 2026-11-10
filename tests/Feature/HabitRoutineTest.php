<?php

namespace Tests\Feature;

use App\Livewire\Habit\HabitTracker;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Game\GameTestHelpers;
use Tests\TestCase;

class HabitRoutineTest extends TestCase
{
    use GameTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-13 20:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_routine_starts_with_pending_habits_of_the_period_in_order(): void
    {
        $floss = $this->habit('Seda Dental', '20:15', '2 min');
        $teeth = $this->habit('Lavarme los dientes', '20:00', '2 min');
        $diary = $this->habit('Llevar el diario', '20:30', '10 min');
        $this->habit('Desayuno', '08:00', '30 min', 'morning');
        HabitCompletion::create(['habit_id' => $diary->id, 'date' => '2026-07-13', 'completed' => true]);

        Livewire::test(HabitTracker::class)
            ->assertSee('Empezar rutina')
            ->call('startRoutine', 'night')
            ->assertSet('routine.queue', [$teeth->id, $floss->id])
            ->assertSee('Noche · 1 de 2')
            ->assertSee('Lavarme los dientes')
            ->assertSee('02:00');
    }

    public function test_done_completes_habit_and_skip_records_nothing(): void
    {
        $teeth = $this->habit('Lavarme los dientes', '20:00', '2 min');
        $floss = $this->habit('Seda Dental', '20:15', '2 min');

        Livewire::test(HabitTracker::class)
            ->call('startRoutine', 'night')
            ->call('routineDone')
            ->assertDispatched('habit-feedback')
            ->assertSee('Noche · 2 de 2')
            ->call('routineSkip')
            ->assertSee('Rutina terminada')
            ->assertSee('Hiciste 1 de 2')
            ->call('closeRoutine')
            ->assertSet('routine', null);

        $this->assertTrue((bool) HabitCompletion::where('habit_id', $teeth->id)->value('completed'));
        $this->assertFalse(HabitCompletion::where('habit_id', $floss->id)->exists());
    }

    public function test_skipped_habits_can_be_revisited(): void
    {
        $teeth = $this->habit('Lavarme los dientes', '20:00', '2 min');
        $floss = $this->habit('Seda Dental', '20:15', '2 min');
        $diary = $this->habit('Llevar el diario', '20:30', '10 min');

        Livewire::test(HabitTracker::class)
            ->call('startRoutine', 'night')
            ->call('routineSkip')
            ->call('routineDone')
            ->call('routineSkip')
            ->assertSet('routine.status', [(string) $teeth->id => 'skipped', (string) $floss->id => 'done', (string) $diary->id => 'skipped'])
            ->assertSee('Retomar saltados (2)')
            ->call('routineRetrySkipped')
            ->assertSet('routine.queue', [$teeth->id, $diary->id])
            ->assertSet('routine.index', 0)
            ->assertSee('Noche · 1 de 2')
            ->call('routineDone')
            ->call('routinePrevious')
            ->assertSet('routine.index', 0)
            ->call('routineGoTo', 1)
            ->assertSet('routine.index', 1)
            ->call('routineGoTo', 9)
            ->assertSet('routine.index', 1);

        $this->assertTrue((bool) HabitCompletion::where('habit_id', $teeth->id)->value('completed'));
    }

    public function test_overlay_is_teleported_to_body(): void
    {
        $this->habit('Lavarme los dientes', '20:00', '2 min');

        Livewire::test(HabitTracker::class)
            ->call('startRoutine', 'night')
            ->assertSeeHtml('x-teleport="body"')
            ->assertSee('Ir a Lavarme los dientes, pendiente');
    }

    public function test_period_without_pending_habits_does_not_start(): void
    {
        $teeth = $this->habit('Lavarme los dientes', '20:00', '2 min');
        HabitCompletion::create(['habit_id' => $teeth->id, 'date' => '2026-07-13', 'completed' => true]);

        Livewire::test(HabitTracker::class)
            ->assertDontSee('Empezar rutina')
            ->call('startRoutine', 'night')
            ->assertSet('routine', null);
    }

    public function test_companion_from_weekly_team_joins_the_routine(): void
    {
        $this->seedSpecies();
        $species = GameSpecies::where('name', 'Gotin')->first();
        GameCompanion::create(['line_key' => 'a', 'species_id' => $species->id, 'first_met_at' => now()]);
        $this->habit('Lavarme los dientes', '20:00', '2 min');

        Livewire::test(HabitTracker::class)
            ->call('startRoutine', 'night')
            ->assertSeeHtml('habit-routine__bubble')
            ->assertSee('¡Vamos con Lavarme los dientes!');
    }

    public function test_duration_text_is_parsed_to_seconds(): void
    {
        $this->assertSame(120, HabitTracker::routineSeconds('2 min'));
        $this->assertSame(3600, HabitTracker::routineSeconds('1 h'));
        $this->assertSame(90, HabitTracker::routineSeconds('90 s'));
        $this->assertSame(300, HabitTracker::routineSeconds('5'));
        $this->assertNull(HabitTracker::routineSeconds(null));
        $this->assertNull(HabitTracker::routineSeconds('a ratos'));
    }

    private function habit(string $name, string $time, string $duration, string $period = 'night'): HabitDefinition
    {
        return HabitDefinition::create([
            'name' => $name,
            'icon' => '🪥',
            'time_of_day' => $period,
            'goal_duration' => $duration,
            'base_time' => $time,
        ]);
    }
}
