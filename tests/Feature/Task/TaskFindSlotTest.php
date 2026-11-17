<?php

namespace Tests\Feature\Task;

use App\Livewire\Task\FindSlot;
use App\Livewire\Task\TaskList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-143: ventana «Buscar un hueco» y agendado con Deshacer. */
class TaskFindSlotTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-17 07:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(array $extra = [])
    {
        return $this->user->tasks()->create(['title' => 'Preparar informe', 'estimated_time' => 60, ...$extra]);
    }

    private function busy(string $start, string $end)
    {
        return $this->user->tasks()->create(['title' => 'Reunión', 'start_date' => Carbon::parse($start, 'America/Bogota'), 'start_is_date' => false,
            'end_date' => Carbon::parse($end, 'America/Bogota'), 'end_is_date' => false]);
    }

    public function test_opens_with_the_tasks_duration_and_shows_suggested_free_slots(): void
    {
        $task = $this->task(['estimated_time' => 90]);
        $this->busy('2026-11-17 10:00', '2026-11-17 11:00');

        Livewire::test(FindSlot::class)
            ->dispatch('task-find-slot', id: $task->id)
            ->assertSet('open', true)
            ->assertSet('minutes', '90')
            ->assertSee('Buscar un hueco')
            ->assertSee('Preparar informe')
            ->assertSee('Horarios sugeridos')
            ->assertSee('Hoy, 17 de noviembre')
            ->assertSee('08:00–09:30')
            ->assertDontSee('08:30–10:00')
            ->assertSee('Según tu horario de 08:00 a 18:00');
    }

    public function test_without_an_estimate_it_proposes_an_hour_and_the_duration_can_change(): void
    {
        $task = $this->task(['estimated_time' => null]);

        Livewire::test(FindSlot::class)
            ->call('openFor', $task->id)
            ->assertSet('minutes', '60')
            ->set('minutes', '30')
            ->assertSee('08:00–08:30');
    }

    public function test_choosing_a_slot_schedules_the_task_and_can_be_undone(): void
    {
        $task = $this->task(['start_date' => null]);

        Livewire::test(FindSlot::class)
            ->call('openFor', $task->id)
            ->call('select', '2026-11-18 08:00')
            ->assertSee('Agendar Mié 18 · 08:00')
            ->assertSee('Tu día')
            ->call('schedule')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertSee('Tarea agendada el miércoles 18 de noviembre a las 08:00.')
            ->assertSee('Deshacer')
            ->assertDispatched('task-calendar-refresh');

        $task->refresh();
        $this->assertSame('2026-11-18 08:00', $task->start_date->format('Y-m-d H:i'));
        $this->assertSame('2026-11-18 09:00', $task->end_date->format('Y-m-d H:i'));
        $this->assertFalse($task->start_is_date);
        $this->assertSame(60, $task->estimated_time);

        Livewire::test(FindSlot::class)->call('openFor', $task->id)->call('select', '2026-11-18 14:00')->call('schedule')->call('undoSchedule')
            ->assertSee('la tarea quedó como estaba');
        $this->assertSame('2026-11-18 08:00', $task->fresh()->start_date->format('Y-m-d H:i'));
    }

    public function test_undo_restores_the_previous_schedule(): void
    {
        $task = $this->task(['start_date' => Carbon::parse('2026-11-19 16:00', 'America/Bogota'), 'start_is_date' => false, 'end_date' => null, 'end_is_date' => true, 'estimated_time' => 45]);

        Livewire::test(FindSlot::class)->call('openFor', $task->id)->call('select', '2026-11-17 09:00')->call('schedule')->call('undoSchedule');

        $task->refresh();
        $this->assertSame('2026-11-19 16:00', $task->start_date->format('Y-m-d H:i'));
        $this->assertNull($task->end_date);
        $this->assertSame(45, $task->estimated_time);
    }

    public function test_a_slot_taken_in_the_meantime_is_rejected_and_nothing_is_written(): void
    {
        $task = $this->task();
        $component = Livewire::test(FindSlot::class)->call('openFor', $task->id)->call('select', '2026-11-17 10:00');
        $this->busy('2026-11-17 10:00', '2026-11-17 11:00');

        $component->call('schedule')->assertHasErrors('selected')->assertSet('selected', null)->assertSet('open', true);

        $this->assertNull($task->fresh()->start_date);
    }

    public function test_it_requires_a_selection(): void
    {
        $task = $this->task();

        Livewire::test(FindSlot::class)->call('openFor', $task->id)->call('schedule')->assertHasErrors('selected');
        $this->assertNull($task->fresh()->start_date);
    }

    public function test_a_fixed_or_completed_task_does_not_open_the_window(): void
    {
        $fixed = $this->task(['schedule_locked' => true]);
        $done = $this->task(['title' => 'Hecha', 'completed' => true]);

        Livewire::test(FindSlot::class)->call('openFor', $fixed->id)->assertSet('open', false)->assertSee('horario fijo');
        Livewire::test(FindSlot::class)->call('openFor', $done->id)->assertSet('open', false)->assertSee('ya está completa');
    }

    public function test_empty_state_offers_the_next_week_and_part_of_day_filters(): void
    {
        $task = $this->task(['estimated_time' => 480]);
        foreach (['17', '18', '19', '20'] as $day) {
            $this->busy("2026-11-{$day} 12:00", "2026-11-{$day} 13:00");
        }

        Livewire::test(FindSlot::class)->call('openFor', $task->id)
            ->set('minutes', '480')->assertSee('No encontré huecos de 480 min')->assertSee('Ver la próxima semana')
            ->set('minutes', '60')->set('range', 'next')->assertDontSee('No encontré huecos')
            ->set('part', 'afternoon')->assertSee('14:00–15:00')->assertDontSee('08:00–09:00');
    }

    public function test_it_cannot_open_or_touch_another_users_task(): void
    {
        $task = $this->task();
        $this->actingAs(User::factory()->create());

        Livewire::test(FindSlot::class)->call('openFor', $task->id)->assertNotFound();
        $this->assertNull($task->fresh()->start_date);
    }

    public function test_the_row_menu_offers_find_a_slot_except_for_fixed_tasks(): void
    {
        $fixed = $this->task(['title' => 'Fija', 'schedule_locked' => true, 'end_date' => today()->setTime(17, 0), 'end_is_date' => false]);
        $flexible = $this->task(['title' => 'Flexible', 'end_date' => today()->setTime(17, 0), 'end_is_date' => false]);

        $html = Livewire::test(TaskList::class)->html();

        $this->assertStringContainsString("task-find-slot', { id: '{$flexible->id}' }", $html);
        $this->assertStringNotContainsString("task-find-slot', { id: '{$fixed->id}' }", $html);
        $this->get(route('tasks.list'))->assertOk()->assertSee('wire:name="task.find-slot"', false);
        $this->get(route('tasks.calendar'))->assertOk()->assertSee('Buscar un hueco');
        $this->get(route('tasks.planning'))->assertOk()->assertSee('wire:name="task.find-slot"', false);
    }
}
