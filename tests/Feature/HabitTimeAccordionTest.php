<?php

namespace Tests\Feature;

use App\Livewire\Habit\HabitTracker;
use App\Models\HabitDefinition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HabitTimeAccordionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_application_uses_colombia_timezone(): void
    {
        $this->assertSame('America/Bogota', config('app.timezone'));
    }

    public function test_current_date_opens_the_morning_section_before_noon(): void
    {
        $this->assertInitiallyOpenAt('2026-07-13 07:00:00', ['morning', 'anytime']);
    }

    public function test_current_date_opens_the_afternoon_section_from_noon_to_six(): void
    {
        $this->assertInitiallyOpenAt('2026-07-13 12:00:00', ['afternoon', 'anytime']);
    }

    public function test_current_date_opens_the_night_section_after_six_pm_and_before_six_am(): void
    {
        $this->assertInitiallyOpenAt('2026-07-13 18:00:00', ['night', 'anytime']);
        $this->assertInitiallyOpenAt('2026-07-13 05:59:00', ['night', 'anytime']);
    }

    public function test_past_and_future_dates_open_every_section(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 13:00:00', 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->createHabitInEverySection();

        foreach (['2026-07-12', '2026-07-14'] as $date) {
            Livewire::withQueryParams(['date' => $date])
                ->test(HabitTracker::class)
                ->assertViewHas('initiallyOpenTimeOfDay', ['morning', 'afternoon', 'night', 'anytime']);
        }
    }

    public function test_toggling_a_habit_keeps_the_context_rail_and_active_module(): void
    {
        $this->actingAs(User::factory()->create());
        $habit = HabitDefinition::create(['name' => 'Leer', 'time_of_day' => 'morning']);

        Livewire::test(HabitTracker::class)
            ->assertSeeHtml('md-module-workspace--rail')
            ->call('toggleHabit', $habit->id)
            ->assertSeeHtml('md-module-workspace--rail')
            ->assertSeeHtml('aria-pressed="true"');
    }

    private function assertInitiallyOpenAt(string $now, array $expected): void
    {
        Carbon::setTestNow(Carbon::parse($now, 'America/Bogota'));
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->createHabitInEverySection();

        Livewire::withQueryParams(['date' => '2026-07-13'])
            ->test(HabitTracker::class)
            ->assertViewHas('initiallyOpenTimeOfDay', $expected);
    }

    private function createHabitInEverySection(): void
    {
        foreach (['morning', 'afternoon', 'night', 'anytime'] as $timeOfDay) {
            HabitDefinition::create([
                'name' => "Habit {$timeOfDay}",
                'time_of_day' => $timeOfDay,
            ]);
        }
    }
}
