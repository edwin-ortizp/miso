<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskBulkEditor;
use App\Livewire\Task\TaskEditor;
use App\Livewire\Task\TaskFlow;
use App\Models\Task;
use App\Models\User;
use App\Support\DefaultTaskCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskBulkEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::created(function (User $user) {
            DefaultTaskCategories::createFor($user);
            $user->taskCategories()->create(['key' => 'trabajo', 'name' => 'Trabajo']);
        });
    }

    public function test_bulk_editor_saves_shared_fields_and_per_line_metadata(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other);
        $untouched = Task::create(['title' => 'Ajena']);
        $this->actingAs($user);

        Livewire::test(TaskBulkEditor::class)
            ->call('openForm')
            ->set('bulkTitles', "Primera 🔺 📅 2026-12-02\n\n Segunda \n")
            ->set('description', 'Descripción común')
            ->set('category', 'personal')
            ->set('priority', 'not-urgent-important')
            ->set('size', 'M')
            ->set('isPrivate', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('task-records-changed');

        $tasks = Task::withoutGlobalScopes()->where('user_id', $user->id)->get();
        $this->assertCount(2, $tasks);
        $first = $tasks->firstWhere('title', 'Primera');
        $this->assertSame('urgent-important', $first->priority);
        $this->assertSame('2026-12-02', $first->end_date->format('Y-m-d'));
        $this->assertSame('not-urgent-important', $tasks->firstWhere('title', 'Segunda')->priority);
        foreach ($tasks as $task) {
            $this->assertSame($user->id, $task->user_id);
            $this->assertSame('Descripción común', $task->description);
            $this->assertSame('personal', $task->category);
            $this->assertSame('M', $task->size);
            $this->assertTrue($task->is_private);
        }
        $this->assertDatabaseHas('tasks', ['id' => $untouched->id, 'title' => 'Ajena', 'user_id' => $other->id]);
    }

    public function test_empty_bulk_draft_keeps_dialog_open_and_cancel_discards_it(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(TaskBulkEditor::class)
            ->call('openForm')
            ->set('bulkTitles', " \n\n")
            ->call('save')
            ->assertHasErrors('bulkTitles')
            ->assertSet('showForm', true)
            ->call('closeForm')
            ->call('openForm')
            ->assertSet('bulkTitles', '')
            ->assertSet('category', '')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_shared_individual_dialog_uses_canonical_sections_without_bulk_controls(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Individual']);
        foreach ([TaskEditor::class, TaskFlow::class] as $component) {
            Livewire::test($component)->call('openForm', $task->id)
                ->assertSee('md-form-dialog__nav', false)
                ->assertSee('Información básica')
                ->assertSee('Privacidad')
                ->assertSee('Programación')
                ->assertDontSee('Varias a la vez')
                ->assertDontSee('Obligatorio')
                ->assertDontSee('Opcional')
                ->assertDontSee('wire:model="bulkTitles"', false);
        }
    }
}
