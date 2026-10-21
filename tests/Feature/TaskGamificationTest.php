<?php

namespace Tests\Feature;

use App\Livewire\Task\TaskKanban;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskPlanning;
use App\Livewire\Task\TaskProgress;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskGamificationService;
use App\Support\DefaultTaskCategories;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskGamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::created(function (User $user) {
            DefaultTaskCategories::createFor($user);
            $user->taskCategories()->create(['key' => 'trabajo', 'name' => 'Trabajo']);
        });
        Carbon::setTestNow('2026-07-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_xp_uses_size_and_importance_and_stays_frozen_after_edits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create(['title' => 'Informe estratégico', 'size' => 'L', 'priority' => 'not-urgent-important']);

        Livewire::test(TaskList::class)->call('toggleComplete', $task->id)
            ->assertDispatched('task-completed', xp: 12);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed' => true, 'completion_xp' => 12, 'completed_at' => '2026-07-12 10:00:00']);
        $task->update(['size' => 'XS', 'priority' => 'not-urgent-not-important']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completion_xp' => 12]);
    }

    public function test_size_xp_combinations_and_reopening_clear_completion_details(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $gamification = app(TaskGamificationService::class);

        $this->assertSame(1, $gamification->completionXp(Task::create(['title' => 'Sin estimación'])));
        $this->assertSame(1, $gamification->completionXp(Task::create(['title' => 'Corta', 'size' => 'XS'])));
        $this->assertSame(2, $gamification->completionXp(Task::create(['title' => 'Pequeña', 'size' => 'S'])));
        $this->assertSame(4, $gamification->completionXp(Task::create(['title' => 'Media', 'size' => 'M'])));
        $this->assertSame(6, $gamification->completionXp(Task::create(['title' => 'Grande', 'size' => 'L'])));
        $task = Task::create(['title' => 'Muy grande', 'size' => 'XL', 'priority' => 'urgent-important']);

        Livewire::test(TaskList::class)->call('toggleComplete', $task->id)->call('toggleComplete', $task->id);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed' => false, 'completed_at' => null, 'completion_xp' => null]);
    }

    public function test_each_completion_view_uses_the_shared_action_and_emits_feedback(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([TaskList::class, TaskKanban::class, TaskPlanning::class] as $component) {
            $task = Task::create(['title' => "Completar desde {$component}", 'size' => 'M']);
            Livewire::test($component)->call('toggleComplete', $task->id)->assertDispatched('task-completed', xp: 4);
            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed' => true, 'completion_xp' => 4]);
        }
    }

    public function test_completing_a_recurrent_task_prompts_for_and_creates_the_next_occurrence(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = Task::create([
            'title' => 'Trapear la casa',
            'category' => 'hogar',
            'size' => 'S',
            'start_date' => '2026-07-12',
            'is_recurrent' => true,
            'recurrence' => ['pattern' => 'custom', 'frequency' => 1, 'customDays' => 7],
        ]);

        Livewire::test(TaskList::class)
            ->call('toggleComplete', $task->id)
            ->assertSet('showRecurringCompletion', true)
            ->assertSet('nextOccurrenceDate', '2026-07-19')
            ->set('nextOccurrenceDate', '2026-07-21')
            ->call('confirmRecurringCompletion')
            ->assertSet('showRecurringCompletion', false)
            ->assertDispatched('task-completed', xp: 2);

        // Lo completado queda como copia de historial y la misma tarea pasa a la próxima fecha.
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Trapear la casa',
            'completed' => true,
            'is_recurrence_history' => true,
            'completed_at' => '2026-07-12 10:00:00',
            'completion_xp' => 2,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'completed' => false,
            'is_recurrent' => true,
            'start_date' => '2026-07-21 00:00:00',
        ]);
        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_progress_reports_levels_streaks_daily_data_and_planning_metrics(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Task::create(['title' => 'Día uno', 'completed' => true, 'completion_xp' => 9, 'completed_at' => '2026-07-10 10:00:00', 'start_date' => '2026-07-10']);
        Task::create(['title' => 'Día dos', 'completed' => true, 'completion_xp' => 12, 'completed_at' => '2026-07-11 10:00:00', 'end_date' => '2026-07-11']);
        Task::create(['title' => 'Día tres', 'completed' => true, 'completion_xp' => 80, 'completed_at' => '2026-07-12 10:00:00', 'category' => 'trabajo', 'size' => 'XL', 'priority' => 'urgent-important', 'start_date' => '2026-07-12']);
        Task::create(['title' => 'Atrasada', 'start_date' => '2026-07-11']);

        Livewire::test(TaskProgress::class)
            ->assertSee('Nivel 2')
            ->assertSee('101 XP total')
            ->assertSee('racha actual')
            ->assertSee('Tareas atrasadas pendientes')
            ->assertSee('Trabajo');
    }

    public function test_progress_route_is_available_and_isolated_per_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $this->actingAs($firstUser);
        Task::create(['title' => 'Solo primera persona', 'completed' => true, 'completion_xp' => 10, 'completed_at' => now()]);
        $this->actingAs($secondUser);
        Task::create(['title' => 'Solo segunda persona', 'completed' => true, 'completion_xp' => 20, 'completed_at' => now()]);

        $this->actingAs($firstUser)->get('/tasks/progress')->assertOk()->assertSee('10 XP total')->assertDontSee('20 XP total');
    }
}
