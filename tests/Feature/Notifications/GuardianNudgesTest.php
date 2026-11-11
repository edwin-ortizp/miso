<?php

namespace Tests\Feature\Notifications;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Game\MissionProposalValidator;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\Push\GuardianNudgePlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-067: avisos del guardián como notificaciones `guardian_nudge`, idempotentes por franja. */
class GuardianNudgesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 15:00:00', 'America/Bogota'));
        config(['game.nudges.modules' => ['water']]);
        $this->user = User::factory()->create();
        PushSubscription::create(['user_id' => $this->user->id, 'endpoint' => 'https://push.example/1', 'public_key' => 'k', 'auth_token' => 'a']);
        $this->prefs(['guardian_nudges' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function prefs(array $changes): void
    {
        $service = app(NotificationPreferences::class);
        $service->save($this->user->id, array_replace($service->get($this->user->id), $changes));
    }

    private function nudges()
    {
        return UserNotification::withoutGlobalScopes()->where('type', GuardianNudgePlanner::TYPE)->get();
    }

    public function test_pending_water_in_active_slot_creates_one_notification(): void
    {
        $this->artisan('miso:guardian-nudges')->assertSuccessful();

        $nudges = $this->nudges();
        $this->assertCount(1, $nudges);
        $this->assertSame('2026-11-11:afternoon', $nudges->first()->occurrence_key);
        $this->assertSame(GuardianNudgePlanner::SOURCE, $nudges->first()->source_type);
        $this->assertStringContainsString('/water/daily', $nudges->first()->destination());
    }

    public function test_running_twice_creates_a_single_notification(): void
    {
        $this->artisan('miso:guardian-nudges')->assertSuccessful();
        $this->artisan('miso:guardian-nudges')->assertSuccessful();

        $this->assertCount(1, $this->nudges());
    }

    public function test_nothing_pending_creates_nothing(): void
    {
        config(['game.nudges.modules' => ['habits']]);

        $this->artisan('miso:guardian-nudges')->assertSuccessful();

        $this->assertCount(0, $this->nudges());
    }

    public function test_disabled_slot_or_outside_slot_creates_nothing(): void
    {
        $this->prefs(['nudge_afternoon' => false]);
        $this->artisan('miso:guardian-nudges');
        $this->assertCount(0, $this->nudges());

        $this->prefs(['nudge_afternoon' => true]);
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', 'America/Bogota'));
        $this->artisan('miso:guardian-nudges');
        $this->assertCount(0, $this->nudges());
    }

    public function test_daily_cap_is_respected(): void
    {
        config(['game.nudges.daily_cap' => 1]);
        $this->prefs(['nudge_morning' => true, 'nudge_afternoon' => true]);

        Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'America/Bogota'));
        $this->artisan('miso:guardian-nudges');
        Carbon::setTestNow(Carbon::parse('2026-11-11 15:00:00', 'America/Bogota'));
        $this->artisan('miso:guardian-nudges');

        $this->assertCount(1, $this->nudges());
    }

    public function test_without_device_or_preference_creates_nothing(): void
    {
        $this->prefs(['guardian_nudges' => false]);
        $this->artisan('miso:guardian-nudges');
        $this->assertCount(0, $this->nudges());

        $this->prefs(['guardian_nudges' => true]);
        PushSubscription::query()->delete();
        $this->artisan('miso:guardian-nudges');
        $this->assertCount(0, $this->nudges());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('miso:guardian-nudges', ['--dry-run' => true])->assertSuccessful();

        $this->assertCount(0, $this->nudges());
    }

    public function test_message_is_short_and_kind(): void
    {
        $this->artisan('miso:guardian-nudges');
        $body = $this->nudges()->first()->body;

        $this->assertLessThanOrEqual(120, mb_strlen($body));
        $this->assertNull(MissionProposalValidator::penaltyWord($body));
    }
}
