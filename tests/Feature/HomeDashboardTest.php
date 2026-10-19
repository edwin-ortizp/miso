<?php

namespace Tests\Feature;

use App\Livewire\Home\Dashboard;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Models\EnergyEntry;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\MealPlanEntry;
use App\Models\MoodEntry;
use App\Models\MoodState;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_renders_all_home_sections(): void
    {
        $meal = MealPlanEntry::create([
            'date' => now()->toDateString(),
            'meal_type' => 'almuerzo',
            'calories' => 620,
        ]);
        $meal->items()->create(['name' => 'Lentejas con arroz', 'position' => 0]);

        $this->get('/')->assertOk()->assertSee('md-module-shell', false);

        Livewire::test(Dashboard::class)
            ->assertSee('Registro rápido')
            ->assertSee('Hábitos')
            ->assertSee('Tareas de hoy')
            ->assertSee('Comidas de hoy')
            ->assertSee('Lentejas con arroz')
            ->assertSee('Tendencia semanal')
            ->assertSeeHtml('data-dashboard-chart');
    }

    public function test_quick_add_water_applies_hydration_factor(): void
    {
        $coffee = DrinkType::create(['name' => 'Café', 'hydration_factor' => 0.8]);

        Livewire::test(Dashboard::class)
            ->call('quickAddWater', $coffee->id, 250);

        $log = DrinkLog::first();
        $this->assertNotNull($log);
        $this->assertSame(250, (int) $log->amount);
        $this->assertSame(200, (int) $log->hydration_value);
        $this->assertSame('2026-07-13', $log->date->toDateString());
    }

    public function test_save_mood_creates_entry_from_catalog_state(): void
    {
        $state = MoodState::create(['emoji' => '😄', 'text' => 'Feliz', 'value' => 5]);

        Livewire::test(Dashboard::class)
            ->call('saveMood', $state->id);

        $entry = MoodEntry::first();
        $this->assertNotNull($entry);
        $this->assertSame('😄', $entry->emoji);
        $this->assertSame('Feliz', $entry->text);
        $this->assertSame(5, (int) $entry->value);
    }

    public function test_save_energy_creates_entry_and_rejects_out_of_range_levels(): void
    {
        Livewire::test(Dashboard::class)
            ->call('saveEnergy', 4)
            ->call('saveEnergy', 9);

        $this->assertSame(1, EnergyEntry::count());
        $this->assertSame(4, (int) EnergyEntry::first()->level);
    }

    public function test_toggle_habit_completes_and_dispatches_feedback(): void
    {
        $habit = HabitDefinition::create(['name' => 'Meditar', 'time_of_day' => 'morning']);
        HabitDefinition::create(['name' => 'Leer', 'time_of_day' => 'night']);

        Livewire::test(Dashboard::class)
            ->call('toggleHabit', $habit->id)
            ->assertDispatched('habit-feedback');

        $this->assertTrue((bool) HabitCompletion::where('habit_id', $habit->id)->first()->completed);
    }

    public function test_pending_habits_only_show_current_time_block_and_anytime(): void
    {
        HabitDefinition::create(['name' => 'Estirar', 'time_of_day' => 'morning']);
        HabitDefinition::create(['name' => 'Leer', 'time_of_day' => 'night']);
        HabitDefinition::create(['name' => 'Hidratarse', 'time_of_day' => 'anytime']);

        Livewire::test(Dashboard::class)
            ->assertViewHas('pendingHabits', function ($habits) {
                return $habits->pluck('name')->all() === ['Estirar', 'Hidratarse'];
            });
    }

    public function test_toggle_task_completes_regular_task(): void
    {
        $task = Task::create([
            'task_code' => 11111,
            'title' => 'Pagar servicios',
            'end_date' => now(),
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Pagar servicios')
            ->call('toggleTask', $task->id)
            ->assertDispatched('task-completed');

        $this->assertTrue((bool) $task->fresh()->completed);
    }

    public function test_toggle_recurring_task_opens_dialog_and_confirm_schedules_next(): void
    {
        $task = Task::create([
            'task_code' => 22222,
            'title' => 'Regar plantas',
            'end_date' => now(),
            'is_recurrent' => true,
            'recurrence' => ['pattern' => 'custom', 'frequency' => 1, 'customDays' => 3],
        ]);

        Livewire::test(Dashboard::class)
            ->call('toggleTask', $task->id)
            ->assertSet('showRecurringCompletion', true)
            ->set('nextOccurrenceDate', '2026-07-16')
            ->call('confirmRecurringCompletion')
            ->assertSet('showRecurringCompletion', false);

        // La tarea viva se mueve a la próxima fecha; lo completado queda como copia de historial.
        $task->refresh();
        $this->assertFalse((bool) $task->completed);
        $this->assertSame('2026-07-16', $task->end_date->toDateString());
        $this->assertTrue(Task::where('is_recurrence_history', true)->where('completed', true)->where('recurrence_series_id', $task->recurrence_series_id)->exists());
    }

    public function test_weekly_trend_reports_seven_days_of_percentages(): void
    {
        $water = DrinkType::create(['name' => 'Agua', 'hydration_factor' => 1.0]);
        $habit = HabitDefinition::create(['name' => 'Meditar', 'time_of_day' => 'morning']);
        DrinkLog::create([
            'date' => '2026-07-12',
            'drink_type' => $water->name,
            'amount' => 1250,
            'hydration_value' => 1250,
            'time' => '09:00',
            'timestamp' => now()->timestamp,
            'drink_type_id' => $water->id,
        ]);
        HabitCompletion::create(['habit_id' => $habit->id, 'date' => '2026-07-12', 'completed' => true]);

        Livewire::test(Dashboard::class)
            ->assertViewHas('weeklyTrend', function (array $trend) {
                $yesterday = $trend[5];

                return count($trend) === 7
                    && $yesterday['water'] === 50
                    && $yesterday['habits'] === 100;
            });
    }
}
