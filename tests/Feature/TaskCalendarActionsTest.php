<?php
namespace Tests\Feature;
use App\Livewire\Task\TaskCalendar;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
class TaskCalendarActionsTest extends TestCase
{
    use RefreshDatabase;
    public function test_move_preserves_hours_duration_and_completion_is_idempotent(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Move', 'start_date' => today()->subDays(3)->setTime(9, 0), 'end_date' => today()->subDays(3)->setTime(10, 30), 'start_is_date' => false, 'end_is_date' => false]);
        $component = Livewire::test(TaskCalendar::class);
        $component->call('calendarAction', $task->id, 'tomorrow')->assertDispatched('task-calendar-refresh');
        $this->assertSame(today()->addDay()->format('Y-m-d').' 09:00', $task->fresh()->start_date->format('Y-m-d H:i'));
        $this->assertSame('10:30', $task->fresh()->end_date->format('H:i'));
        $component->call('calendarAction', $task->id, 'complete');
        $completedAt = $task->fresh()->completed_at;
        $component->call('calendarAction', $task->id, 'complete');
        $this->assertTrue($task->fresh()->completed);
        $this->assertEquals($completedAt, $task->fresh()->completed_at);
        $component->call('calendarAction', $task->id, 'delete');
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
    public function test_actions_reject_another_users_task(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Private']);
        $this->actingAs(User::factory()->create());
        foreach (['advance', 'complete', 'today', 'tomorrow', 'delete'] as $action) {
            Livewire::test(TaskCalendar::class)->call('calendarAction', $task->id, $action)->assertNotFound();
        }
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed' => false]);
    }
}
