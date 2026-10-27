<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskCalendar;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCalendarRecurrenceSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_completion_suggests_tomorrow_and_keeps_schedule_and_history(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(15, 0));
        $this->actingAs(User::factory()->create());
        $task = Task::create([
            'title' => 'Reunión diaria', 'size' => 'S',
            'start_date' => '2026-10-27 09:00', 'end_date' => '2026-10-27 10:00',
            'start_is_date' => false, 'end_is_date' => false, 'is_recurrent' => true,
            'recurrence' => ['pattern' => 'custom', 'customDays' => 1, 'rrule' => 'FREQ=DAILY;INTERVAL=1',
                'anchor' => '2026-10-25T09:00:00', 'occurrences_completed' => 1],
        ]);
        $before = $task->fresh()->getAttributes();

        $component = Livewire::test(TaskCalendar::class)
            ->call('toggleComplete', $task->id)
            ->assertSet('showRecurringCompletion', true)
            ->assertSet('nextOccurrenceDate', '2026-10-28')
            ->call('cancelRecurringCompletion')
            ->assertSet('showRecurringCompletion', false);
        $this->assertSame($before, $task->fresh()->getAttributes());
        $this->assertDatabaseCount('tasks', 1);

        $component->call('toggleComplete', $task->id)
            ->call('confirmRecurringCompletion')->assertHasNoErrors()
            ->assertSet('noticeDate', '2026-10-28')
            ->assertDispatched('task-calendar-refresh')
            ->assertDispatched('task-completed', xp: 2);

        $task->refresh();
        $this->assertFalse($task->completed);
        $this->assertSame('2026-10-28 09:00', $task->start_date->format('Y-m-d H:i'));
        $this->assertSame('2026-10-28 10:00', $task->end_date->format('Y-m-d H:i'));
        $history = Task::where('is_recurrence_history', true)->sole();
        $this->assertTrue($history->completed);
        $this->assertSame(2, $history->completion_xp);
        $this->assertSame('2026-10-27 09:00', $history->start_date->format('Y-m-d H:i'));
        $this->assertSame($task->recurrence_series_id, $history->recurrence_series_id);
    }
}
