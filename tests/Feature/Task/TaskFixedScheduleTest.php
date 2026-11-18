<?php

namespace Tests\Feature\Task;

use App\Actions\Task\ShiftTasksToDate;
use App\Livewire\Home\Panel\QuickEditor;
use App\Livewire\Task\TaskCalendar;
use App\Livewire\Task\TaskEditor;
use App\Livewire\Task\TaskGantt;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskPlanning;
use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Task\CreateTaskTool;
use App\Mcp\Tools\Task\GetTaskTool;
use App\Mcp\Tools\Task\UpdateTaskTool;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-144: una tarea con horario fijo no se mueve por atajos, arrastre, cierre del día ni MCP. */
class TaskFixedScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function fixed(array $extra = []): Task
    {
        return Task::create(['title' => 'Reunión con cliente', 'start_date' => today()->subDays(2)->setTime(10, 0), 'end_date' => today()->subDays(2)->setTime(11, 0),
            'start_is_date' => false, 'end_is_date' => false, 'schedule_locked' => true, ...$extra]);
    }

    private function stamp(Task $task): string
    {
        $task->refresh();

        return $task->start_date->format('Y-m-d H:i').'|'.$task->end_date->format('Y-m-d H:i');
    }

    public function test_the_flag_is_saved_read_back_and_off_by_default(): void
    {
        $plain = Task::create(['title' => 'Flexible']);
        $fixed = $this->fixed();

        $this->assertFalse($plain->fresh()->schedule_locked);
        $this->assertTrue($fixed->fresh()->schedule_locked);
        $this->assertDatabaseHas('tasks', ['id' => $fixed->id, 'schedule_locked' => true]);
    }

    public function test_quick_move_actions_skip_a_fixed_task_and_say_so(): void
    {
        $task = $this->fixed();
        $before = $this->stamp($task);

        Livewire::test(TaskList::class)->call('moveToDay', $task->id, 1)
            ->assertSet('lockedNotice', 'Esta tarea tiene horario fijo. Quita el candado para moverla.');
        Livewire::test(TaskCalendar::class)->call('calendarAction', $task->id, 'tomorrow')->assertSet('lockedNotice', 'Esta tarea tiene horario fijo. Quita el candado para moverla.');
        Livewire::test(TaskCalendar::class)->call('moveTask', $task->id, today()->addDay()->setTime(9, 0)->toIso8601String(), today()->addDay()->setTime(10, 0)->toIso8601String(), false)
            ->assertDispatched('task-calendar-refresh');
        Livewire::test(TaskPlanning::class)->call('moveToDay', $task->id, 0)->assertSet('lockedNotice', 'Esta tarea tiene horario fijo. Quita el candado para moverla.');
        Livewire::test(TaskPlanning::class)->call('clearDates', $task->id);
        Livewire::test(TaskGantt::class)->call('moveTask', $task->id, today()->addDays(3)->toDateString(), today()->addDays(4)->toDateString());

        $this->assertSame($before, $this->stamp($task));
    }

    public function test_a_flexible_task_still_moves_with_the_same_actions(): void
    {
        $task = $this->fixed(['schedule_locked' => false]);

        Livewire::test(TaskList::class)->call('moveToDay', $task->id, 1)->assertSet('lockedNotice', null);

        $this->assertSame(today()->addDay()->format('Y-m-d').' 10:00', $task->fresh()->start_date->format('Y-m-d H:i'));
    }

    public function test_snooze_from_my_day_and_the_day_close_leave_a_fixed_task_alone(): void
    {
        $task = $this->fixed();
        $flexible = $this->fixed(['title' => 'Flexible', 'schedule_locked' => false]);
        $before = $this->stamp($task);

        Livewire::test(QuickEditor::class)->call('open', 'snooze', $task->id)->set('scheduledDate', '2030-01-01')->call('save')->assertHasErrors('scheduledDate');
        $undo = ShiftTasksToDate::handle([$task->fresh(), $flexible->fresh()], CarbonImmutable::parse(today()->toDateString())->addDay());

        $this->assertSame($before, $this->stamp($task));
        $this->assertArrayNotHasKey($task->id, $undo);
        $this->assertArrayHasKey($flexible->id, $undo);
        $this->assertSame(today()->addDay()->format('Y-m-d').' 10:00', $flexible->fresh()->start_date->format('Y-m-d H:i'));
    }

    public function test_the_editor_asks_to_unlock_before_moving_and_saves_the_flag(): void
    {
        $task = $this->fixed(['start_date' => today()->addDay()->setTime(10, 0), 'end_date' => today()->addDay()->setTime(11, 0)]);

        $component = Livewire::test(TaskEditor::class, ['editTask' => $task->id])
            ->assertSet('scheduleLocked', true)
            ->set('startDate', today()->addDays(2)->toDateString())
            ->set('endDate', today()->addDays(2)->toDateString())
            ->call('save')
            ->assertHasErrors('scheduleLocked');
        $this->assertSame(today()->addDay()->format('Y-m-d').' 10:00', $task->fresh()->start_date->format('Y-m-d H:i'));

        $component->set('scheduleLocked', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($task->fresh()->schedule_locked);
        $this->assertSame(today()->addDays(2)->format('Y-m-d'), $task->fresh()->start_date->format('Y-m-d'));

        Livewire::test(TaskEditor::class, ['editTask' => $task->id])->set('scheduleLocked', true)->call('save')->assertHasNoErrors();
        $this->assertTrue($task->fresh()->schedule_locked);
    }

    public function test_list_and_planning_show_the_icon_only_while_the_schedule_is_locked(): void
    {
        $task = $this->fixed(['start_date' => today()->setTime(10, 0), 'end_date' => today()->setTime(11, 0)]);
        $this->fixed(['schedule_locked' => false]);
        $icon = 'class="bi bi-lock-fill task-locked-icon"';

        foreach ([TaskList::class, TaskPlanning::class] as $view) {
            $html = Livewire::test($view)->html();
            $this->assertSame(1, substr_count($html, $icon));
        }

        Livewire::test(TaskEditor::class)->call('openForm', $task->id)
            ->set('scheduleLocked', false)->call('save')->assertHasNoErrors();

        foreach ([TaskList::class, TaskPlanning::class] as $view) {
            Livewire::test($view)->assertDontSee($icon, false);
        }
    }

    public function test_the_calendar_marks_fixed_events_as_locked_and_not_draggable(): void
    {
        $task = $this->fixed(['start_date' => today()->setTime(10, 0), 'end_date' => today()->setTime(11, 0)]);
        $events = Livewire::test(TaskCalendar::class)->instance()->events(today()->toDateString(), today()->addDay()->toDateString());
        $event = collect($events)->firstWhere('id', $task->id);

        $this->assertTrue($event['extendedProps']['locked']);
        $this->assertFalse($event['editable']);
        $this->assertContains('is-locked', $event['classNames']);
    }

    public function test_mcp_reads_and_writes_the_flag_and_refuses_to_move_a_fixed_task(): void
    {
        LifeTrackerServer::actingAs($this->user)->tool(CreateTaskTool::class, [
            'title' => 'Demo', 'start_date' => '2026-12-01 10:00', 'end_date' => '2026-12-01 11:00', 'schedule_locked' => true,
        ])->assertOk();
        $task = Task::where('title', 'Demo')->firstOrFail();
        $this->assertTrue($task->schedule_locked);

        LifeTrackerServer::actingAs($this->user)->tool(GetTaskTool::class, ['task_id' => $task->id])->assertOk()->assertSee('schedule_locked');
        LifeTrackerServer::actingAs($this->user)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'start_date' => '2026-12-02 10:00'])
            ->assertHasErrors(['horario fijo']);
        $this->assertSame('2026-12-01 10:00', $task->fresh()->start_date->format('Y-m-d H:i'));

        LifeTrackerServer::actingAs($this->user)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'title' => 'Demo renombrada'])->assertOk();
        LifeTrackerServer::actingAs($this->user)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'schedule_locked' => false])->assertOk();
        LifeTrackerServer::actingAs($this->user)->tool(UpdateTaskTool::class, ['task_id' => $task->id, 'start_date' => '2026-12-02 10:00'])->assertOk();
        $this->assertSame('2026-12-02 10:00', $task->fresh()->start_date->format('Y-m-d H:i'));
    }

    public function test_another_users_fixed_tasks_are_not_visible_or_affected(): void
    {
        $this->fixed();
        $other = User::factory()->create();
        $this->actingAs($other);
        $mine = Task::create(['title' => 'Mía', 'start_date' => today()->setTime(9, 0), 'end_date' => today()->setTime(10, 0)]);

        Livewire::test(TaskList::class)->call('moveToDay', $mine->id, 1)->assertSet('lockedNotice', null);

        $this->assertSame(today()->addDay()->format('Y-m-d').' 09:00', $mine->fresh()->start_date->format('Y-m-d H:i'));
    }
}
