<?php

namespace Tests\Feature;

use App\Livewire\Goal\GoalDetail;
use App\Livewire\Goal\GoalIndex;
use App\Livewire\Habit\HabitSettings;
use App\Livewire\Meal\MealRecipes;
use App\Livewire\Plan\PlanIndex;
use App\Livewire\Task\TaskList;
use App\Livewire\Vehicle\VehicleIndex;
use App\Livewire\Habit\HabitTracker;
use App\Livewire\Home\Dashboard;
use App\Livewire\Home\DayClose;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameQuote;
use App\Models\Game\GameSpecies;
use App\Models\Goal;
use App\Models\HabitDefinition;
use App\Models\Task;
use App\Models\User;
use App\Services\Game\DailyBuddy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Game\GameTestHelpers;
use Tests\TestCase;

class CompanionVoiceTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    public function test_director_quotes_are_user_scoped_and_local_catalog_is_available_without_seed_rows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        GameQuote::query()->delete();
        GameQuote::create(['user_id' => $other->id, 'source' => 'director', 'text' => 'Ajena']);
        GameQuote::create(['user_id' => $user->id, 'source' => 'director', 'text' => 'Propia']);
        config(['companion_voice.A' => ['A01' => 'Local']]);
        $buddy = app(DailyBuddy::class);
        $this->assertSame('Propia', $buddy->quote($user->id, 'Local'));
        $this->assertSame('Local', $buddy->quote($user->id, 'Propia'));
        $this->assertDatabaseCount('game_quotes', 2);
    }

    public function test_day_close_keeps_buddy_and_quote_during_passive_refresh(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seedSpecies();
        GameCompanion::create(['line_key' => 'a', 'species_id' => GameSpecies::first()->id, 'first_met_at' => now()]);
        $component = Livewire::test(DayClose::class);
        $before = $component->get('buddy');
        $component->call('$refresh')->assertSet('buddy', $before);
        $this->assertContains($before['quote'], config('companion_voice.A'));
    }

    public function test_habit_day_feedback_is_contextual_and_reused_by_the_coach(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        GameCompanion::create(['line_key' => 'a', 'species_id' => GameSpecies::first()->id, 'first_met_at' => now()]);
        $habit = HabitDefinition::create(['name' => 'Respirar', 'time_of_day' => 'morning']);
        $component = Livewire::test(HabitTracker::class)->call('toggleHabit', $habit->id);
        $feedback = $component->get('lastHabitFeedback');
        $this->assertContains($feedback['message'], config('companion_voice.D'));
        $component->assertViewHas('coachCard', fn ($card) => $card['message'] === $feedback['message']);
        $component->call('$refresh')->assertSet('lastHabitFeedback', $feedback);
        $component->call('toggleHabit', $habit->id)->assertDispatched('habit-feedback', tone: 'neutral');
    }

    public function test_routine_voice_survives_refresh_and_navigation_back_to_the_same_habit(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        GameCompanion::create(['line_key' => 'a', 'species_id' => GameSpecies::first()->id, 'first_met_at' => now()]);
        $first = HabitDefinition::create(['name' => 'Respirar', 'time_of_day' => 'morning', 'base_time' => '08:00']);
        HabitDefinition::create(['name' => 'Estirar', 'time_of_day' => 'morning', 'base_time' => '08:10']);
        $component = Livewire::test(HabitTracker::class)->call('startRoutine', 'morning');
        $voice = $component->get('routine')['voice'][$first->id];
        $component->call('$refresh')->call('routineSkip')->call('routineGoTo', 0);
        $this->assertSame($voice, $component->get('routine')['voice'][$first->id]);
    }

    private function ownedGoal(bool $withCompanion = true): Goal
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        if ($withCompanion) {
            $this->seedSpecies();
            GameCompanion::create(['line_key' => 'a', 'species_id' => GameSpecies::first()->id, 'first_met_at' => now()]);
        }

        return Goal::create(['title' => 'Viaje con á y <script>alert(1)</script>', 'status' => 'active']);
    }

    public function test_completion_celebrates_once_preserves_plain_text_and_survives_render(): void
    {
        $goal = $this->ownedGoal();
        config(['companion_voice.F' => ['F01' => config('companion_voice.F.F01')]]);
        $component = Livewire::test(GoalDetail::class, ['goal' => $goal->id])
            ->call('updateStatus', 'completed')->assertHasNoErrors()
            ->assertSet('feedback.title', 'Objetivo completado');
        $message = $component->get('feedback')['message'];
        $this->assertSame('¡COMPLETASTE '.$goal->title.'! *gritito nada discreto*', $message);
        $component->assertSee(e($message), false)->assertDontSee('<script>alert(1)</script>', false);
        $component->call('$refresh')->assertSet('feedback.message', $message);
        $component->call('updateStatus', 'completed')->assertSet('feedback.message', 'Estado del objetivo actualizado.');
        $component->call('updateStatus', 'active')->assertSet('feedback.message', 'Estado del objetivo actualizado.');
        $component->call('updateStatus', 'abandoned')->assertSet('feedback.message', 'Estado del objetivo actualizado.');
        $this->assertSame('abandoned', $goal->fresh()->status);
    }

    public function test_only_a_new_milestone_today_uses_the_celebratory_group(): void
    {
        $goal = $this->ownedGoal();
        config(['companion_voice.E' => ['E01' => config('companion_voice.E.E01')]]);
        $component = Livewire::test(GoalDetail::class, ['goal' => $goal->id])->call('openEntryForm')
            ->set('entryText', 'Un paso real')->set('entryIsMilestone', true)->call('saveEntry')
            ->assertSet('feedback.message', 'Un hito más en '.$goal->title.'. *intenta mantener la calma*');
        $entry = $goal->goalEntries()->firstOrFail();
        $component->call('openEntryForm', $entry->id)->assertSet('feedback', null)
            ->set('entryText', 'Editado')->call('saveEntry')->assertSet('feedback.message', 'Registro actualizado.');
        $component->call('openEntryForm')->set('entryText', 'Histórico')->set('entryIsMilestone', true)
            ->set('entryDate', today()->subDay()->toDateString())->call('saveEntry')->assertSet('feedback.message', 'Hito registrado.');
        $component->call('openEntryForm')->set('entryText', 'Normal')->call('saveEntry')
            ->assertSet('feedback.message', 'Avance registrado.');
        $this->assertSame('active', $goal->fresh()->status);
        $component->set('entryText', '')->call('saveEntry')->assertHasErrors(['entryText'])->assertSet('feedback', null);
    }

    public function test_without_companion_or_with_game_disabled_confirmation_remains_functional(): void
    {
        $goal = $this->ownedGoal(false);
        Livewire::test(GoalDetail::class, ['goal' => $goal->id])->call('updateStatus', 'completed')
            ->assertSet('feedback.message', 'Objetivo completado: '.$goal->title.'.');
        $this->seedSpecies();
        GameCompanion::create(['line_key' => 'a', 'species_id' => GameSpecies::first()->id, 'first_met_at' => now()]);
        $this->gameSettings(['enabled' => false]);
        Livewire::test(GoalDetail::class, ['goal' => $goal->id])->call('openEntryForm')
            ->set('entryText', 'Hito')->set('entryIsMilestone', true)->call('saveEntry')
            ->assertSet('feedback.message', 'Hito registrado en '.$goal->title.'.');
    }

    public function test_numeric_target_does_not_complete_goal_or_emit_completion_message(): void
    {
        $goal = $this->ownedGoal();
        $goal->update(['numeric_goal' => ['enabled' => true, 'name' => 'Distancia', 'unit' => 'km', 'direction' => 'increase', 'startValue' => 0, 'targetValue' => 10, 'currentValue' => 0]]);
        $component = Livewire::test(GoalDetail::class, ['goal' => $goal->id])->call('openNumericForm')
            ->set('numericValue', 10)->call('saveNumericEntry')->assertHasNoErrors()
            ->assertSet('feedback.message', 'Medición registrada.');
        $component->call('openNumericForm', $goal->goalNumericEntries()->first()->id)
            ->set('numericValue', 11)->call('saveNumericEntry')->assertSet('feedback.message', 'Registro actualizado.');
        $this->assertSame('active', $goal->fresh()->status);
    }

    public function test_feedback_does_not_allow_mutating_another_users_goal(): void
    {
        $goal = $this->ownedGoal();
        $foreign = User::factory()->create()->goals()->create(['title' => 'Privado', 'status' => 'active']);
        Livewire::test(GoalDetail::class, ['goal' => $goal->id])->set('goalId', $foreign->id)->assertNotFound();
        $this->assertSame('active', $foreign->fresh()->status);
    }

    public function test_empty_current_period_does_not_claim_all_habits_are_complete(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(Dashboard::class)->set('checkin', true)
            ->assertSee('No hay hábitos pendientes para este momento.');
    }

    public function test_close_empty_state_does_not_claim_tomorrows_tasks_are_done(): void
    {
        $this->actingAs(User::factory()->create());
        Task::create(['title' => 'Tarea de mañana', 'end_date' => today()->addDay()->setTime(12, 0)]);
        Livewire::test(DayClose::class)->assertSee('No quedan tareas pendientes en este cierre.')
            ->assertDontSee('Nada pendiente para mañana.')->assertDontSee('Tarea de mañana');
        $this->assertDatabaseHas('tasks', ['title' => 'Tarea de mañana', 'completed' => false]);
        $this->assertSame('No hay avances escritos en {goal} desde hace {days} días. ¿Quieres registrar alguno?', config('game.guardian_advice.goals.idle'));
    }

    public function test_six_empty_states_keep_their_creation_actions_with_the_approved_voice(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(HabitSettings::class)->assertSee('Yo me encargo de los aplausos.')
            ->assertSee('Agregar predeterminados')->assertSee('Nuevo hábito');
        Livewire::test(GoalIndex::class)->set('statusFilter', 'all')
            ->assertSee('¿Qué quieres lograr? Crea tu primer objetivo. *ya se emocionó*');
        Livewire::test(TaskList::class)->set('statusQuery', 'all')->assertSee('¿Algo dando vueltas por tu cabeza? Crea una tarea y lo ponemos por escrito.');
        Livewire::test(PlanIndex::class)->assertSee('Guarda tu primer plan. *ya está buscando qué ponerse*');
        Livewire::test(MealRecipes::class)->assertSee('Todavía no hay recetas. *se pone el delantal*')
            ->assertSee('Crear receta');
        Livewire::test(VehicleIndex::class)->assertSee('Tu garaje está vacío')
            ->assertSee('*reserva sitio* Añade tu vehículo para programar sus cuidados.');
        $this->assertDatabaseCount('goals', 0);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('habit_definitions', 0);
    }

    public function test_filtered_empty_states_keep_recovery_copy_instead_of_the_first_record_invitation(): void
    {
        $this->actingAs(User::factory()->create());
        HabitDefinition::create(['name' => 'Respirar', 'time_of_day' => 'morning']);
        Livewire::test(HabitSettings::class)->set('search', 'sin coincidencias')
            ->assertSee('Ningún hábito coincide')->assertDontSee('Yo me encargo de los aplausos.');
        Livewire::test(GoalIndex::class)->assertSee('No hay objetivos con el estado')
            ->assertDontSee('*ya se emocionó*');
        Livewire::test(TaskList::class)->set('search', 'sin coincidencias')
            ->assertSee('No hay tareas con los filtros actuales')->assertDontSee('¿Algo dando vueltas por tu cabeza?');
        Livewire::test(MealRecipes::class)->set('search', 'sin coincidencias')
            ->assertSee('No se encontraron recetas con esos filtros.')->assertDontSee('*se pone el delantal*');
    }
}
