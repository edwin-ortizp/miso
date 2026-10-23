<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskKanban;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskKanbanGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_status_columns_include_empty_columns_in_configured_order(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other);
        Task::create(['title' => 'Private other task']);
        TaskStatus::create(['name' => 'Private other status', 'type' => 'ready']);
        $this->actingAs(User::factory()->create());
        $custom = TaskStatus::create(['name' => 'Revisión', 'type' => 'ready', 'sort_order' => 9]);
        $task = Task::create(['title' => 'Revisar informe', 'status_id' => $custom->id]);
        $ids = TaskStatus::query()->ordered()->pluck('id')->all();

        Livewire::test(TaskKanban::class)->assertSet('groupBy', 'state')
            ->assertDontSee('Private other task')->assertDontSee('Private other status')
            ->assertViewHas('columns', fn ($columns) => array_keys($columns) === $ids
                && $columns[$custom->id]['tasks']->pluck('id')->all() === [$task->id]
                && collect($columns)->contains(fn ($column) => $column['tasks']->isEmpty()));
    }

    public function test_group_url_and_invalid_values(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::withQueryParams(['group' => 'priority'])->test(TaskKanban::class)
            ->assertSet('groupBy', 'priority')->assertSee('Urgente + Importante')
            ->set('groupBy', 'invalid')->assertSet('groupBy', 'state');
        Livewire::withQueryParams(['group' => 'invalid'])->test(TaskKanban::class)->assertSet('groupBy', 'state');
    }

    public function test_switching_groups_preserves_filters_and_completion_moves_the_card(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Informe', 'priority' => 'urgent-important']);
        Task::create(['title' => 'Otra tarea']);
        $original = $task->status_id;
        $done = TaskStatus::query()->where('type', TaskStatus::DONE)->firstOrFail();
        Livewire::test(TaskKanban::class)->set('search', 'Informe')
            ->set('priorityFilter', 'urgent-important')->set('groupBy', 'priority')
            ->assertSet('search', 'Informe')->assertSet('priorityFilter', 'urgent-important')
            ->assertViewHas('columns', fn ($columns) => $columns['urgent-important']['tasks']->count() === 1)
            ->set('groupBy', 'state')->call('toggleComplete', $task->id)
            ->assertViewHas('columns', fn ($columns) => $columns[$done->id]['tasks']->pluck('id')->all() === [$task->id]
                && $columns[$original]['tasks']->isEmpty())
            ->set('groupBy', 'priority')->call('clearFilters')->assertSet('groupBy', 'priority');
    }

    public function test_adjacent_actions_follow_custom_order_and_reopen_completed_tasks(): void
    {
        $this->actingAs(User::factory()->create());
        $statuses = TaskStatus::ensureFor(auth()->id());
        $custom = TaskStatus::create(['name' => 'Verificación', 'type' => TaskStatus::READY, 'sort_order' => 50]);
        $task = Task::create(['title' => 'Mover informe', 'status_id' => $statuses[0]->id]);
        $ordered = TaskStatus::query()->ordered()->get();
        $component = Livewire::test(TaskKanban::class);
        $component->call('moveStatus', $task->id, 'previous');
        $this->assertSame($ordered[0]->id, $task->fresh()->status_id);
        foreach ($ordered->skip(1) as $status) {
            $component->call('moveStatus', $task->id, 'next');
            $this->assertSame($status->id, $task->fresh()->status_id);
        }
        $this->assertTrue($task->fresh()->completed);
        $this->assertNotNull($task->fresh()->completion_xp);
        $component->call('moveStatus', $task->id, 'next');
        $this->assertSame($ordered->last()->id, $task->fresh()->status_id);
        $component->set('groupBy', 'priority')->call('moveStatus', $task->id, 'previous');
        $this->assertSame($ordered[$ordered->count() - 2]->id, $task->fresh()->status_id);
        $this->assertFalse($task->fresh()->completed);
        $this->assertNull($task->fresh()->completion_xp);
    }

    public function test_status_actions_respect_filters_categories_and_user_isolation(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other);
        $foreign = Task::create(['title' => 'Privada']);
        $this->actingAs(User::factory()->create());
        $category = \App\Models\TaskCategory::create(['key' => 'revision-interna', 'name' => 'Revisión interna']);
        $task = Task::create(['title' => 'Informe', 'category' => $category->key]);
        Livewire::test(TaskKanban::class)->assertSee('Revisión interna')
            ->set('filter', [$task->status_id])->call('moveStatus', $task->id, 'next')
            ->assertViewHas('columns', fn ($columns) => collect($columns)->sum(fn ($column) => $column['tasks']->count()) === 0);
        Livewire::test(TaskKanban::class)->call('moveStatus', $foreign->id, 'next')->assertNotFound();
        Livewire::test(TaskKanban::class)->call('moveStatus', $task->id, 'invalid')->assertStatus(422);
    }

    public function test_recurring_completion_can_be_cancelled_or_confirmed_from_next(): void
    {
        $this->actingAs(User::factory()->create());
        $wip = TaskStatus::ensureFor(auth()->id())->firstWhere('type', TaskStatus::IN_PROGRESS);
        $task = Task::create(['title' => 'Recurrente', 'status_id' => $wip->id, 'is_recurrent' => true,
            'start_date' => today(), 'recurrence' => ['pattern' => 'custom', 'frequency' => 1, 'customDays' => 7]]);
        $component = Livewire::test(TaskKanban::class)->call('moveStatus', $task->id, 'next')
            ->assertSet('showRecurringCompletion', true)->call('cancelRecurringCompletion');
        $this->assertSame($wip->id, $task->fresh()->status_id);
        $this->assertFalse($task->fresh()->completed);
        $component->call('moveStatus', $task->id, 'next')->call('confirmRecurringCompletion')
            ->assertSet('showRecurringCompletion', false)->assertDispatched('task-completed');
        $this->assertDatabaseHas('tasks', ['title' => 'Recurrente', 'completed' => true, 'is_recurrence_history' => true]);
    }
}
