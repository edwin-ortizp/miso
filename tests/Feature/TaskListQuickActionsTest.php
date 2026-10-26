<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskList;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskListQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
    }

    public function test_completion_skips_open_states_and_cannot_reopen_a_completed_task(): void
    {
        foreach (TaskStatus::ensureFor(auth()->id())->where('type', '!=', TaskStatus::DONE) as $status) {
            $task = Task::create(['title' => 'Completar '.$status->name, 'status_id' => $status->id, 'size' => 'M']);
            $component = Livewire::test(TaskList::class)
                ->assertSee('Mover para hoy')->assertSee('Mover para mañana')
                ->assertSee('md-menu__item--success', false)
                ->call('completeTask', $task->id)->assertDispatched('task-completed', xp: 4)
                ->assertViewHas('tasks', fn ($tasks) => ! $tasks->contains('id', $task->id));
            $completedAt = $task->fresh()->completed_at;
            $component->call('completeTask', $task->id);
            $this->assertTrue($task->fresh()->completed);
            $this->assertEquals($completedAt, $task->fresh()->completed_at);
            $this->assertSame(TaskStatus::DONE, $task->fresh()->status->type);
        }
        Livewire::test(TaskList::class)->set('filter', 'completed')
            ->assertDontSee('Mover para hoy')->assertDontSee('Mover para mañana')
            ->assertSee('Editar')->assertSee('Eliminar')->assertSee('Reabrir');
    }

    public function test_recurrent_completion_preserves_the_existing_prompt_and_history(): void
    {
        $task = Task::create(['title' => 'Recurrente', 'start_date' => today(), 'is_recurrent' => true,
            'recurrence' => ['pattern' => 'custom', 'frequency' => 1, 'customDays' => 7]]);
        Livewire::test(TaskList::class)->call('completeTask', $task->id)
            ->assertSet('showRecurringCompletion', true)->assertSet('nextOccurrenceDate', '2026-11-02')
            ->call('confirmRecurringCompletion')->assertSet('showRecurringCompletion', false)
            ->assertDispatched('task-completed');
        $this->assertFalse($task->fresh()->completed);
        $this->assertSame('2026-11-02', $task->fresh()->start_date->toDateString());
        $this->assertSame(1, Task::where('completed', true)->where('is_recurrence_history', true)->count());
    }

    public function test_moves_preserve_hours_duration_optional_endpoints_and_recurrence(): void
    {
        $cases = [
            [null, null, 0, null, '2026-10-26 00:00:00'],
            ['2026-10-20 08:30:00', null, 0, '2026-10-26 08:30:00', null],
            [null, '2026-11-12 18:00:00', 1, null, '2026-10-27 18:00:00'],
            ['2026-10-24 09:00:00', '2026-10-26 10:30:00', 1, '2026-10-25 09:00:00', '2026-10-27 10:30:00'],
            ['2026-10-20 00:00:00', '2026-10-22 00:00:00', 0, '2026-10-24 00:00:00', '2026-10-26 00:00:00'],
        ];
        foreach ($cases as [$start, $end, $offset, $expectedStart, $expectedEnd]) {
            $task = Task::create(['title' => 'Reprogramar', 'start_date' => $start, 'end_date' => $end,
                'is_recurrent' => true, 'recurrence' => ['pattern' => 'daily']]);
            Livewire::test(TaskList::class)->call('moveToDay', $task->id, $offset)->assertHasNoErrors();
            $task->refresh();
            $this->assertSame($expectedStart, $task->start_date?->format('Y-m-d H:i:s'));
            $this->assertSame($expectedEnd, $task->end_date?->format('Y-m-d H:i:s'));
            $this->assertSame(['pattern' => 'daily'], $task->recurrence);
            $this->assertFalse($task->completed);
        }
    }

    public function test_actions_reject_foreign_completed_tasks_and_invalid_offsets(): void
    {
        $other = User::factory()->create();
        $foreign = $other->tasks()->create(['title' => 'Ajena', 'end_date' => today()->subDay()]);
        $done = Task::create(['title' => 'Terminada', 'completed' => true, 'end_date' => today()->subDay()]);
        foreach ([$foreign, $done] as $task) {
            $before = $task->fresh()->getAttributes();
            Livewire::test(TaskList::class)->call('completeTask', $task->id)->call('moveToDay', $task->id, 0);
            $this->assertSame($before, $task->fresh()->getAttributes());
        }
        $task = Task::create(['title' => 'Sin fecha']);
        Livewire::test(TaskList::class)->call('moveToDay', $task->id, 2);
        $this->assertNull($task->fresh()->end_date);
    }

    public function test_moving_refreshes_overdue_filter_and_counters(): void
    {
        $task = Task::create(['title' => 'Vencida', 'end_date' => today()->subDay()]);
        Livewire::test(TaskList::class)->set('dateFilter', ['vencidas'])
            ->assertViewHas('overdueCount', 1)->call('moveToDay', $task->id, 0)
            ->assertViewHas('overdueCount', 0)->assertViewHas('plannedToday', 1)
            ->assertViewHas('tasks', fn ($tasks) => $tasks->isEmpty());
    }
}
