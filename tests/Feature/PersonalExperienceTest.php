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
use App\Services\Experience\OnboardingChoices;
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
        Livewire::test(Welcome::class)->call('next')->assertHasErrors('contexts');
        Livewire::test(Welcome::class)->set('contexts', ['work', 'study', 'care'])->call('next')->assertHasNoErrors();
        $this->assertSame('both', $this->experience()->state($user->id)['context']);
        $modules = $this->experience()->state($user->id)['modules'];
        Livewire::test(Welcome::class)->assertSet('contexts', ['work', 'study', 'care'])
            ->call('next')->assertHasErrors('need')->set('need', 'unsure')->call('next')->assertHasNoErrors();
        Livewire::test(Welcome::class)->call('next')->assertHasErrors('habit');
        $editor = Livewire::test(HabitEditor::class)->call('openGuidedHabit', 'water', true)
            ->assertSet('name', 'Tomar agua')->assertSet('timeOfDay', 'morning')->call('save')->assertHasNoErrors();
        $editor->set('name', 'Tomar agua')->set('timeOfDay', 'morning')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('habit_definitions', 1);
        $this->assertDatabaseCount('habit_completions', 0);
        $this->assertDatabaseCount('game_events', 0);
        $this->assertDatabaseCount('habit_actions', 0);
        $habit = HabitDefinition::firstOrFail();
        $this->assertNull($habit->goal_duration);
        $this->assertNull($habit->base_time);
        Livewire::test(Welcome::class)->call('next')->call('finish')->assertRedirect(route('home'));
        $this->assertFalse($this->experience()->required($user->id));
        $this->get('/')->assertOk();
        $this->get(route('habits'))->assertOk()->assertSee('Tomar agua');
        $state = $this->experience()->state($user->id);
        $this->assertSame($modules, $state['modules']);
        $this->assertArrayHasKey('completed', $state['events']);
        $this->assertArrayHasKey('v2_step_2', $state['events']);
        $this->assertArrayNotHasKey('first_real_action', $state['events']);
        UserActiveDay::countRecord($user->id);
        $first = $this->experience()->state($user->id)['events']['first_real_action'];
        $this->travel(1)->hours();
        UserActiveDay::countRecord($user->id);
        $this->assertSame($first, $this->experience()->state($user->id)['events']['first_real_action']);
    }

    public function test_repeated_creation_from_stale_editor_snapshot_reuses_the_server_owned_id(): void
    {
        $this->actingAs($user = User::factory()->create());
        Livewire::test(Welcome::class)->set('contexts', ['none'])->call('next')->set('need', 'care')->call('next');
        $first = Livewire::test(HabitEditor::class)->call('openGuidedHabit', 'water', true);
        $retry = Livewire::test(HabitEditor::class)->call('openGuidedHabit', 'water', true);
        $first->call('save')->assertHasNoErrors();
        $id = $this->experience()->state($user->id)['habit_id'];
        $retry->call('save')->assertHasNoErrors();
        $this->assertSame($id, $this->experience()->state($user->id)['habit_id']);
        $this->assertDatabaseCount('habit_definitions', 1);
        $this->assertDatabaseCount('habit_completions', 0);
    }

    public function test_context_choices_are_combinable_and_none_is_exclusive(): void
    {
        $this->actingAs(User::factory()->create());
        $guide = Livewire::test(Welcome::class)->set('contexts', ['work', 'study'])
            ->assertSet('contexts', ['work', 'study'])->set('contexts', ['work', 'study', 'none'])
            ->assertSet('contexts', ['none'])->set('contexts', ['none', 'care'])
            ->assertSet('contexts', ['care'])->set('contexts', ['invented'])->call('next')->assertHasErrors('contexts.0');
        $guide->set('contexts', ['none'])->call('next')->assertHasNoErrors()
            ->set('need', 'invented')->call('next')->assertHasErrors('need');
        $this->assertSame('other', $this->experience()->state(auth()->id())['context']);
    }

    public function test_all_needs_offer_three_ideas_context_only_orders_and_more_ideas_is_complete(): void
    {
        $choices = OnboardingChoices::class;
        foreach (array_keys($choices::NEEDS) as $need) {
            $neutral = $choices::suggestions($need, ['none']);
            $combined = $choices::suggestions($need, ['study', 'care', 'work']);
            $this->assertCount(3, $neutral);
            $this->assertEqualsCanonicalizing(array_keys($neutral), array_keys($combined));
            $this->assertCount(12, $choices::suggestions($need, [], true));
        }
        $this->assertSame('review', array_key_first($choices::suggestions('learn', ['study'])));
        $this->assertSame('pause', array_key_first($choices::suggestions('care', ['work'])));
    }

    public function test_legacy_progress_keeps_preferences_intention_habit_and_events(): void
    {
        $this->actingAs($user = User::factory()->create());
        $habit = HabitDefinition::create(['name' => 'Hábito anterior', 'time_of_day' => 'night']);
        $this->experience()->begin($user->id, true);
        $this->experience()->change($user->id, fn ($s) => array_replace($s, [
            'step' => 5, 'context' => 'both', 'intention' => 'Texto anterior', 'habit_id' => $habit->id,
            'modules' => ['habits', 'vehicles'], 'events' => ['step_2' => '2026-11-01T10:00:00Z'],
        ]));
        Livewire::test(Welcome::class)->assertSet('contexts', ['work', 'study'])->set('need', 'learn')->call('next')->assertHasNoErrors();
        $state = $this->experience()->state($user->id);
        $this->assertSame(3, $state['step']);
        $this->assertSame(5, $state['legacy_step']);
        $this->assertSame('Texto anterior', $state['intention']);
        $this->assertSame(['habits', 'vehicles'], $state['modules']);
        $this->assertSame('2026-11-01T10:00:00Z', $state['events']['step_2']);
        Livewire::test(Welcome::class)->assertSee('Hábito anterior')->call('next')->call('finish')->assertRedirect(route('home'));
        $this->assertDatabaseCount('habit_definitions', 1);
    }

    public function test_completed_legacy_guide_stays_completed_and_voluntary_guide_has_an_exit(): void
    {
        $this->actingAs($user = User::factory()->create());
        Livewire::test(Welcome::class)->assertSee('Volver a Mi día');
        $this->experience()->change($user->id, function ($s) {
            unset($s['guide_version']);

            return array_replace($s, ['context' => 'work', 'step' => 5, 'required' => true, 'completed_at' => now()->toIso8601String()]);
        });
        Livewire::test(Welcome::class)->assertSee('Ya tienes por dónde empezar')->assertSee('Volver a Mi día');
        $this->assertFalse($this->experience()->required($user->id));
    }

    public function test_reusing_and_editing_an_existing_habit_preserves_hidden_settings(): void
    {
        $this->actingAs($user = User::factory()->create());
        $habit = HabitDefinition::create(['name' => 'Ya lo hago', 'time_of_day' => 'night', 'base_time' => '20:30', 'goal_duration' => '15 min']);
        $action = $habit->action()->create(['action_key' => 'water', 'mode' => 'auto', 'config' => ['amount_ml' => 250], 'enabled' => true]);
        $original = $action->getAttributes();
        Livewire::test(Welcome::class)->set('contexts', ['none'])->call('next')->set('need', 'care')->call('next')
            ->set('existingHabit', (string) $habit->id)->call('useExistingHabit')->assertHasNoErrors();
        Livewire::test(HabitEditor::class)->call('openGuidedHabit')->set('name', 'Mi práctica diaria')
            ->set('baseTime', '01:00')->set('goalDuration', 'invalid hidden change')->call('save')->assertHasNoErrors();
        $habit->refresh();
        $this->assertSame('Mi práctica diaria', $habit->name);
        $this->assertSame('20:30', substr($habit->base_time, 0, 5));
        $this->assertSame('15 min', $habit->goal_duration);
        $this->assertEquals($original, $action->fresh()->getAttributes());
        $this->assertDatabaseCount('habit_definitions', 1);
        $this->assertArrayNotHasKey('habit_created', $this->experience()->state($user->id)['events']);
    }

    public function test_foreign_habits_and_invalid_suggestions_cannot_be_used(): void
    {
        $this->actingAs($other = User::factory()->create());
        $foreign = HabitDefinition::create(['name' => 'Privado', 'time_of_day' => 'morning']);
        $this->actingAs($user = User::factory()->create());
        Livewire::test(Welcome::class)->set('contexts', ['work'])->call('next')->set('need', 'organize')->call('next')
            ->set('existingHabit', (string) $foreign->id)->call('useExistingHabit')->assertHasErrors('existingHabit')
            ->set('suggestion', 'invented')->call('prepareHabit')->assertHasErrors('suggestion');
        $this->experience()->change($user->id, fn ($s) => array_replace($s, ['habit_id' => $foreign->id]));
        Livewire::test(HabitEditor::class)->call('openGuidedHabit')->set('name', 'No permitido')->call('save')->assertStatus(422);
        $this->assertSame('Privado', $foreign->fresh()->name);
    }

    public function test_custom_habit_validation_and_switching_ideas_do_not_overwrite_saved_habits(): void
    {
        $this->actingAs($user = User::factory()->create());
        Livewire::test(Welcome::class)->set('contexts', ['none'])->call('next')->set('need', 'unsure')->call('next');
        Livewire::test(HabitEditor::class)->call('openGuidedHabit', null, true)->call('save')->assertHasErrors('name')
            ->set('name', str_repeat('á', 121))->call('save')->assertHasErrors('name')->assertSet('name', str_repeat('á', 121))
            ->set('name', 'Mirar por la ventana')->call('save')->assertHasNoErrors();
        $firstId = $this->experience()->state($user->id)['habit_id'];
        Livewire::test(Welcome::class)->call('previous')->set('need', 'learn')->call('next');
        $this->assertSame($firstId, $this->experience()->state($user->id)['habit_id']);
        Livewire::test(HabitEditor::class)->call('openGuidedHabit', 'read', true)->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('habit_definitions', 2);
        $this->assertSame('Mirar por la ventana', HabitDefinition::findOrFail($firstId)->name);
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

    public function test_habit_can_be_skipped_and_the_guide_completes_without_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->experience()->begin($user->id, true);
        Livewire::test(Welcome::class)->call('skipHabit')->assertStatus(403);

        $this->experience()->change($user->id, fn ($state) => $state + ['contexts' => ['none'], 'need' => 'unsure']);
        $this->experience()->change($user->id, function ($state) {
            $state['step'] = 3;

            return $state;
        });
        Livewire::test(Welcome::class)
            ->call('skipHabit')
            ->assertSee('Cuando quieras, crea tu primer hábito desde Hábitos.')
            ->call('finish')
            ->assertRedirect(route('home'));

        $this->assertFalse($this->experience()->required($user->id));
        $this->assertArrayHasKey('skipped_habit', $this->experience()->state($user->id)['events']);
        $this->assertDatabaseCount('habit_definitions', 0);
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
