<?php

namespace Tests\Feature\Habit;

use App\Livewire\Habit\HabitTracker;
use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Models\HabitAction;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\User;
use App\Services\HabitGamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class HabitActionPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_prompt_mode_completes_the_habit_but_waits_for_the_answer(): void
    {
        [$habit] = $this->exerciseHabit();

        $feedback = app(HabitGamificationService::class)->toggle($habit->id, '2026-07-13');

        $this->assertTrue($feedback['requiresInput']);
        $this->assertSame(
            ['exercise_type_id', 'total_reps', 'sets', 'reps', 'duration', 'distance', 'weight', 'calories', 'steps', 'notes'],
            array_column($feedback['promptFields'], 'key'),
        );
        $this->assertTrue(HabitCompletion::sole()->completed);
        $this->assertSame(0, ExerciseLog::count());
    }

    public function test_answering_the_prompt_creates_the_record_and_links_it(): void
    {
        [$habit, $type] = $this->exerciseHabit();
        $gamification = app(HabitGamificationService::class);
        $gamification->toggle($habit->id, '2026-07-13');

        $result = $gamification->resolveAction($habit->id, '2026-07-13', [
            'exercise_type_id' => $type->id,
            'duration' => 45,
        ]);

        $log = ExerciseLog::sole();
        $this->assertSame(45, (int) $log->duration);
        $this->assertSame($type->id, $log->exercise_type_id);
        // 45 min a 600 kcal/h.
        $this->assertSame(450, (int) $log->calories);
        $this->assertStringContainsString('Correr', $result['actionResult']);
        $this->assertSame('exercise-log', HabitCompletion::sole()->actionable_type);
    }

    public function test_skipping_the_prompt_leaves_the_habit_completed_without_a_record(): void
    {
        [$habit] = $this->exerciseHabit();

        // «Omitir» es simplemente no llamar a resolveAction.
        app(HabitGamificationService::class)->toggle($habit->id, '2026-07-13');

        $this->assertTrue(HabitCompletion::sole()->completed);
        $this->assertNull(HabitCompletion::sole()->actionable_type);
        $this->assertSame(0, ExerciseLog::count());
    }

    public function test_the_answer_is_validated_against_the_declared_fields(): void
    {
        [$habit] = $this->exerciseHabit();
        app(HabitGamificationService::class)->toggle($habit->id, '2026-07-13');

        $this->expectException(ValidationException::class);

        app(HabitGamificationService::class)->resolveAction($habit->id, '2026-07-13', ['duration' => 30]);
    }

    public function test_the_tracker_opens_the_dialog_and_registers_the_answer(): void
    {
        [$habit, $type] = $this->exerciseHabit();

        Livewire::test(HabitTracker::class)
            ->call('toggleHabit', $habit->id)
            ->assertSet('showHabitActionPrompt', true)
            ->assertSet('promptingHabitName', 'Hacer ejercicio')
            ->set('habitActionInput.exercise_type_id', $type->id)
            ->set('habitActionInput.duration', 20)
            ->call('confirmHabitAction')
            ->assertSet('showHabitActionPrompt', false)
            ->assertDispatched('habit-action-registered');

        $this->assertSame(20, (int) ExerciseLog::sole()->duration);
    }

    public function test_the_tracker_lets_the_user_skip_without_losing_the_completion(): void
    {
        [$habit] = $this->exerciseHabit();

        Livewire::test(HabitTracker::class)
            ->call('toggleHabit', $habit->id)
            ->assertSet('showHabitActionPrompt', true)
            ->call('skipHabitAction')
            ->assertSet('showHabitActionPrompt', false);

        $this->assertTrue(HabitCompletion::sole()->completed);
        $this->assertSame(0, ExerciseLog::count());
    }

    public function test_a_habit_without_an_action_never_opens_the_dialog(): void
    {
        $habit = HabitDefinition::create(['name' => 'Estirar', 'time_of_day' => 'morning']);

        Livewire::test(HabitTracker::class)
            ->call('toggleHabit', $habit->id)
            ->assertSet('showHabitActionPrompt', false);
    }

    public function test_strength_prompt_uses_configured_type_and_is_safe_to_confirm_twice(): void
    {
        [$habit, $type] = $this->exerciseHabit();
        $type->update(['name' => 'Flexiones']);
        $habit->action()->firstOrFail()->update(['config' => [
            'exercise_type_id' => $type->id,
            'duration' => 30,
        ]]);

        Livewire::test(HabitTracker::class)
            ->call('toggleHabit', $habit->id)
            ->assertSet('habitActionInput.exercise_type_id', $type->id)
            ->assertSet('habitActionInput.duration', null)
            ->assertSee('Repeticiones totales')
            ->set('habitActionInput.total_reps', 30)
            ->call('confirmHabitAction')
            ->assertHasNoErrors();

        $date = HabitCompletion::sole()->date->toDateString();
        $this->assertNull(app(HabitGamificationService::class)->resolveAction($habit->id, $date, ['total_reps' => 40])['actionResult']);
        $this->assertSame(30, (int) ExerciseLog::sole()->total_reps);
        $this->assertNull(ExerciseLog::sole()->calories);
    }

    /** @return array{0: HabitDefinition, 1: ExerciseType} */
    private function exerciseHabit(): array
    {
        $type = ExerciseType::create(['name' => 'Correr', 'calories_per_hour' => 600, 'steps_equivalent' => 0]);
        $habit = HabitDefinition::create(['name' => 'Hacer ejercicio', 'time_of_day' => 'morning']);

        HabitAction::create([
            'habit_id' => $habit->id,
            'action_key' => 'exercise.log',
            'mode' => HabitAction::MODE_PROMPT,
            'config' => [],
        ]);

        return [$habit->fresh(), $type];
    }
}
