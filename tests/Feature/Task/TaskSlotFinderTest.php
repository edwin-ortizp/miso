<?php

namespace Tests\Feature\Task;

use App\Livewire\Task\TaskSettings;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Services\Task\PlanningHours;
use App\Services\Task\TaskSlotFinder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-142: horario para planificar y huecos libres. */
class TaskSlotFinderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Martes 17 de noviembre de 2026, 07:00 en Bogotá.
        Carbon::setTestNow(Carbon::parse('2026-11-17 07:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function finder(): TaskSlotFinder
    {
        return app(TaskSlotFinder::class);
    }

    private function day(string $date = '2026-11-17'): Carbon
    {
        return Carbon::parse($date, 'America/Bogota');
    }

    private function timed(string $title, string $start, ?string $end = null, array $extra = [])
    {
        return $this->user->tasks()->create([
            'title' => $title, 'start_date' => Carbon::parse($start, 'America/Bogota'), 'start_is_date' => false,
            'end_date' => $end ? Carbon::parse($end, 'America/Bogota') : null, 'end_is_date' => false, ...$extra,
        ]);
    }

    private function starts(array $slots): array
    {
        return array_map(fn ($slot) => $slot['start']->format('D H:i'), $slots);
    }

    public function test_defaults_are_weekdays_8_to_18_and_saturday_9_to_12(): void
    {
        $hours = app(PlanningHours::class);

        $window = $hours->window($this->user->id, $this->day('2026-11-17'));
        $this->assertSame('08:00', $window['start']->format('H:i'));
        $this->assertSame('18:00', $window['end']->format('H:i'));

        $saturday = $hours->window($this->user->id, $this->day('2026-11-21'));
        $this->assertSame(['09:00', '12:00'], [$saturday['start']->format('H:i'), $saturday['end']->format('H:i')]);
        $this->assertNull($hours->window($this->user->id, $this->day('2026-11-22')));
        $this->assertSame(10, $hours->margin($this->user->id));
    }

    public function test_free_slots_respect_the_window_the_margin_and_the_past(): void
    {
        $this->timed('Reunión', '2026-11-17 10:00', '2026-11-17 11:00');

        $slots = $this->finder()->find($this->user->id, 60, $this->day(), $this->day(), ['limit' => 100]);
        $starts = $this->starts($slots);

        $this->assertSame('Tue 08:00', $starts[0]);
        $this->assertNotContains('Tue 09:00', $starts, 'Termina a las 10:00 y choca con el margen de 10 min.');
        $this->assertNotContains('Tue 10:00', $starts);
        $this->assertContains('Tue 11:10', $starts, 'El primer hueco tras la reunión empieza al terminar el margen.');
        $this->assertContains('Tue 12:30', $starts, 'Los siguientes se alinean a la media hora.');
        $this->assertContains('Tue 16:30', $starts);
        $this->assertNotContains('Tue 17:30', $starts, 'No cabe una hora antes de las 18:00.');
        foreach ($slots as $slot) {
            $this->assertEquals(60, $slot['start']->diffInMinutes($slot['end']));
        }
    }

    public function test_the_past_and_days_without_planning_are_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 09:12:00', 'America/Bogota'));

        $slots = $this->finder()->find($this->user->id, 30, $this->day('2026-11-17'), $this->day('2026-11-22'), ['limit' => 200]);
        $starts = $this->starts($slots);

        $this->assertSame('Tue 09:15', $starts[0], 'El primer hueco no está en el pasado.');
        $this->assertNotEmpty(array_filter($starts, fn ($s) => str_starts_with($s, 'Sat')));
        $this->assertEmpty(array_filter($starts, fn ($s) => str_starts_with($s, 'Sun')), 'El domingo no se planifica por defecto.');
        $this->assertEmpty(array_filter($starts, fn ($s) => str_starts_with($s, 'Sat') && $s >= 'Sat 12:00'));
    }

    public function test_meals_medications_completed_tasks_and_all_day_tasks_are_treated_correctly(): void
    {
        MealPlanEntry::create(['date' => '2026-11-17', 'meal_type' => 'almuerzo', 'status' => 'planned']);
        $this->user->medications()->create([
            'name' => 'Losartán', 'frequency_type' => 'daily', 'times' => ['14:00'], 'starts_on' => '2026-11-01', 'is_active' => true,
        ]);
        $this->timed('Ya hecha', '2026-11-17 09:00', '2026-11-17 10:00', ['completed' => true]);
        $this->user->tasks()->create(['title' => 'Todo el día', 'start_date' => '2026-11-17', 'start_is_date' => true, 'end_date' => '2026-11-17', 'end_is_date' => true]);

        $starts = $this->starts($this->finder()->find($this->user->id, 60, $this->day(), $this->day(), ['limit' => 100]));

        $this->assertContains('Tue 09:00', $starts, 'Una tarea completada no bloquea.');
        $this->assertNotContains('Tue 12:30', $starts, 'El almuerzo (12:30) ocupa la agenda.');
        $this->assertNotContains('Tue 13:30', $starts, 'Choca con el margen del almuerzo de 12:30 a 13:30.');
        $this->assertNotContains('Tue 14:00', $starts, 'La toma de las 14:00 ocupa la agenda.');
        $this->assertContains('Tue 15:30', $starts);
    }

    public function test_no_slots_when_the_day_is_full_or_not_planned(): void
    {
        $this->timed('Todo el día ocupado', '2026-11-17 07:30', '2026-11-17 18:30');

        $this->assertSame([], $this->finder()->find($this->user->id, 30, $this->day(), $this->day()));
        $this->assertSame([], $this->finder()->find($this->user->id, 30, $this->day('2026-11-22'), $this->day('2026-11-22')));
    }

    public function test_part_of_day_duration_and_task_exclusion(): void
    {
        $task = $this->timed('Mover esta', '2026-11-17 09:00', '2026-11-17 10:00');

        $morning = $this->starts($this->finder()->find($this->user->id, 60, $this->day(), $this->day(), ['part' => 'morning', 'limit' => 100]));
        $afternoon = $this->starts($this->finder()->find($this->user->id, 60, $this->day(), $this->day(), ['part' => 'afternoon', 'limit' => 100]));
        $this->assertNotEmpty($morning);
        $this->assertEmpty(array_filter($morning, fn ($s) => (int) substr($s, 4, 2) >= 12));
        $this->assertEmpty(array_filter($afternoon, fn ($s) => (int) substr($s, 4, 2) < 12));

        $this->assertNotContains('Tue 09:00', $morning);
        $excluding = $this->starts($this->finder()->find($this->user->id, 60, $this->day(), $this->day(), ['exclude_task_id' => (string) $task->id, 'limit' => 100]));
        $this->assertContains('Tue 09:00', $excluding, 'Al reubicar una tarea, su propio horario no la bloquea.');

        $this->assertNotEmpty($this->finder()->find($this->user->id, 240, $this->day(), $this->day(), ['limit' => 100]));
        $this->assertSame([], $this->finder()->find($this->user->id, 600, $this->day(), $this->day()));
    }

    public function test_conflicts_group_overlapping_blocks_and_mark_what_can_move(): void
    {
        $this->timed('Propuesta', '2026-11-17 09:00', '2026-11-17 10:30');
        $this->timed('Llamada', '2026-11-17 10:00', '2026-11-17 11:00');
        $this->timed('Aparte', '2026-11-17 15:00', '2026-11-17 16:00');
        MealPlanEntry::create(['date' => '2026-11-17', 'meal_type' => 'almuerzo', 'status' => 'planned']);

        $groups = $this->finder()->conflicts($this->user->id, $this->day(), $this->day());

        $this->assertCount(1, $groups);
        $this->assertEqualsCanonicalizing(['Propuesta', 'Llamada'], array_column($groups[0]['items'], 'title'));
        $this->assertSame('09:00', $groups[0]['start']->format('H:i'));
        $this->assertSame('11:00', $groups[0]['end']->format('H:i'));
        $this->assertTrue(collect($groups[0]['items'])->every(fn ($item) => $item['movable'] === true));
    }

    public function test_range_is_validated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->finder()->find($this->user->id, 60, $this->day('2026-11-17'), $this->day('2026-12-30'));
    }

    public function test_everything_is_scoped_to_the_user(): void
    {
        $other = User::factory()->create();
        $other->tasks()->create(['title' => 'De otra persona', 'start_date' => Carbon::parse('2026-11-17 09:00', 'America/Bogota'), 'start_is_date' => false,
            'end_date' => Carbon::parse('2026-11-17 10:00', 'America/Bogota'), 'end_is_date' => false]);

        $starts = $this->starts($this->finder()->find($this->user->id, 60, $this->day(), $this->day(), ['limit' => 100]));

        $this->assertContains('Tue 09:00', $starts);
        $this->assertSame([], $this->finder()->conflicts($this->user->id, $this->day(), $this->day()));
    }

    public function test_saved_hours_override_the_defaults_without_touching_other_settings_or_users(): void
    {
        $other = User::factory()->create();
        \App\Models\ModuleSetting::create(['module' => 'tasks', 'settings' => ['code_prefix' => 'ABC']]);
        $hours = app(PlanningHours::class);

        $days = PlanningHours::defaults()['days'];
        $days[6] = ['enabled' => true, 'start' => '10:00', 'end' => '13:00'];
        $days[3]['enabled'] = false;
        $hours->save($this->user->id, $days, 15);

        $fresh = new PlanningHours;
        $this->assertSame('10:00', $fresh->window($this->user->id, $this->day('2026-11-21'))['start']->format('H:i'));
        $this->assertNull($fresh->window($this->user->id, $this->day('2026-11-18')), 'El miércoles quedó sin planificar.');
        $this->assertSame(15, $fresh->margin($this->user->id));
        $this->assertSame('09:00', $fresh->window($other->id, $this->day('2026-11-21'))['start']->format('H:i'));
        $this->assertSame('ABC', \App\Models\ModuleSetting::withoutGlobalScopes()->where('user_id', $this->user->id)->where('module', 'tasks')->first()->settings['code_prefix']);

        $fresh->reset($this->user->id);
        $this->assertSame('09:00', (new PlanningHours)->window($this->user->id, $this->day('2026-11-21'))['start']->format('H:i'));
        $this->assertSame('ABC', \App\Models\ModuleSetting::withoutGlobalScopes()->where('user_id', $this->user->id)->where('module', 'tasks')->first()->settings['code_prefix']);
    }

    public function test_settings_screen_saves_validates_and_restores(): void
    {
        $this->actingAs($this->user);
        $this->get(route('tasks.settings.planning'))->assertOk()->assertSee('Horario para planificar')->assertSee('Margen entre bloques');

        Livewire::test(TaskSettings::class, ['section' => 'planning'])
            ->assertSet('planningDays.1.start', '08:00')
            ->assertSet('planningMargin', '10')
            ->set('planningDays.6.start', '10:00')
            ->set('planningDays.6.end', '13:00')
            ->set('planningDays.0.enabled', false)
            ->set('planningMargin', '15')
            ->call('savePlanning')
            ->assertHasNoErrors()
            ->assertSet('message', 'Horario guardado.')
            ->set('planningDays.2.end', '07:00')
            ->call('savePlanning')
            ->assertHasErrors(['planningDays.2.end'])
            ->set('planningDays.2.end', '18:00')
            ->set('planningMargin', '7')
            ->call('savePlanning')
            ->assertHasErrors(['planningMargin'])
            ->call('restorePlanning')
            ->assertSet('planningDays.6.start', '09:00')
            ->assertSet('planningMargin', '10');
    }
}
