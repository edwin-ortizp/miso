<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskEditor;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskEditorDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_status_and_title_keeps_editor_open_and_awards_xp_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Antes', 'size' => 'M']);
        $done = TaskStatus::ensureFor($user->id)->firstWhere('type', TaskStatus::DONE);
        $editor = Livewire::test(TaskEditor::class)->call('openForm', $task->id)
            ->set('title', 'Después')->set('statusId', $done->id);
        $this->assertSame('Antes', $task->fresh()->title);
        $editor->call('save')->assertHasNoErrors()->assertSet('showForm', true);
        $this->assertTrue($task->fresh()->completed);
        $this->assertSame(4, $task->fresh()->completion_xp);
        $editor->call('save')->assertHasNoErrors();
        $this->assertSame(4, $task->fresh()->completion_xp);
    }

    public function test_canceling_recurrence_does_not_persist_draft(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Original', 'is_recurrent' => true, 'recurrence' => ['pattern' => 'daily', 'frequency' => 1]]);
        $done = TaskStatus::ensureFor($user->id)->firstWhere('type', TaskStatus::DONE);
        Livewire::test(TaskEditor::class)->call('openForm', $task->id)
            ->set('title', 'Pendiente')->set('statusId', $done->id)->call('save')
            ->assertSet('showRecurringCompletion', true)->call('cancelRecurringCompletion');
        $this->assertSame('Original', $task->fresh()->title);
        $this->assertFalse($task->fresh()->completed);
        $this->assertSame(1, $user->tasks()->count());
    }
    public function test_confirming_recurrence_persists_draft_and_creates_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Original', 'is_recurrent' => true, 'recurrence' => ['pattern' => 'daily', 'frequency' => 1], 'start_date' => '2026-11-04 10:00:00', 'start_is_date' => false]);
        $done = TaskStatus::ensureFor($user->id)->firstWhere('type', TaskStatus::DONE);
        Livewire::test(TaskEditor::class)->call('openForm', $task->id)
            ->set('title', 'Cambiada')->set('statusId', $done->id)->call('save')
            ->set('nextOccurrenceDate', '2026-11-06')->call('confirmRecurringCompletion')
            ->assertHasNoErrors()->assertSet('showForm', true);
        $this->assertSame('Cambiada', $task->fresh()->title);
        $this->assertSame('2026-11-06 10:00', $task->fresh()->start_date->format('Y-m-d H:i'));
        $this->assertFalse($task->fresh()->completed);
        $this->assertSame(1, $user->tasks()->where('is_recurrence_history', true)->count());
    }
    public function test_stale_recurrence_confirmation_does_not_write_draft(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Original', 'is_recurrent' => true, 'recurrence' => ['pattern' => 'daily', 'frequency' => 1]]);
        $done = TaskStatus::ensureFor($user->id)->firstWhere('type', TaskStatus::DONE);
        $editor = Livewire::test(TaskEditor::class)->call('openForm', $task->id)
            ->set('title', 'No guardar')->set('statusId', $done->id)->call('save');
        $task->update(['recurrence' => ['pattern' => 'weekly', 'frequency' => 1]]);
        $editor->call('confirmRecurringCompletion')->assertHasErrors(['statusId']);
        $this->assertSame('Original', $task->fresh()->title);
        $this->assertSame(1, $user->tasks()->count());
    }

    public function test_another_users_task_cannot_be_opened(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task = $other->tasks()->create(['title' => 'Ajena']);
        $this->actingAs($owner);
        Livewire::test(TaskEditor::class)->call('openForm', $task->id)->assertNotFound();
    }
    public function test_reopening_a_task_uses_selected_open_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Hecha', 'completed' => true, 'completion_xp' => 4, 'progress' => 100]);
        $open = TaskStatus::ensureFor($user->id)->firstWhere('type', TaskStatus::READY);
        Livewire::test(TaskEditor::class)->call('openForm', $task->id)->set('statusId', $open->id)->call('save')->assertHasNoErrors();
        $this->assertFalse($task->fresh()->completed);
        $this->assertSame($open->id, $task->fresh()->status_id);
        $this->assertNull($task->fresh()->completion_xp);
    }

    public function test_new_recurring_task_is_not_created_until_confirmed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $done = TaskStatus::ensureFor($user->id)->firstWhere('type', TaskStatus::DONE);
        $editor = Livewire::test(TaskEditor::class)->call('openForm')->set('title', 'Nueva')
            ->set('isRecurrent', true)->set('statusId', $done->id)->call('save')
            ->assertSet('showRecurringCompletion', true);
        $this->assertSame(0, $user->tasks()->count());
        $editor->set('nextOccurrenceDate', '2026-11-10')->call('confirmRecurringCompletion')->assertHasNoErrors();
        $this->assertSame(2, $user->tasks()->count());
    }
    public function test_scheduling_intent_requires_a_date_before_saving(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $user->tasks()->create(['title' => 'Sin fecha']);
        $editor = Livewire::test(TaskEditor::class)->call('openScheduleForm', $task->id)
            ->set('title', 'Pendiente')->call('save')->assertHasErrors(['endDate']);
        $this->assertSame('Sin fecha', $task->fresh()->title);
        $editor->set('endDate', '2026-11-10')->call('save')->assertHasNoErrors();
        $this->assertSame('2026-11-10', $task->fresh()->end_date->format('Y-m-d'));
    }
}
