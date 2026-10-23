<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskCalendar;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCalendarStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_exposes_status_and_changes_open_states_and_completion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Calendar status', 'start_date' => now()]);
        $statuses = TaskStatus::ensureFor($user->id);
        $wip = $statuses->firstWhere('type', TaskStatus::IN_PROGRESS);
        $done = $statuses->firstWhere('type', TaskStatus::DONE);
        $component = Livewire::test(TaskCalendar::class);
        $events = $component->instance()->events(now()->startOfDay()->toIso8601String(), now()->addDay()->toIso8601String());
        $this->assertSame($task->status_id, $events[0]['extendedProps']['status']['id']);
        $component->call('changeStatus', $task->id, $wip->id)->assertDispatched('task-calendar-refresh');
        $this->assertSame($wip->id, $task->fresh()->status_id);
        $this->assertFalse($task->fresh()->completed);
        $component->call('changeStatus', $task->id, $done->id)->assertDispatched('task-completed');
        $this->assertTrue($task->fresh()->completed);
        $this->assertNotNull($task->fresh()->completion_xp);
        $completedAt = $task->fresh()->completed_at;
        $component->call('changeStatus', $task->id, $done->id);
        $this->assertEquals($completedAt, $task->fresh()->completed_at);
        $component->call('changeStatus', $task->id, $wip->id);
        $this->assertSame($wip->id, $task->fresh()->status_id);
        $this->assertFalse($task->fresh()->completed);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertNull($task->fresh()->completion_xp);
    }

    public function test_custom_status_is_available_and_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Custom status']);
        $status = TaskStatus::create(['name' => 'Waiting for review', 'type' => TaskStatus::IN_PROGRESS, 'sort_order' => 99]);
        Livewire::test(TaskCalendar::class)->assertSee('Waiting for review')
            ->call('changeStatus', $task->id, $status->id);
        $this->assertSame($status->id, $task->fresh()->status_id);
        $this->assertFalse($task->fresh()->completed);
    }

    public function test_done_on_recurring_task_requires_confirmation_and_cancel_preserves_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Recurring', 'is_recurrent' => true, 'recurrence' => ['pattern' => 'daily', 'frequency' => 1], 'start_date' => now()]);
        $done = TaskStatus::firstOfType($user->id, TaskStatus::DONE);
        $component = Livewire::test(TaskCalendar::class)
            ->call('changeStatus', $task->id, $done->id)
            ->assertSet('showRecurringCompletion', true);
        $this->assertFalse($task->fresh()->completed);
        $component->call('cancelRecurringCompletion')->assertSet('showRecurringCompletion', false);
        $this->assertSame($task->status_id, $task->fresh()->status_id);
        $component->call('changeStatus', $task->id, $done->id)
            ->call('confirmRecurringCompletion')->assertDispatched('task-completed')
            ->assertDispatched('task-calendar-refresh');
    }

    public function test_other_users_tasks_and_statuses_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignTask = $other->tasks()->create(['title' => 'Private']);
        $foreignStatus = TaskStatus::firstOfType($other->id, TaskStatus::IN_PROGRESS);
        $this->actingAs($user);
        $task = Task::create(['title' => 'Mine']);
        $status = TaskStatus::firstOfType($user->id, TaskStatus::IN_PROGRESS);
        Livewire::test(TaskCalendar::class)->call('changeStatus', $foreignTask->id, $status->id)->assertNotFound();
        Livewire::test(TaskCalendar::class)->call('changeStatus', $task->id, $foreignStatus->id)->assertNotFound();
        $this->assertSame($task->status_id, $task->fresh()->status_id);
        $this->assertSame($foreignTask->status_id, $foreignTask->fresh()->status_id);
    }
}
