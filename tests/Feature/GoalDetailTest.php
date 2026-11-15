<?php

namespace Tests\Feature;

use App\Livewire\Goal\GoalDetail;
use App\Livewire\Goal\GoalIndex;
use App\Models\Goal;
use App\Models\GoalEntry;
use App\Models\GoalNumericEntry;
use App\Models\User;
use App\Support\GoalProgress;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_list_card_opens_a_dedicated_owned_detail_page(): void
    {
        $owner = User::factory()->create();
        $goal = $owner->goals()->create(['title' => 'Completar la certificación']);
        $otherGoal = User::factory()->create()->goals()->create(['title' => 'Objetivo privado']);

        $this->actingAs($owner)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Completar la certificación');

        $this->actingAs($owner)
            ->get(route('goals.show', $otherGoal))
            ->assertNotFound();
    }

    public function test_creation_form_persists_the_single_kpi_contract(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GoalIndex::class)
            ->set('title', 'Ahorrar para un viaje')
            ->set('description', 'Fondo de emergencia')
            ->set('startDate', '2026-07-01')
            ->set('dueDate', '2026-12-31')
            ->set('kpiEnabled', true)
            ->set('kpiName', 'Ahorro acumulado')
            ->set('kpiUnit', 'COP')
            ->set('kpiDirection', 'increase')
            ->set('kpiStartValue', 100000)
            ->set('kpiTargetValue', 3000000)
            ->call('save')
            ->assertHasNoErrors();

        $goal = Goal::query()->where('title', 'Ahorrar para un viaje')->firstOrFail();
        $this->assertSame('Ahorro acumulado', $goal->numeric_goal['name']);
        $this->assertSame('increase', $goal->numeric_goal['direction']);
        $this->assertEquals(100000.0, $goal->numeric_goal['currentValue']);
    }

    public function test_progress_supports_increasing_decreasing_and_expected_pacing(): void
    {
        Carbon::setTestNow('2026-07-06 10:00:00');

        $increase = GoalProgress::calculate([
            'enabled' => true, 'startValue' => 0, 'currentValue' => 60, 'targetValue' => 100,
        ], Carbon::parse('2026-07-01'), Carbon::parse('2026-07-11'));
        $decrease = GoalProgress::calculate([
            'enabled' => true, 'direction' => 'decrease', 'startValue' => 100, 'currentValue' => 70, 'targetValue' => 40,
        ], Carbon::parse('2026-07-01'), Carbon::parse('2026-07-11'));

        $this->assertSame(60.0, $increase['actualPercent']);
        $this->assertSame(50.0, $increase['expectedPercent']);
        $this->assertSame(50.0, $increase['expectedValue']);
        $this->assertSame(50.0, $decrease['actualPercent']);

        Carbon::setTestNow();
    }

    public function test_numeric_and_text_progress_are_managed_and_current_value_is_recalculated(): void
    {
        $user = User::factory()->create();
        $goal = $user->goals()->create([
            'title' => 'Meta medible',
            'start_date' => '2026-07-01',
            'due_date' => '2026-08-01',
            'numeric_goal' => ['enabled' => true, 'name' => 'Kilómetros', 'unit' => 'km', 'direction' => 'increase', 'startValue' => 0, 'targetValue' => 100, 'currentValue' => 0],
        ]);

        $detail = Livewire::actingAs($user)
            ->test(GoalDetail::class, ['goal' => $goal->id])
            ->call('openNumericForm')
            ->set('numericValue', 35)
            ->set('numericDate', '2026-07-10')
            ->set('numericNote', 'Primera salida')
            ->call('saveNumericEntry')
            ->assertHasNoErrors()
            ->call('openEntryForm')
            ->set('entryText', 'Completé el entrenamiento de base')
            ->set('entryDate', '2026-07-10')
            ->set('entryIsMilestone', true)
            ->call('saveEntry')
            ->assertHasNoErrors();

        $numeric = GoalNumericEntry::query()->where('goal_id', $goal->id)->firstOrFail();
        $entry = GoalEntry::query()->where('goal_id', $goal->id)->firstOrFail();
        $goal->refresh();

        $this->assertEquals(35.0, $goal->numeric_goal['currentValue']);
        $this->assertTrue($entry->is_milestone);

        $detail->call('deleteNumericEntry', $numeric->id)->call('deleteEntry', $entry->id);
        $goal->refresh();

        $this->assertEquals(0.0, $goal->numeric_goal['currentValue']);
        $this->assertDatabaseMissing('goal_entries', ['id' => $entry->id]);
    }

    public function test_detail_creates_and_links_a_new_task(): void
    {
        $user = User::factory()->create();
        $goal = $user->goals()->create(['title' => 'Organizar mudanza']);

        Livewire::actingAs($user)
            ->test(GoalDetail::class, ['goal' => $goal->id])
            ->call('openTaskForm')
            ->assertDispatched('task-editor');

        Livewire::actingAs($user)
            ->test(\App\Livewire\Task\TaskEditor::class, ['goalContext' => $goal->id])
            ->set('title', 'Pedir cotizaciones')
            ->set('priority', 'not-urgent-important')
            ->call('save')
            ->assertHasNoErrors();

        $task = $user->tasks()->where('title', 'Pedir cotizaciones')->firstOrFail();
        $this->assertTrue($goal->tasks()->whereKey($task->id)->exists());
    }
}
