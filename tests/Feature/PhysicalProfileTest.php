<?php

namespace Tests\Feature;

use App\Livewire\Settings\SettingsPage;
use App\Livewire\Water\WaterDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhysicalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_require_physical_profile_data(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('onboarding'));

        $user = User::where('email', 'ana@example.test')->firstOrFail();

        $this->assertNull($user->current_weight_kg);
        $this->assertNull($user->height_cm);
        $this->assertNull($user->birth_date);
        $this->assertNull($user->activity_level);
        $this->assertNull($user->daily_water_goal);
    }

    public function test_user_can_update_their_optional_physical_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SettingsPage::class)
            ->set('fullName', 'Ana Gómez')
            ->set('currentWeightKg', 68.5)
            ->set('heightCm', 170)
            ->set('birthDate', '1995-04-10')
            ->set('lifeExpectancyYears', 88)
            ->set('activityLevel', 'moderate')
            ->set('dailyWaterGoal', 2800)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ana Gómez',
            'full_name' => 'Ana Gómez',
            'current_weight_kg' => 68.5,
            'height_cm' => 170,
            'life_expectancy_years' => 88,
            'activity_level' => 'moderate',
            'daily_water_goal' => 2800,
        ]);
        $this->assertSame('1995-04-10', $user->fresh()->birth_date?->toDateString());
    }

    public function test_physical_profile_values_are_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SettingsPage::class)
            ->set('currentWeightKg', 10)
            ->set('heightCm', 400)
            ->set('birthDate', now()->addDay()->toDateString())
            ->set('lifeExpectancyYears', 131)
            ->set('activityLevel', 'unknown')
            ->set('dailyWaterGoal', 200)
            ->call('updateProfile')
            ->assertHasErrors([
                'currentWeightKg',
                'heightCm',
                'birthDate',
                'lifeExpectancyYears',
                'activityLevel',
                'dailyWaterGoal',
            ]);
    }

    public function test_hydration_goal_prefers_manual_then_weight_suggestion_then_default(): void
    {
        $manualGoalUser = User::factory()->create(['current_weight_kg' => 70, 'daily_water_goal' => 3000]);
        $this->actingAs($manualGoalUser);
        Livewire::test(WaterDaily::class)->assertSet('dailyGoal', 3000);

        $suggestedGoalUser = User::factory()->create(['current_weight_kg' => 70]);
        $this->actingAs($suggestedGoalUser);
        Livewire::test(WaterDaily::class)->assertSet('dailyGoal', 2450);

        $defaultGoalUser = User::factory()->create();
        $this->actingAs($defaultGoalUser);
        Livewire::test(WaterDaily::class)->assertSet('dailyGoal', 2500);
    }
}
