<?php

namespace Tests\Feature;

use App\Livewire\Relationship\RelationshipShow;
use App\Livewire\Relationship\RelationshipTasks;
use App\Models\Circle;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Models\Task;
use App\Models\TaskAssociation;
use App\Models\User;
use App\Support\DefaultTaskCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class RelationshipRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Relationship $relationship;

    protected function setUp(): void
    {
        parent::setUp();
        User::created(function (User $user) {
            DefaultTaskCategories::createFor($user);
            $user->taskCategories()->create(['key' => 'trabajo', 'name' => 'Trabajo']);
        });

        Carbon::setTestNow('2026-08-04 09:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->relationship = Relationship::factory()->create([
            'user_id' => $this->user->id,
            'full_name' => 'Alison Restrepo',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_reminder_creates_one_task_visible_in_both_surfaces(): void
    {
        Livewire::test(RelationshipShow::class, ['relationship' => $this->relationship->id])
            ->call('openTaskForm')
            ->set('taskTitle', 'Recordarle pedir cita médica')
            ->set('taskDueDate', '2026-08-20')
            ->call('saveTask')
            ->assertHasNoErrors()
            ->assertSee('Recordarle pedir cita médica');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('task_associations', 1);

        $task = Task::firstWhere('title', 'Recordarle pedir cita médica');
        $this->assertTrue($this->relationship->tasks()->whereKey($task->id)->exists());

        // It is an ordinary task: the general Tasks module owns it with no parallel store.
        $this->assertSame([$task->id], Task::pluck('id')->all());
        $this->assertSame($this->user->id, $task->user_id);
        $this->assertSame('2026-08-20', $task->end_date->toDateString());
    }

    public function test_creating_an_event_never_creates_an_implicit_task(): void
    {
        Livewire::test(RelationshipShow::class, ['relationship' => $this->relationship->id])
            ->call('openEventForm')
            ->set('eventTitle', 'Graduación')
            ->set('eventDate', '2026-11-14')
            ->call('saveEvent')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('relationship_events', 1);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('task_associations', 0);
    }

    public function test_completing_a_task_elsewhere_is_reflected_without_copies(): void
    {
        $task = Task::create(['user_id' => $this->user->id, 'title' => 'Llamar a Alison']);
        TaskAssociation::link($task, $this->relationship);

        Livewire::test(RelationshipShow::class, ['relationship' => $this->relationship->id])
            ->assertSee('Llamar a Alison');

        $task->update(['completed' => true, 'completed_at' => now()]);

        // A completed task leaves «Pendientes y próximos» and stays under Tareas relacionadas.
        Livewire::test(RelationshipShow::class, ['relationship' => $this->relationship->id])
            ->assertDontSee('Llamar a Alison');

        Livewire::test(RelationshipTasks::class, ['relationship' => $this->relationship->id])
            ->assertDontSee('Llamar a Alison')
            ->call('setStatus', 'completed')
            ->assertSee('Llamar a Alison')
            ->assertSee('Completada');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('task_associations', 1);
    }

    public function test_deleting_a_relationship_keeps_the_task_and_drops_only_the_association(): void
    {
        $task = Task::create(['user_id' => $this->user->id, 'title' => 'Comprar regalo']);
        TaskAssociation::link($task, $this->relationship);

        $this->relationship->delete();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Comprar regalo']);
        $this->assertDatabaseCount('task_associations', 0);
    }

    public function test_an_overdue_follow_up_is_flagged_in_the_quick_stats_without_creating_a_task(): void
    {
        $circle = Circle::factory()->create(['user_id' => $this->user->id, 'contact_frequency_days' => 15]);
        $this->relationship->update([
            'circle_id' => $circle->id,
            'last_contact_at' => Carbon::parse('2026-06-01'),
        ]);

        $component = Livewire::test(RelationshipShow::class, ['relationship' => $this->relationship->id])
            ->assertSee('Hace 64 días')
            ->assertSee('Seguimiento vencido')
            ->assertSee('md-relationship-quick-stat--warning', false);

        $this->assertDatabaseCount('tasks', 0);

        $component->call('openTaskForm', 'Contactar a Alison Restrepo')
            ->assertSet('showTaskForm', true)
            ->assertSet('taskTitle', 'Contactar a Alison Restrepo');

        // Opening the form must not create a task on its own.
        $this->assertDatabaseCount('tasks', 0);

        $component->call('saveTask')->assertHasNoErrors();
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_marking_contact_clears_the_overdue_state(): void
    {
        $this->relationship->update([
            'contact_frequency_days' => 15,
            'last_contact_at' => Carbon::parse('2026-06-01'),
        ]);

        $this->assertTrue($this->relationship->fresh()->isFollowUpDue());

        Livewire::test(RelationshipShow::class, ['relationship' => $this->relationship->id])
            ->call('markContact')
            ->assertDontSee('Seguimiento vencido')
            ->assertSee('Hoy');

        $fresh = $this->relationship->fresh();
        $this->assertFalse($fresh->isFollowUpDue());
        $this->assertSame('2026-08-04', $fresh->last_contact_at->toDateString());
    }

    public function test_an_association_between_different_owners_is_rejected(): void
    {
        $other = User::factory()->create();
        $foreignRelationship = Relationship::factory()->create(['user_id' => $other->id]);
        $task = Task::create(['user_id' => $this->user->id, 'title' => 'Tarea propia']);

        $this->expectException(InvalidArgumentException::class);
        TaskAssociation::link($task, $foreignRelationship);
    }

    public function test_an_events_relationship_and_its_tasks_stay_separate_concepts(): void
    {
        RelationshipEvent::factory()->forRelationship($this->relationship)->create(['title' => 'Graduación']);
        $task = Task::create(['user_id' => $this->user->id, 'title' => 'Comprar regalo de graduación']);
        TaskAssociation::link($task, $this->relationship);

        Livewire::test(RelationshipShow::class, ['relationship' => $this->relationship->id])
            ->assertSeeInOrder(['Pendientes y próximos', 'Graduación', 'Acontecimiento', 'Comprar regalo de graduación', 'Tarea', 'Historial reciente']);
    }
}
