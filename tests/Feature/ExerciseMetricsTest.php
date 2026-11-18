<?php

namespace Tests\Feature;

use App\Actions\LogExercise;
use App\Livewire\Exercise\ExerciseDaily;
use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExerciseMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_repetitions_can_be_recorded_as_total_or_series(): void
    {
        $this->actingAs(User::factory()->create());
        $type = ExerciseType::create(['name' => 'Flexiones', 'calories_per_hour' => 350]);

        Livewire::test(ExerciseDaily::class)
            ->call('openQuickExercise', $type->id)
            ->assertSee('Repeticiones totales')
            ->set('total_reps', 30)
            ->call('save')
            ->assertHasNoErrors();

        $log = ExerciseLog::sole();
        $this->assertEquals(30, $log->totalRepetitions());
        $this->assertNull($log->duration);
        $this->assertNull($log->calories);

        Livewire::test(ExerciseDaily::class)
            ->call('openForm', $log->id)
            ->call('setSeriesMode', true)
            ->set('sets', 3)
            ->set('reps', 10)
            ->call('save')
            ->assertHasNoErrors();

        $log->refresh();
        $this->assertNull($log->total_reps);
        $this->assertEquals(30, $log->totalRepetitions());
    }

    public function test_speed_and_profile_weight_refine_calories_and_edits_preserve_manual_values(): void
    {
        $this->actingAs(User::factory()->create(['current_weight_kg' => 70]));
        $type = ExerciseType::create(['name' => 'Trotar', 'calories_per_hour' => 500]);

        $log = LogExercise::handle($type, '2026-11-01', ['duration' => 28, 'distance' => 5]);
        $this->assertSame(336, $log->paceSecondsPerKm());
        $this->assertSame(343, $log->calories);
        $this->assertSame('speed_met', $log->calories_source);

        LogExercise::handle($type, '2026-11-02', ['distance' => null], $log);
        $this->assertNull($log->paceSecondsPerKm());
        $this->assertSame(233, $log->calories);
        $this->assertSame('catalog_rate', $log->calories_source);

        LogExercise::handle($type, '2026-11-02', ['calories' => 0], $log);
        LogExercise::handle($type, '2026-11-02', ['duration' => 40], $log);
        $log->refresh();
        $this->assertSame(0, $log->calories);
        $this->assertSame('manual', $log->calories_source);
        $this->assertSame('2026-11-01', $log->date->toDateString());
    }

    public function test_legacy_details_and_unknown_calories_survive_editing(): void
    {
        $this->actingAs(User::factory()->create());
        $type = ExerciseType::create(['name' => 'Yoga', 'calories_per_hour' => 250]);
        $log = ExerciseLog::create([
            'exercise_type_id' => $type->id,
            'date' => '2026-11-01',
            'weight' => 12.5,
            'steps' => 40,
            'calories' => 60,
        ]);

        Livewire::test(ExerciseDaily::class)
            ->call('openForm', $log->id)
            ->assertSee('Peso usado (kg)')
            ->set('notes', 'Sesión anterior')
            ->call('save')
            ->assertHasNoErrors();

        $log->refresh();
        $this->assertEquals(12.5, $log->weight);
        $this->assertSame(40, $log->steps);
        $this->assertSame(60, $log->calories);
        $this->assertNull($log->calories_source);
        $this->assertSame('2026-11-01', $log->date->toDateString());
    }
}
