<?php

namespace Tests\Feature\Engagement;

use App\Livewire\Home\WeeklyInsightsCard;
use App\Models\EnergyEntry;
use App\Models\ExerciseLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WeeklyInsight;
use App\Services\Game\MissionProposalValidator;
use App\Services\Insights\WeeklyInsights;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-070: resumen del domingo con hallazgos de tus datos. */
class WeeklyInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Domingo por la tarde.
        Carbon::setTestNow(Carbon::parse('2026-11-15 15:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** 8 semanas: días pares con ejercicio y energía 4; impares sin ejercicio y energía 2. */
    private function seedExerciseAndEnergy(int $days = 56): void
    {
        $end = CarbonImmutable::parse('2026-11-15');
        for ($i = 0; $i < $days; $i++) {
            $day = $end->subDays($i)->toDateString();
            $exercised = $i % 2 === 0;
            EnergyEntry::create(['date' => $day, 'level' => $exercised ? 4 : 2, 'time' => '18:00', 'timestamp' => $end->subDays($i)->timestamp]);
            if ($exercised) {
                ExerciseLog::create(['date' => $day, 'duration' => 30]);
            }
        }
    }

    private function service(): WeeklyInsights
    {
        return app(WeeklyInsights::class);
    }

    public function test_synthetic_data_produces_expected_finding(): void
    {
        $this->seedExerciseAndEnergy();

        $findings = $this->service()->forWeek($this->user, now())->findings;

        $this->assertSame('exercise_energy', $findings[0]['key']);
        $this->assertSame('+2,0', $findings[0]['value']);
        $this->assertStringContainsString('tiende a ser 2,0 puntos más alta', $findings[0]['text']);
    }

    public function test_few_data_produce_no_findings(): void
    {
        $this->seedExerciseAndEnergy(8);

        $this->assertSame([], $this->service()->forWeek($this->user, now())->findings);
    }

    public function test_computing_twice_keeps_one_row(): void
    {
        $this->seedExerciseAndEnergy();

        $this->service()->forWeek($this->user, now());
        $this->service()->forWeek($this->user, now());

        $this->assertSame(1, WeeklyInsight::count());
    }

    public function test_sunday_notification_is_created_once(): void
    {
        $this->seedExerciseAndEnergy();

        $this->artisan('miso:weekly-insights')->assertSuccessful();
        $this->artisan('miso:weekly-insights')->assertSuccessful();

        $notices = UserNotification::withoutGlobalScopes()->where('type', WeeklyInsights::TYPE)->get();
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('/statistics', $notices->first()->destination());
    }

    public function test_no_notification_without_findings(): void
    {
        $this->artisan('miso:weekly-insights');

        $this->assertSame(0, UserNotification::withoutGlobalScopes()->where('type', WeeklyInsights::TYPE)->count());
    }

    public function test_home_card_can_be_dismissed_and_statistics_still_shows_it(): void
    {
        $this->seedExerciseAndEnergy();

        Livewire::test(WeeklyInsightsCard::class)->assertSee('Lo que dicen tus datos')->call('dismiss');
        Livewire::test(WeeklyInsightsCard::class)->assertDontSee('Lo que dicen tus datos');
        $this->get(route('statistics'))->assertOk()->assertSee('tiende a ser');
    }

    public function test_card_is_hidden_midweek(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'America/Bogota'));
        $this->seedExerciseAndEnergy();

        Livewire::test(WeeklyInsightsCard::class)->assertDontSee('Lo que dicen tus datos');
    }

    public function test_texts_carry_no_guilt_nor_causality(): void
    {
        $this->seedExerciseAndEnergy();

        foreach ($this->service()->forWeek($this->user, now())->findings as $finding) {
            $this->assertNull(MissionProposalValidator::penaltyWord($finding['text']));
            $this->assertStringNotContainsString('porque', $finding['text']);
            $this->assertStringNotContainsString('causa', $finding['text']);
        }
    }
}
