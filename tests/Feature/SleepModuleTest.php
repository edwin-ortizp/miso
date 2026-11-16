<?php

namespace Tests\Feature;

use App\Actions\LogSleep;
use App\Livewire\Health\Sleep;
use App\Models\SleepLog;
use App\Models\User;
use App\Support\SleepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SleepModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function night(User $user, string $slept, string $woke, array $extra = []): SleepLog
    {
        return $user->sleepLogs()->create(['slept_at' => $slept, 'woke_at' => $woke, ...$extra]);
    }

    public function test_page_shows_notice_and_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('health.sleep'))
            ->assertOk()->assertSee('No es un dispositivo médico')->assertSee('Aún no tienes registros de sueño');
    }

    public function test_saves_a_night_across_midnight_and_computes_its_duration(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Sleep::class)
            ->call('openForm')
            ->set('slept_at', '2026-11-15T23:00')->set('woke_at', '2026-11-16T06:30')
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false)
            ->assertSee('7 h 30 min')->assertSee('Faltaron 30 min para tu meta');

        $this->assertSame(450, SleepLog::firstOrFail()->durationMinutes());
    }

    public function test_the_form_opens_prefilled_with_the_goal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Sleep::class)->call('openForm')
            ->assertSet('woke_at', '2026-11-16T09:00')->assertSet('slept_at', '2026-11-16T01:00');
    }

    public function test_rejects_impossible_ranges_and_overlaps(): void
    {
        $user = User::factory()->create();
        $this->night($user, '2026-11-15 23:00', '2026-11-16 06:30');

        $component = Livewire::actingAs($user)->test(Sleep::class)->call('openForm');
        $component->set('slept_at', '2026-11-16T07:00')->set('woke_at', '2026-11-16T06:00')->call('save')->assertHasErrors(['woke_at']);
        $component->set('slept_at', '2026-11-16T07:00')->set('woke_at', '2026-11-16T07:05')->call('save')->assertHasErrors(['woke_at']);
        $component->set('slept_at', '2026-11-14T07:00')->set('woke_at', '2026-11-16T08:00')->call('save')->assertHasErrors(['woke_at']);
        $component->set('slept_at', '2026-11-16T05:00')->set('woke_at', '2026-11-16T08:00')->call('save')->assertHasErrors(['slept_at']);
        $component->set('slept_at', '')->call('save')->assertHasErrors(['slept_at']);

        $this->assertSame(1, SleepLog::count());
    }

    public function test_edit_does_not_overlap_with_itself_and_updates_quality(): void
    {
        $user = User::factory()->create();
        $log = $this->night($user, '2026-11-15 23:00', '2026-11-16 06:30');

        Livewire::actingAs($user)->test(Sleep::class)
            ->call('edit', $log->id)->assertSet('slept_at', '2026-11-15T23:00')
            ->set('quality', '4')->set('woke_at', '2026-11-16T07:00')->call('save')->assertHasNoErrors();

        $this->assertSame(4, $log->fresh()->quality);
        $this->assertSame(480, $log->fresh()->durationMinutes());
    }

    public function test_goal_can_be_changed_per_person_and_drives_the_message(): void
    {
        $user = User::factory()->create();
        $this->night($user, '2026-11-15 23:30', '2026-11-16 06:30');

        $component = Livewire::actingAs($user)->test(Sleep::class)
            ->assertSee('Faltaron 1 h para tu meta')
            ->call('openGoal')->assertSet('goalHours', '8')
            ->set('goalHours', '3')->call('saveGoal')->assertHasErrors(['goalHours'])
            ->set('goalHours', '15')->call('saveGoal')->assertHasErrors(['goalHours'])
            ->set('goalHours', '7')->call('saveGoal')->assertHasNoErrors()
            ->assertSee('Alcanzaste tu meta');

        $this->assertEquals(7.0, $user->fresh()->daily_sleep_hours);
        $this->assertSame(420, SleepProgress::goalMinutes($user->fresh()));
    }

    public function test_week_summary_has_average_and_nights_meeting_the_goal(): void
    {
        $user = User::factory()->create();
        $this->night($user, '2026-11-15 23:00', '2026-11-16 07:00');
        $this->night($user, '2026-11-14 01:00', '2026-11-14 06:00');
        $this->night($user, '2026-10-01 23:00', '2026-10-02 07:00');
        $this->actingAs($user);

        $summary = SleepProgress::summary(SleepProgress::recent(7), 480);

        $this->assertSame(2, $summary['nights']);
        $this->assertSame(390, $summary['average_minutes']);
        $this->assertSame(1, $summary['meeting_goal']);

        Livewire::actingAs($user)->test(Sleep::class)->assertSee('Promedio 6 h 30 min')->assertSee('1 de 2 registros cumplieron tu meta');
    }

    public function test_goal_messages_are_neutral(): void
    {
        $this->assertSame('Alcanzaste tu meta', SleepProgress::goalMessage(480, 480));
        $this->assertSame('Superaste tu meta por 30 min', SleepProgress::goalMessage(510, 480));
        $this->assertSame('Faltaron 1 h 15 min para tu meta', SleepProgress::goalMessage(405, 480));
    }

    public function test_identical_record_is_not_duplicated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = LogSleep::validate(['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 06:30']);

        $first = LogSleep::handle($data);
        $again = LogSleep::handle(LogSleep::validate(['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 06:30']));

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, SleepLog::count());
    }

    public function test_validation_rejects_quality_outside_1_to_5(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(ValidationException::class);
        LogSleep::validate(['slept_at' => '2026-11-15 23:00', 'woke_at' => '2026-11-16 06:30', 'quality' => 9]);
    }

    public function test_deletes_and_never_touches_another_users_records(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $log = $this->night($owner, '2026-11-15 23:00', '2026-11-16 06:30');

        Livewire::actingAs($other)->test(Sleep::class)
            ->assertDontSee('7 h 30 min')->call('delete', $log->id)->call('edit', $log->id)->assertSet('showForm', false);
        $this->assertSame(1, SleepLog::withoutGlobalScopes()->count());

        Livewire::actingAs($owner)->test(Sleep::class)->call('delete', $log->id);
        $this->assertSame(0, SleepLog::withoutGlobalScopes()->count());
    }

    public function test_period_filter_limits_the_history(): void
    {
        $user = User::factory()->create();
        $this->night($user, '2026-09-01 23:40', '2026-09-02 06:00');
        $this->night($user, '2026-11-15 23:00', '2026-11-16 07:00');

        Livewire::actingAs($user)->test(Sleep::class)
            ->assertSee('6 h 20 min')->call('applyFilters', '7')->assertDontSee('6 h 20 min')->assertSee('8 h');
    }
}
