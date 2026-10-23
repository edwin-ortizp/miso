<?php

namespace Tests\Feature;

use App\Livewire\Home\Panel\GoalEditor;
use App\Livewire\Home\Panel\PlanEditor;
use App\Livewire\Home\Panel\QuickEditor;
use App\Livewire\Home\Panel\ShoppingEditor;
use App\Livewire\Home\WallPanel;
use App\Models\DrinkType;
use App\Models\Goal;
use App\Models\HabitDefinition;
use App\Models\JournalEntry;
use App\Models\MealPlanEntry;
use App\Models\MoodState;
use App\Models\NegativeHabitDefinition;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\ShoppingItem;
use App\Models\Task;
use App\Models\User;
use App\Support\WallPanelData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WallPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(8, 0));
        $this->actingAs(User::factory()->create());
    }

    public function test_panel_has_its_own_authenticated_layout_and_keeps_home(): void
    {
        $this->get('/panel')->assertOk()->assertSee('wall-frame')->assertSee('Panel de pared')->assertSee('lt-sidebar--right', false)->assertDontSee('wall-flyout', false);
        $this->get('/')->assertOk();
        auth()->logout();
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_agenda_uses_real_dates_intervals_and_all_day_flags(): void
    {
        Task::create(['title' => 'Con hora', 'start_date' => today()->setTime(9, 0), 'end_date' => today()->setTime(10, 0), 'start_is_date' => false]);
        Task::create(['title' => 'Sin hora', 'end_date' => today(), 'end_is_date' => true]);
        Task::create(['title' => 'Intervalo', 'start_date' => today()->subDay(), 'end_date' => today()->addDay(), 'start_is_date' => true, 'end_is_date' => true]);
        $meal = MealPlanEntry::create(['date' => today(), 'meal_type' => 'almuerzo']);
        $meal->items()->create(['name' => 'Lentejas', 'position' => 0]);
        Plan::create(['title' => 'Salida', 'type' => 'activity', 'status' => 'scheduled', 'scheduled_on' => today()]);
        $data = app(WallPanelData::class)->forToday();
        $this->assertCount(5, $data['week']);
        $rows = collect($data['week'][0]['rows'])->keyBy('title');
        $this->assertSame('09:00', $rows['Con hora']['time']);
        $this->assertNull($rows['Sin hora']['time']);
        $this->assertTrue(collect($data['week'][1]['rows'])->contains('title', 'Intervalo'));
        $this->assertSame('Con hora', $data['nextTasks']->first()['title']);
        $this->assertTrue($rows->has('Almuerzo · Lentejas'));
        $this->get('/panel')->assertOk()->assertSee('Salida')->assertSee('Lentejas');
    }

    public function test_quick_records_refresh_real_totals_and_validate_amounts(): void
    {
        $drink = DrinkType::create(['name' => 'Café', 'hydration_factor' => .8]);
        $habit = HabitDefinition::create(['name' => 'Leer', 'time_of_day' => 'anytime']);
        $state = MoodState::create(['text' => 'Bien', 'emoji' => '🙂', 'value' => 4, 'is_active' => true]);
        Livewire::test(WallPanel::class)->call('quickAddWater', $drink->id, 250)
            ->assertViewHas('waterTotal', fn ($value) => (int) $value === 200)
            ->call('toggleHabit', $habit->id)->call('saveMood', $state->id)->call('saveEnergy', 4)->assertHasNoErrors()
            ->assertViewHas('trend', fn ($trend) => $trend->last()['energy'] === 4)
            ->call('quickAddWater', $drink->id, -1)->assertHasErrors('amount');
        $this->assertDatabaseCount('drink_logs', 1);
        $this->assertDatabaseHas('habit_completions', ['habit_id' => $habit->id, 'completed' => true]);
        $this->assertDatabaseHas('mood_entries', ['mood_state_id' => $state->id]);
    }

    public function test_user_cannot_read_or_mutate_another_users_records(): void
    {
        $owner = auth()->user();
        $this->actingAs(User::factory()->create());
        $task = Task::create(['title' => 'Privado', 'end_date' => today()]);
        $drink = DrinkType::create(['name' => 'Bebida privada', 'hydration_factor' => 1]);
        $goal = Goal::create(['title' => 'Objetivo privado', 'status' => 'active']);
        $habit = NegativeHabitDefinition::create(['name' => 'Privado']);
        $this->actingAs($owner);
        $this->get('/panel')->assertDontSee('Bebida privada')->assertDontSee('Objetivo privado');
        Livewire::test(WallPanel::class)->call('toggleTask', $task->id)->assertNotFound();
        Livewire::test(WallPanel::class)->call('quickAddWater', $drink->id, 250)->assertNotFound();
        Livewire::test(GoalEditor::class)->call('openPanelGoal', $goal->id)->assertNotFound();
        Livewire::test(QuickEditor::class)->call('open', 'avoid', (string) $habit->id)->assertNotFound();
    }

    public function test_snooze_preserves_duration_and_due_date_only_tasks(): void
    {
        $task = Task::create(['title' => 'Reunión', 'start_date' => today()->setTime(9, 0), 'end_date' => today()->setTime(10, 30)]);
        Livewire::test(QuickEditor::class)->call('open', 'snooze', $task->id)->set('scheduledDate', '2026-10-24')->set('scheduledTime', '14:00')->call('save')->assertHasNoErrors();
        $task->refresh();
        $this->assertSame('2026-10-24 15:30', $task->end_date->format('Y-m-d H:i'));
        $task = Task::create(['title' => 'Vence', 'end_date' => today(), 'end_is_date' => true]);
        Livewire::test(QuickEditor::class)->call('open', 'snooze', $task->id)->set('scheduledDate', '2026-10-25')->call('save');
        $this->assertNull($task->fresh()->start_date);
        $this->assertTrue($task->fresh()->end_is_date);
    }

    public function test_journal_preserves_existing_content_and_detects_concurrent_edits(): void
    {
        $entry = JournalEntry::create(['date' => today(), 'text' => 'Texto completo', 'summary' => 'Resumen']);
        $editor = Livewire::test(QuickEditor::class)->call('open', 'journal')->assertSet('text', 'Texto completo');
        $entry->update(['text' => 'Cambio externo']);
        $editor->set('text', 'Borrador local')->call('save')->assertHasErrors('text')->assertSet('text', 'Borrador local');
        $this->assertSame('Cambio externo', $entry->fresh()->text);
        $editor->call('open', 'journal')->set('text', 'Texto revisado')->call('save')->assertHasNoErrors();
        $this->assertSame('Resumen', $entry->fresh()->summary);
    }

    public function test_numeric_and_narrative_goal_entries_use_existing_rules(): void
    {
        $goal = Goal::create(['title' => 'Libros', 'status' => 'active', 'numeric_goal' => ['enabled' => true, 'unit' => 'libros', 'startValue' => 0, 'targetValue' => 12]]);
        Livewire::test(GoalEditor::class)->call('openPanelGoal', $goal->id)->assertSet('showNumericForm', true)->set('numericValue', 3)->call('saveNumericEntry')->assertHasNoErrors();
        $this->assertEquals(3, $goal->fresh()->numeric_goal['currentValue']);
        $goal = Goal::create(['title' => 'Aprender', 'status' => 'active']);
        Livewire::test(GoalEditor::class)->call('openPanelGoal', $goal->id)->assertSet('showEntryForm', true)->set('entryText', 'Terminé el capítulo')->call('saveEntry')->assertHasNoErrors();
        $this->assertDatabaseHas('goal_entries', ['goal_id' => $goal->id, 'text' => 'Terminé el capítulo']);
    }

    public function test_midnight_refresh_does_not_change_an_open_editor_date(): void
    {
        $panel = Livewire::test(WallPanel::class);
        $editor = Livewire::test(QuickEditor::class)->call('open', 'journal');
        $this->travelTo(now()->addDay());
        $panel->call('refreshPanel')->assertSet('selectedDate', '2026-10-24');
        $editor->set('text', 'Del día anterior')->call('save');
        $this->assertSame('2026-10-23', JournalEntry::where('text', 'Del día anterior')->firstOrFail()->date->toDateString());
    }

    public function test_recurring_task_completion_keeps_the_existing_confirmation_flow(): void
    {
        $task = Task::create(['title' => 'Repetir', 'start_date' => today()->setTime(9, 0), 'end_date' => today()->setTime(10, 0), 'is_recurrent' => true, 'recurrence' => ['pattern' => 'daily', 'frequency' => 1]]);
        Livewire::test(WallPanel::class)->call('toggleTask', $task->id)->assertSet('showRecurringCompletion', true)
            ->set('nextOccurrenceDate', '2026-10-24')->call('confirmRecurringCompletion')->assertHasNoErrors()->assertSet('showRecurringCompletion', false);
        $this->assertDatabaseHas('tasks', ['title' => 'Repetir', 'completed' => true]);
        $this->assertTrue(Task::where('completed', false)->whereDate('start_date', '2026-10-24')->exists());
    }

    public function test_plan_visits_and_purchases_use_the_real_recorders(): void
    {
        $person = Relationship::create(['category' => 'friends', 'full_name' => 'Persona del panel']);
        $plan = Plan::create(['title' => 'Paseo', 'type' => 'activity', 'status' => 'scheduled', 'scheduled_on' => today()]);
        $plan->relationships()->attach($person->id);
        Livewire::test(PlanEditor::class)->call('openPanelPlan', $plan->id, true)
            ->assertSet('visitPeople', [$person->id])->call('saveVisit')->assertHasNoErrors()->assertDispatched('panel-changed');
        $this->assertDatabaseHas('plan_visits', ['plan_id' => $plan->id]);
        $item = ShoppingItem::create(['status' => 'available', 'name' => 'Pan', 'base_unit' => 'unit', 'stock' => 0, 'next_purchase' => true]);
        Livewire::test(ShoppingEditor::class)->call('openPanelShopping', $item->id)->set('purchaseQuantity', 2)->call('confirmPurchase')->assertHasNoErrors()->assertDispatched('panel-changed');
        $this->assertFalse($item->fresh()->next_purchase);
        $this->assertEquals(2, $item->fresh()->stock);
    }

    public function test_birthdays_last_day_meals_and_sensitive_events_are_respected(): void
    {
        Relationship::create(['category' => 'friends', 'full_name' => 'Cumpleañera', 'birthday_month' => 10, 'birthday_day' => 27]);
        $meal = MealPlanEntry::create(['date' => today()->addDays(4), 'meal_type' => 'cena']);
        $meal->items()->create(['name' => 'Cena del último día', 'position' => 0]);
        $data = app(WallPanelData::class)->forToday();
        $titles = collect($data['week'][4]['rows'])->pluck('title')->all();
        $this->assertContains('Cumpleaños · Cumpleañera', $titles);
        $this->assertContains('Cena · Cena del último día', $titles);
    }

    public function test_five_day_agenda_crosses_year_boundary_and_uses_spanish_dates(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-12-30 12:00:00'));
        config(['app.locale' => 'en']);
        \Carbon\Carbon::setLocale('en');
        $data = app(WallPanelData::class)->forToday();
        $this->assertCount(5, $data['week']);
        $this->assertSame('2026-12-30', $data['week'][0]['date']->toDateString());
        $this->assertSame('2027-01-03', $data['week'][4]['date']->toDateString());
        Livewire::test(WallPanel::class)->assertSee('30 dic')->assertSee('03 ene')->assertDontSee('30 Dec');
    }

    public function test_last_update_timestamp_advances_after_loading_fresh_data(): void
    {
        $panel = Livewire::test(WallPanel::class);
        $first = $panel->get('lastUpdatedAt');
        $this->assertNotEmpty($first);
        $this->travel(5)->minutes();
        $panel->call('refreshPanel')->assertSet('lastUpdatedAt', now()->format('Y-m-d\TH:i:s.uP'));
        $this->assertNotSame($first, $panel->get('lastUpdatedAt'));
    }
}
