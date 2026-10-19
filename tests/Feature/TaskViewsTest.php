<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskFlow;
use App\Livewire\Task\TaskEditor;
use App\Livewire\Task\TaskGantt;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskPlanning;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_tasks_open_description_preview_on_every_open(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Con descripción', 'description' => '**Detalle**']);
        $emptyTask = Task::create(['title' => 'Sin descripción']);

        foreach ([TaskEditor::class, TaskFlow::class, TaskGantt::class, TaskPlanning::class] as $component) {
            Livewire::test($component)
                ->call('openForm', $task->id)
                ->assertSet('descriptionMode', 'preview')
                ->assertSee('<strong>Detalle</strong>', false)
                ->set('descriptionMode', 'write')
                ->set('description', 'Texto actualizado')
                ->set('descriptionMode', 'preview')
                ->assertSet('description', 'Texto actualizado')
                ->assertSee('Texto actualizado')
                ->set('descriptionMode', 'write')
                ->call('closeForm')
                ->call('openForm', $emptyTask->id)
                ->assertSet('descriptionMode', 'preview')
                ->assertSee('Sin contenido para previsualizar.');
        }
    }

    public function test_new_tasks_open_description_in_write_mode_after_an_existing_task(): void
    {
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Tarea existente']);

        foreach ([TaskEditor::class, TaskList::class] as $component) {
            Livewire::test($component)
                ->call('openForm')
                ->assertSet('descriptionMode', 'write')
                ->call('openForm', $task->id)
                ->assertSet('descriptionMode', 'preview')
                ->call('closeForm')
                ->call('openForm')
                ->assertSet('descriptionMode', 'write')
                ->assertSet('description', '');
        }

        Livewire::test(TaskEditor::class, ['editTask' => $task->id])
            ->assertSet('descriptionMode', 'preview');
    }

    public function test_task_views_have_canonical_routes_and_shared_tabs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tasks')->assertRedirect('/tasks/list');

        $this->actingAs($user)->get('/tasks/list')
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('Kanban');

        foreach (['gantt' => 'Gantt', 'flow' => 'Flujo', 'kanban' => 'Kanban', 'planning' => 'Planificación', 'progress' => 'Progreso'] as $view => $tab) {
            $this->actingAs($user)->get("/tasks/{$view}")
                ->assertOk()
                ->assertSee('<h1 class="visually-hidden" data-region="identity">Tareas</h1>', false)
                ->assertSee($tab)
                ->assertSee('aria-current="page"', false);
        }
    }

    public function test_bulk_creation_uses_non_empty_lines_and_shared_attributes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskEditor::class)
            ->call('openForm')
            ->set('bulkTitles', "Preparar informe\n\nLlamar al cliente\n  ")
            ->set('description', 'Seguimiento semanal')
            ->set('category', 'trabajo')
            ->set('priority', 'urgent-important')
            ->set('size', 'M')
            ->set('startDate', '2026-07-12')
            ->set('endDate', '2026-07-15')
            ->set('isPrivate', true)
            ->call('saveBulk')
            ->assertSet('showForm', false);

        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Preparar informe', 'category' => 'trabajo', 'is_private' => true]);
        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Llamar al cliente', 'description' => 'Seguimiento semanal']);
    }

    public function test_individual_task_creation_remains_available(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskList::class)
            ->call('openForm')
            ->set('title', 'Tarea individual')
            ->set('category', 'personal')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Tarea individual', 'category' => 'personal']);
    }

    public function test_task_list_orders_tasks_from_the_oldest_schedule_to_the_latest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Task::create(['title' => 'Sin fecha']);
        Task::create(['title' => 'Fecha futura urgente', 'priority' => 'urgent-important', 'start_date' => '2026-08-20']);
        Task::create(['title' => 'Fecha antigua', 'priority' => 'not-urgent-not-important', 'start_date' => '2026-08-02']);
        Task::create(['title' => 'Solo fecha límite', 'end_date' => '2026-08-10']);

        $titles = Task::query()->chronological()->pluck('title')->all();

        $this->assertSame([
            'Fecha antigua',
            'Solo fecha límite',
            'Fecha futura urgente',
            'Sin fecha',
        ], $titles);
    }

    public function test_individual_task_creation_persists_a_start_without_an_end(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskList::class)
            ->call('openForm')
            ->set('title', 'Inicio sin final')
            ->set('startDate', '2026-07-13')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Inicio sin final',
            'start_date' => '2026-07-13 00:00:00',
            'end_date' => null,
        ]);
    }

    public function test_task_duration_shortcuts_calculate_and_persist_schedule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskList::class)
            ->call('openForm')
            ->set('title', 'Bloque de concentración')
            ->set('startDate', '2026-07-13')
            ->set('startTime', '09:30')
            ->call('applyDuration', 90)
            ->assertSet('endDate', '2026-07-13')
            ->assertSet('endTime', '11:00')
            ->assertSet('estimatedTime', 90)
            ->call('save');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Bloque de concentración',
            'start_date' => '2026-07-13 09:30:00',
            'end_date' => '2026-07-13 11:00:00',
            'estimated_time' => 90,
        ]);
    }

    public function test_duration_shortcut_uses_now_when_start_is_missing_and_manual_times_recalculate_it(): void
    {
        Carbon::setTestNow('2026-07-13 10:22:45');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskList::class)
            ->call('openForm')
            ->call('applyDuration', 30)
            ->assertSet('startDate', '2026-07-13')
            ->assertSet('startTime', '10:22')
            ->assertSet('endDate', '2026-07-13')
            ->assertSet('endTime', '10:52')
            ->set('endTime', '12:07')
            ->assertSet('estimatedTime', 105);

        Carbon::setTestNow();
    }

    public function test_changing_start_shifts_end_by_the_existing_task_duration(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskList::class)
            ->call('openForm')
            ->set('startDate', '2026-07-13')
            ->set('startTime', '09:30')
            ->set('endDate', '2026-07-15')
            ->set('endTime', '11:00')
            ->set('startDate', '2026-07-20')
            ->assertSet('endDate', '2026-07-22')
            ->assertSet('endTime', '11:00')
            ->assertSet('estimatedTime', 2970);
    }

    public function test_task_rejects_an_end_before_its_start(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskList::class)
            ->call('openForm')
            ->set('title', 'Horario inválido')
            ->set('startDate', '2026-07-13')
            ->set('startTime', '12:00')
            ->set('endDate', '2026-07-13')
            ->set('endTime', '11:00')
            ->call('save')
            ->assertHasErrors('endDate');

        $this->assertDatabaseMissing('tasks', ['title' => 'Horario inválido']);
    }

    public function test_bulk_creation_preserves_time_and_estimation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskEditor::class)
            ->call('openForm')
            ->set('bulkTitles', "Primera\nSegunda")
            ->set('startDate', '2026-07-13')
            ->set('startTime', '14:00')
            ->call('applyDuration', 120)
            ->call('saveBulk');

        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', ['title' => 'Primera', 'start_date' => '2026-07-13 14:00:00', 'end_date' => '2026-07-13 16:00:00', 'estimated_time' => 120]);
        $this->assertDatabaseHas('tasks', ['title' => 'Segunda', 'start_date' => '2026-07-13 14:00:00', 'end_date' => '2026-07-13 16:00:00', 'estimated_time' => 120]);
    }

    public function test_flow_prioritizes_pending_tasks_and_keeps_completed_tasks_in_lane_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $first = Task::create(['title' => 'Primera tecnología', 'category' => 'tecnologia']);
        $completed = Task::create(['title' => 'Completada reciente', 'category' => 'tecnologia', 'completed' => true, 'completed_at' => now()->subDay()]);
        Task::create(['title' => 'Completada anterior', 'category' => 'tecnologia', 'completed' => true, 'completed_at' => now()->subDays(2)]);
        $last = Task::create(['title' => 'Última tecnología', 'category' => 'tecnologia']);
        Task::create(['title' => 'Sin categoría', 'category' => null]);

        Livewire::test(TaskFlow::class)
            ->assertSee('Tecnología')
            ->assertSee('Sin categoría')
            ->assertSee('2 pendientes')
            ->assertSee('2 completadas')
            ->assertSeeInOrder(['Primera tecnología', 'Última tecnología', 'Historial del carril', 'Completada reciente', 'Completada anterior'])
            ->call('moveNext', $first->id);

        $first->refresh();
        $completed->refresh();
        $last->refresh();

        $this->assertSame(4, $first->flow_position);
        $this->assertSame(2, $completed->flow_position);
        $this->assertTrue($completed->completed);
        $this->assertSame(1, $last->flow_position);

        Livewire::test(TaskFlow::class)
            ->call('movePrevious', $completed->id);

        $completed->refresh();
        $last->refresh();
        $this->assertSame(2, $completed->flow_position);
        $this->assertSame(1, $last->flow_position);
    }

    public function test_flow_edits_and_completes_tasks_without_leaving_the_view(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Editar desde flujo', 'category' => 'trabajo']);

        Livewire::test(TaskFlow::class)
            ->call('openForm', $task->id)
            ->assertSet('showForm', true)
            ->assertSet('completed', false)
            ->call('toggleComplete', $task->id)
            ->assertSet('completed', true)
            ->set('title', 'Editada sin salir')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Editada sin salir', 'completed' => true]);
    }

    public function test_flow_reopens_a_task_from_its_lane_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create([
            'title' => 'Retomar propuesta',
            'category' => 'tecnologia',
            'completed' => true,
            'completed_at' => now(),
        ]);

        Livewire::test(TaskFlow::class)
            ->assertSee('0 pendientes')
            ->assertSee('1 completadas')
            ->assertSee('Historial del carril')
            ->call('toggleComplete', $task->id)
            ->assertSee('1 pendientes');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed' => false, 'completed_at' => null]);
    }

    public function test_flow_loads_thirty_pending_tasks_per_lane_then_can_load_more(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 31) as $number) {
            Task::create(['title' => "Tecnología {$number}", 'category' => 'tecnologia']);
        }
        Task::create(['title' => 'Tecnología completada', 'category' => 'tecnologia', 'completed' => true, 'completed_at' => now()]);

        Livewire::test(TaskFlow::class)
            ->assertSee('31 pendientes')
            ->assertSee('1 completadas')
            ->assertSee('Tecnología 30')
            ->assertDontSee('Tecnología 31')
            ->assertSee('Tecnología completada')
            ->call('loadMore', 'tecnologia')
            ->assertSee('Tecnología 31');
    }

    public function test_changing_a_tasks_category_places_it_at_the_end_of_its_new_flow_lane(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Task::create(['title' => 'Primera de trabajo', 'category' => 'trabajo']);
        $moved = Task::create(['title' => 'Mover a trabajo', 'category' => 'personal']);
        $last = Task::create(['title' => 'Última de trabajo', 'category' => 'trabajo']);

        Livewire::test(TaskList::class)
            ->call('openForm', $moved->id)
            ->set('category', 'trabajo')
            ->call('save');

        $moved->refresh();
        $this->assertSame($last->flow_position + 1, $moved->flow_position);
    }

    public function test_individual_task_creation_can_define_a_recurrence_interval(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TaskList::class)
            ->call('openForm')
            ->set('title', 'Backup de la base de datos')
            ->set('isRecurrent', true)
            ->set('recurrenceIntervalDays', 7)
            ->call('save');

        $task = Task::where('title', 'Backup de la base de datos')->firstOrFail();
        $this->assertTrue($task->is_recurrent);
        $this->assertSame(['pattern' => 'custom', 'frequency' => 1, 'customDays' => 7], $task->recurrence);
    }

    public function test_editing_a_recurrent_task_loads_its_recurrence_interval(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create([
            'title' => 'Revisión quincenal',
            'is_recurrent' => true,
            'recurrence' => ['pattern' => 'custom', 'frequency' => 1, 'customDays' => 15],
        ]);

        Livewire::test(TaskEditor::class)
            ->call('openForm', $task->id)
            ->assertSet('isRecurrent', true)
            ->assertSet('recurrenceIntervalDays', 15)
            ->assertSee('Repetir cada (días)');
    }

    public function test_gantt_shows_ranges_milestones_and_tasks_without_dates(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        Task::create(['title' => 'Rango de julio', 'start_date' => '2026-07-10', 'end_date' => '2026-07-15']);
        Task::create(['title' => 'Hito de julio', 'end_date' => '2026-07-14']);
        Task::create(['title' => 'Cruza el mes', 'start_date' => '2026-06-29', 'end_date' => '2026-07-03']);
        Task::create(['title' => 'Sin programar']);

        Livewire::test(TaskGantt::class)
            ->assertSee('Rango de julio')
            ->assertSee('Hito de julio')
            ->assertSee('Cruza el mes')
            ->assertSee('Sin programar')
            ->call('nextMonth')
            ->assertDontSee('Rango de julio');

        Carbon::setTestNow();
    }

    public function test_tasks_can_be_opened_from_the_list_and_gantt_then_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Planificar semana', 'start_date' => '2026-07-10', 'end_date' => '2026-07-12']);

        Livewire::test(TaskList::class)
            ->call('openForm', $task->id)
            ->assertSet('showForm', true)
            ->assertSet('title', 'Planificar semana');

        Livewire::test(TaskGantt::class)
            ->call('openForm', $task->id)
            ->set('startDate', '2026-07-14')
            ->set('endDate', '2026-07-18')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'start_date' => '2026-07-14 00:00:00', 'end_date' => '2026-07-18 00:00:00']);
    }

    public function test_gantt_description_preview_renders_safe_markdown(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Documentar', 'description' => '**Importante**']);

        Livewire::test(TaskGantt::class)
            ->call('openForm', $task->id)
            ->set('descriptionMode', 'preview')
            ->assertSeeHtml('<strong>Importante</strong>');
    }

    public function test_planning_groups_only_pending_tasks_by_their_scheduled_date(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        Task::create(['title' => 'Atrasada', 'start_date' => '2026-07-11']);
        Task::create(['title' => 'Hoy', 'start_date' => '2026-07-12']);
        Task::create(['title' => 'Mañana', 'start_date' => '2026-07-13']);
        Task::create(['title' => 'Futura por fin', 'end_date' => '2026-07-14']);
        Task::create(['title' => 'Sin fecha']);
        Task::create(['title' => 'Completada', 'start_date' => '2026-07-12', 'completed' => true]);

        Livewire::test(TaskPlanning::class)
            ->assertSee('Atrasada')->assertSee('Hoy')->assertSee('Mañana')->assertSee('Futura por fin')->assertSee('Sin fecha')
            ->assertDontSee('Completada');

        Carbon::setTestNow();
    }

    public function test_planning_quick_actions_shift_ranges_and_clear_both_dates(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $range = Task::create(['title' => 'Rango', 'start_date' => '2026-07-10', 'end_date' => '2026-07-12']);
        $single = Task::create(['title' => 'Solo inicio', 'start_date' => '2026-07-10']);

        Livewire::test(TaskPlanning::class)->call('moveToDay', $range->id, 1);
        $this->assertDatabaseHas('tasks', ['id' => $range->id, 'start_date' => '2026-07-13 00:00:00', 'end_date' => '2026-07-15 00:00:00']);

        Livewire::test(TaskPlanning::class)->call('moveToDay', $single->id, 2);
        $this->assertDatabaseHas('tasks', ['id' => $single->id, 'start_date' => '2026-07-14 00:00:00', 'end_date' => null]);

        Livewire::test(TaskPlanning::class)->call('clearDates', $range->id);
        $this->assertDatabaseHas('tasks', ['id' => $range->id, 'start_date' => null, 'end_date' => null]);
        Carbon::setTestNow();
    }

    public function test_planning_can_assign_a_task_without_dates_to_a_day(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Sin programar']);

        Livewire::test(TaskPlanning::class)->call('moveToDay', $task->id, 1);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'start_date' => null,
            'end_date' => '2026-07-13 00:00:00',
        ]);
        Carbon::setTestNow();
    }

    public function test_planning_moves_keep_task_times_and_estimation(): void
    {
        Carbon::setTestNow('2026-07-12 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create([
            'title' => 'Con hora',
            'start_date' => '2026-07-10 09:30:00',
            'end_date' => '2026-07-10 11:00:00',
            'estimated_time' => 90,
        ]);
        Livewire::test(TaskPlanning::class)->call('moveToDay', $task->id, 1);

        $task->refresh();

        $this->assertTrue($task->start_date->isSameDay('2026-07-13'));
        $this->assertEquals(90, $task->start_date->diffInMinutes($task->end_date));
        $this->assertSame(90, $task->estimated_time);
        Carbon::setTestNow();
    }
}
