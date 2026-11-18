<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Exercise\LogExerciseTool;
use App\Mcp\Tools\Water\LogWaterIntakeTool;
use App\Models\DrinkLog;
use App\Models\ExerciseLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifeTrackerExerciseWaterMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_exercise_tool_calculates_calories_and_steps_from_duration(): void
    {
        $user = User::factory()->create();
        $type = $user->exerciseTypes()->create([
            'name' => 'Correr', 'calories_per_hour' => 600, 'steps_equivalent' => 6000,
        ]);

        LifeTrackerServer::actingAs($user)
            ->tool(LogExerciseTool::class, ['exercise_type_name' => 'Correr', 'duration' => 30])
            ->assertOk()
            ->assertSee('300')
            ->assertSee('3000');

        $log = ExerciseLog::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($type->id, $log->exercise_type_id);
        $this->assertSame(300, $log->calories);
        $this->assertSame(3000, $log->steps);
    }

    public function test_log_exercise_tool_respects_a_manual_calories_override(): void
    {
        $user = User::factory()->create();
        $user->exerciseTypes()->create(['name' => 'Pesas', 'calories_per_hour' => 400, 'steps_equivalent' => 0]);

        LifeTrackerServer::actingAs($user)
            ->tool(LogExerciseTool::class, ['exercise_type_name' => 'Pesas', 'duration' => 45, 'calories' => 250])
            ->assertOk();

        $log = ExerciseLog::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(250, $log->calories);
    }

    public function test_log_exercise_tool_preserves_total_repetitions_without_duration(): void
    {
        $user = User::factory()->create();
        $user->exerciseTypes()->create(['name' => 'Sentadillas', 'calories_per_hour' => 400]);

        LifeTrackerServer::actingAs($user)
            ->tool(LogExerciseTool::class, ['exercise_type_name' => 'Sentadillas', 'total_reps' => 30])
            ->assertOk();

        $log = ExerciseLog::where('user_id', $user->id)->sole();
        $this->assertSame(30, $log->totalRepetitions());
        $this->assertNull($log->calories);
        $this->assertNull($log->sets);
    }

    public function test_log_water_intake_tool_records_intake_and_reports_progress(): void
    {
        $user = User::factory()->create(['daily_water_goal' => 2000]);
        $user->drinkTypes()->create(['name' => 'Agua', 'hydration_factor' => 1.00]);

        LifeTrackerServer::actingAs($user)
            ->tool(LogWaterIntakeTool::class, ['amount_ml' => 500])
            ->assertOk()
            ->assertSee('500 ml')
            ->assertSee('25%');

        $log = DrinkLog::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(500, $log->amount);
        $this->assertSame(500, $log->hydration_value);
    }

    public function test_log_water_intake_tool_applies_the_hydration_factor_for_non_water_drinks(): void
    {
        $user = User::factory()->create(['daily_water_goal' => 2000]);
        $user->drinkTypes()->create(['name' => 'Café', 'hydration_factor' => 0.50]);

        LifeTrackerServer::actingAs($user)
            ->tool(LogWaterIntakeTool::class, ['drink_type_name' => 'Café', 'amount_ml' => 200])
            ->assertOk();

        $log = DrinkLog::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(200, $log->amount);
        $this->assertSame(100, $log->hydration_value);
    }
}
