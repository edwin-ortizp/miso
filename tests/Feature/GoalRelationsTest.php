<?php

namespace Tests\Feature;

use App\Livewire\Goal\{GoalDetail, GoalIndex};
use App\Livewire\Habit\HabitEditor;
use App\Livewire\Task\TaskEditor;
use App\Models\{Goal, GoalHabit, HabitDefinition, HabitCompletion, Task, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_links_to_detail_and_clearing_filters_shows_all(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = Goal::create(['title' => 'Leer tres libros']);
        Goal::create(['title' => 'Ya terminado', 'status' => 'completed']);
        Livewire::test(GoalIndex::class)->assertSee(route('goals.show', $goal), false)
            ->assertDontSee('Ya terminado')->call('clearFilters')->assertSet('statusFilter', 'all')->assertSee('Ya terminado');
    }

    public function test_existing_actions_can_support_multiple_goals_and_unlink_preserves_records_and_kpi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = Goal::create(['title' => 'Leer', 'numeric_goal' => ['enabled' => true, 'name' => 'Libros', 'unit' => 'libros', 'startValue' => 0, 'targetValue' => 3, 'currentValue' => 1]]);
        $other = Goal::create(['title' => 'Aprender']);
        $habit = HabitDefinition::create(['name' => 'Cinco minutos', 'time_of_day' => 'morning']);
        $task = Task::create(['title' => 'Escoger libro']);
        foreach ([$goal, $other] as $target) {
            $detail = Livewire::test(GoalDetail::class, ['goal' => $target->id]);
            foreach (['tasks' => $task->id, 'habits' => $habit->id] as $kind => $id) {
                $detail->call('openLinks', $kind)->set('selectedLinks', [(string) $id])->call('saveLinks')->assertHasNoErrors();
                $detail->call('openLinks', $kind)->set('selectedLinks', [(string) $id])->call('saveLinks')->assertHasNoErrors();
            }
        }
        $this->assertSame(2, GoalHabit::count());
        $this->assertSame(1, $goal->tasks()->count());
        Livewire::test(GoalDetail::class, ['goal' => $goal->id])->call('unlinkTask', $task->id)->call('unlinkHabit', $habit->id);
        $this->assertNotNull($task->fresh());
        $this->assertNotNull($habit->fresh());
        $this->assertSame(1, $other->habits()->count());
        $this->assertSame(1, $goal->fresh()->numeric_goal['currentValue']);
        $this->assertSame('active', $goal->fresh()->status);
    }

    public function test_foreign_selections_fail_atomically_and_search_hides_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);
        $foreign = HabitDefinition::create(['name' => 'Privado']);
        $foreignTask = Task::create(['title' => 'Tarea privada']);
        $this->actingAs($user);
        $goal = Goal::create(['title' => 'Mi objetivo']);
        $own = HabitDefinition::create(['name' => 'Propio']);
        $detail = Livewire::test(GoalDetail::class, ['goal' => $goal->id]);
        $detail->call('openLinks', 'habits')->assertDontSee('Privado')->set('selectedLinks', [$own->id, $foreign->id])->call('saveLinks')->assertHasErrors('selectedLinks');
        $this->assertSame(0, GoalHabit::count());
        $detail->call('openLinks', 'tasks')->assertDontSee('Tarea privada')->set('selectedLinks', [$foreignTask->id])->call('saveLinks')->assertHasErrors('selectedLinks');
        $this->assertSame(0, $goal->tasks()->count());
    }

    public function test_habit_editor_creates_and_links_atomically_with_shared_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = Goal::create(['title' => 'Leer']);
        $editor = Livewire::test(HabitEditor::class, ['goalContext' => $goal->id]);
        $editor->call('createHabit')->set('name', '')->call('save')->assertHasErrors('name');
        $this->assertSame(0, $goal->habits()->count());
        $editor->set('name', 'Leer cinco minutos')->set('goalDuration', '5 min')->call('save')->assertHasNoErrors()->assertDispatched('goal-relations-changed');
        $this->assertSame('Leer cinco minutos', $goal->habits()->first()->name);
        $editor->call('createHabit')->set('name', 'Sin guardar')->call('closeForm');
        $this->assertDatabaseMissing('habit_definitions', ['name' => 'Sin guardar']);
    }

    public function test_deleted_goal_rolls_back_new_records_and_rejects_foreign_editor_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = Goal::create(['title' => 'Temporal']);
        $editor = Livewire::test(HabitEditor::class, ['goalContext' => $goal->id])->set('name', 'No persistir');
        $goal->delete();
        $editor->call('save')->assertNotFound();
        $this->assertDatabaseMissing('habit_definitions', ['name' => 'No persistir']);
        $other = User::factory()->create()->goals()->create(['title' => 'Privado']);
        Livewire::test(TaskEditor::class, ['goalContext' => $other->id])->assertNotFound();
        Livewire::test(HabitEditor::class, ['goalContext' => $other->id])->assertNotFound();
    }

    public function test_domain_link_checks_ownership_without_an_authenticated_scope(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $goal = Goal::create(['title' => 'Propio']);
        $habit = HabitDefinition::create(['name' => 'Propio']);
        $foreign = User::factory()->create()->goals()->create(['title' => 'Ajeno']);
        auth()->logout();
        $this->assertThrows(fn () => GoalHabit::link($foreign, $habit), \InvalidArgumentException::class);
        GoalHabit::link($goal, $habit);
        $this->assertDatabaseHas('goal_habits', ['goal_id' => $goal->id, 'habit_id' => $habit->id, 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('goal_habits', ['goal_id' => $foreign->id]);
    }

    public function test_followup_uses_only_completed_days_in_inclusive_seven_day_window_and_cascade_keeps_sources(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
        $goal = Goal::create(['title' => 'Lectura']);
        $habit = HabitDefinition::create(['name' => 'Leer']);
        GoalHabit::link($goal, $habit);
        foreach ([0, 6, 7, -1] as $days) HabitCompletion::create(['habit_id' => $habit->id, 'date' => today()->subDays($days), 'completed' => true]);
        HabitCompletion::create(['habit_id' => $habit->id, 'date' => today()->subDays(2), 'completed' => false]);
        $detail = Livewire::test(GoalDetail::class, ['goal' => $goal->id]);
        $loaded = $detail->viewData('goal')->habits->first();
        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded->habitCompletions);
        $this->assertContains(today()->toDateString(), $loaded->habitCompletions->map(fn ($entry) => $entry->date->toDateString())->all());
        $detail->assertSee('Hecho hoy')->assertSee('2 de 7 días');
        $goal->delete();
        $this->assertSame(0, GoalHabit::count());
        $this->assertNotNull($habit->fresh());
        $this->assertSame(5, $habit->habitCompletions()->count());
        $another = Goal::create(['title' => 'Otro']);
        GoalHabit::link($another, $habit);
        $habit->delete();
        $this->assertSame(0, GoalHabit::count());
        $this->assertNotNull($another->fresh());
    }
}
