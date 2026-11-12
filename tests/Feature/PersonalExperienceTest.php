<?php

namespace Tests\Feature;

use App\Livewire\Onboarding\GoalEditor;
use App\Livewire\Onboarding\HabitEditor;
use App\Livewire\Onboarding\TaskEditor;
use App\Livewire\Onboarding\Welcome;
use App\Livewire\Settings\ModulePreferences;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Models\UserActiveDay;
use App\Services\Experience\PersonalExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function experience(): PersonalExperience
    {
        return app(PersonalExperience::class);
    }

    public function test_registration_creates_required_guide_without_personal_routine(): void
    {
        $this->get('/login')->assertOk()->assertSee('Regístrate');
        $this->get('/register')->assertOk();
        $this->post('/register', ['name' => 'Persona nueva', 'email' => 'new@example.test',
            'password' => 'test-password-123', 'password_confirmation' => 'test-password-123'])->assertRedirect(route('onboarding'));
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($this->experience()->required($user->id));
        $this->assertDatabaseCount('habit_definitions', 0);
        $this->assertDatabaseCount('negative_habit_definitions', 0);
        $this->get('/')->assertRedirect(route('onboarding'));
        $this->get('/tasks')->assertRedirect(route('onboarding'));
        $this->get(route('settings'))->assertOk()->assertSee('Cerrar sesión');
        $this->get(route('onboarding'))->assertOk();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guide_validates_resumes_and_creates_a_single_real_habit_without_completion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->experience()->begin($user->id, true);
        Livewire::test(Welcome::class)->call('next')->assertHasErrors(['context', 'intention']);
        Livewire::test(Welcome::class)->set('context', 'study')->set('intention', 'Estudiar mejor')->call('next')->assertHasNoErrors();
        $this->assertSame(2, $this->experience()->state($user->id)['step']);
        Livewire::test(Welcome::class)->assertSet('context', 'study')->assertSet('intention', 'Estudiar mejor')
            ->set('modules', ['vehicles'])->call('next')->assertHasErrors('modules');
        Livewire::test(Welcome::class)->set('modules', ['habits', 'tasks', 'goals'])->call('next')->assertHasNoErrors();
        Livewire::test(Welcome::class)->call('next')->assertHasErrors('habit');
        $editor = Livewire::test(HabitEditor::class)->call('openGuidedHabit')->set('name', 'Leer 10 minutos')->set('baseTime', '18:00')->call('save')->assertHasNoErrors();
        // Retry the same submitted creation: server-owned ID prevents a second record.
        $editor->set('name', 'Leer 10 minutos')->set('timeOfDay', 'anytime')->set('baseTime', '18:00')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('habit_definitions', 1);
        $this->assertDatabaseCount('habit_completions', 0);
        $this->assertDatabaseCount('game_events', 0);
        Livewire::test(Welcome::class)->call('next')->assertHasNoErrors();
        Livewire::test(Welcome::class)->call('next')->call('finish')->assertRedirect(route('home'));
        $this->assertFalse($this->experience()->required($user->id));
        $this->get('/')->assertOk()->assertSee('Leer 10 minutos')->assertDontSee('Atención para tus vehículos');
        $state = $this->experience()->state($user->id);
        $this->assertArrayHasKey('completed', $state['events']);
        $this->assertArrayNotHasKey('first_real_action', $state['events']);
        UserActiveDay::countRecord($user->id);
        $first = $this->experience()->state($user->id)['events']['first_real_action'];
        $this->travel(1)->hours();
        UserActiveDay::countRecord($user->id);
        $this->assertSame($first, $this->experience()->state($user->id)['events']['first_real_action']);
    }

    public function test_existing_accounts_and_preferences_are_isolated_and_direct_links_still_work(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a);
        $habit = HabitDefinition::create(['name' => 'Mi hábito existente', 'time_of_day' => 'anytime']);
        $this->get('/')->assertOk();
        $this->get(route('settings.section', 'modules'))->assertOk()->assertSee('Guardar preferencias');
        $this->assertFalse($this->experience()->required($a->id));
        $this->assertTrue($this->experience()->visible('vehicles', $a->id));
        Livewire::test(ModulePreferences::class)->set('modules', ['habits'])->call('save')->assertHasNoErrors();
        $this->assertFalse($this->experience()->visible('vehicles', $a->id));
        $this->assertTrue($this->experience()->visible('vehicles', $b->id));
        $response = $this->get('/')->assertOk()->assertSee('Personalizar módulos');
        $response->assertDontSee('Registro rápido')->assertDontSee('Crear tarea')->assertDontSee('Bitácora del día');
        $this->get(route('vehicles'))->assertOk();
        $this->experience()->saveModules($a->id, []);
        $this->get('/')->assertOk()->assertSee('Tu Inicio está despejado');
        $this->assertDatabaseHas('habit_definitions', ['id' => $habit->id]);
        Livewire::test(ModulePreferences::class)->set('modules', ['invented'])->call('save')->assertHasErrors('modules');
        $this->assertDatabaseMissing('module_settings', ['user_id' => $b->id]);
    }

    public function test_optional_shared_goal_and_task_editors_are_idempotent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->experience()->begin($user->id, true);
        $this->experience()->change($user->id, fn ($s) => array_replace($s, ['step' => 4, 'intention' => 'Mi proyecto']));
        $goal = Livewire::test(GoalEditor::class)->call('openGuidedGoal')->set('title', 'Terminar el semestre')->call('save')->assertHasNoErrors();
        $goal->set('title', 'Terminar el semestre')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('goals', 1);
        $task = Livewire::test(TaskEditor::class)->call('openGuidedTask')->set('title', 'Revisar apuntes')->call('save')->assertHasNoErrors();
        $task->call('openGuidedTask')->set('title', 'Revisar apuntes')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('tasks', ['title' => 'Revisar apuntes', 'completed' => false]);
    }

    public function test_cannot_finish_early_or_create_guided_records_outside_the_correct_step(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->experience()->begin($user->id, true);
        Livewire::test(Welcome::class)->call('finish')->assertStatus(422);
        Livewire::test(HabitEditor::class)->set('name', 'Fuera de paso')->call('save')->assertForbidden();
        $this->assertDatabaseCount('habit_definitions', 0);
        $this->assertTrue($this->experience()->required($user->id));
    }

    public function test_report_separates_mature_visits_from_actual_habit_use_without_personal_text(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->experience()->begin($user->id, true);
        $this->experience()->change($user->id, fn ($s) => array_replace($s, ['started_at' => now()->subDays(10)->toIso8601String(), 'context' => 'study', 'intention' => 'Texto privado que no debe salir']));
        $habit = HabitDefinition::create(['name' => 'Un hábito privado', 'time_of_day' => 'anytime']);
        HabitCompletion::create(['habit_id' => $habit->id, 'date' => now()->toDateString(), 'completed' => true]);
        $this->assertArrayHasKey('first_real_action', $this->experience()->state($user->id)['events']);
        UserActiveDay::markActive($user->id, 'direct', now()->subDays(9)->toDateString());
        $before = ModuleSetting::withoutGlobalScopes()->first()->getRawOriginal();
        $this->artisan('experience:report')->expectsOutputToContain('Visitas y acciones son señales distintas.')->assertSuccessful();
        $this->assertSame($before, ModuleSetting::withoutGlobalScopes()->first()->getRawOriginal());
        Artisan::call('experience:report');
        $output = Artisan::output();
        $this->assertStringNotContainsString('Texto privado', $output);
        $this->assertStringNotContainsString('Un hábito privado', $output);
        $this->assertStringNotContainsString($user->email, $output);
    }
}
