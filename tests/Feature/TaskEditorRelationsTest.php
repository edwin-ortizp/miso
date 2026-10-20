<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskEditor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskEditorRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_task_links_complete_rows_and_ignores_incomplete_ones(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = $user->goals()->create(['title' => 'Correr 10K']);
        $person = $this->person($user, 'Laura Gómez');

        Livewire::test(TaskEditor::class)
            ->call('openForm')
            ->set('title', 'Coordinar entrenamiento')
            ->set('relations', [
                ['type' => 'goal', 'id' => $goal->id],
                ['type' => 'relationship', 'id' => $person->id],
                ['type' => 'goal', 'id' => ''],
                ['type' => '', 'id' => ''],
            ])
            ->call('save');

        $task = Task::firstWhere('title', 'Coordinar entrenamiento');
        $this->assertTrue($task->goals()->whereKey($goal->id)->exists());
        $this->assertTrue($task->relationships()->whereKey($person->id)->exists());
        $this->assertSame(2, $task->associations()->count());
    }

    public function test_editing_loads_relations_and_removes_the_dropped_ones(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = $user->goals()->create(['title' => 'Correr 10K']);
        $person = $this->person($user, 'Laura Gómez');
        $task = $user->tasks()->create(['title' => 'Planificar']);
        $task->goals()->attach($goal->id, ['id' => (string) str()->uuid(), 'user_id' => $user->id]);
        $task->relationships()->attach($person->id, ['id' => (string) str()->uuid(), 'user_id' => $user->id]);

        Livewire::test(TaskEditor::class)
            ->call('openForm', $task->id)
            ->assertCount('relations', 2)
            ->call('removeRelation', 0)
            ->call('removeRelation', 0)
            ->assertSet('relations', [['type' => '', 'id' => '']])
            ->call('save');

        $this->assertSame(0, $task->associations()->count());
    }

    public function test_changing_module_clears_the_selection_and_add_keeps_a_blank_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = $user->goals()->create(['title' => 'Correr 10K']);

        Livewire::test(TaskEditor::class)
            ->call('openForm')
            ->assertCount('relations', 1)
            ->call('addRelation')
            ->assertCount('relations', 2)
            ->set('relations.0.type', 'goal')
            ->set('relations.0.id', $goal->id)
            ->set('relations.0.type', 'relationship')
            ->assertSet('relations.0.id', '')
            ->assertSee('Relacionado');
    }

    public function test_targets_from_another_user_are_ignored(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreignGoal = $other->goals()->create(['title' => 'Meta ajena']);
        $this->actingAs($owner);

        Livewire::test(TaskEditor::class)
            ->call('openForm')
            ->set('title', 'Mía')
            ->set('relations', [['type' => 'goal', 'id' => $foreignGoal->id]])
            ->call('save');

        $this->assertSame(0, Task::firstWhere('title', 'Mía')->associations()->count());
    }

    private function person(User $user, string $name)
    {
        $circle = $user->circles()->create(['name' => 'Amistades']);

        return $user->relationships()->create(['circle_id' => $circle->id, 'full_name' => $name, 'category' => 'amistad']);
    }
}
