<?php

namespace Tests\Feature\Notifications;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\Push\GuardianNudgePlanner;
use App\Services\Notifications\Push\PushGateway;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** CHG-068: entrega push de la tabla `notifications`, una sola vez por notificación. */
class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FakePushGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 15:00:00', 'America/Bogota'));
        $this->gateway = new FakePushGateway;
        $this->app->instance(PushGateway::class, $this->gateway);
        $this->user = User::factory()->create();
        PushSubscription::create(['user_id' => $this->user->id, 'endpoint' => 'https://push.example/1', 'public_key' => 'k', 'auth_token' => 'a']);
        app(NotificationPreferences::class)->save($this->user->id, array_replace(NotificationPreferences::DEFAULTS, ['guardian_nudges' => true]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function notice(array $overrides = []): UserNotification
    {
        return UserNotification::withoutGlobalScopes()->create(array_replace([
            'user_id' => $this->user->id, 'source_type' => GuardianNudgePlanner::SOURCE,
            'source_id' => GuardianNudgePlanner::sourceId('water'), 'type' => GuardianNudgePlanner::TYPE,
            'occurrence_key' => (string) Str::uuid(), 'title' => 'Gotin · MiSo', 'body' => 'Te falta un vaso.',
            'scheduled_for' => now('UTC')->subMinute(),
        ], $overrides));
    }

    public function test_sends_once_and_marks_push_sent_at(): void
    {
        $notice = $this->notice();

        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->artisan('notifications:dispatch')->assertSuccessful();

        $this->assertCount(1, $this->gateway->sent);
        $this->assertNotNull($notice->fresh()->push_sent_at);
        $this->assertStringEndsWith('via=push', $this->gateway->sent[0]['payload']['url']);
    }

    public function test_already_claimed_notification_is_not_sent_again(): void
    {
        $this->notice(['push_sent_at' => now('UTC')]);

        $this->artisan('notifications:dispatch');

        $this->assertCount(0, $this->gateway->sent);
    }

    public function test_skips_disabled_types_cancelled_future_read_and_old(): void
    {
        $this->notice(['type' => 'task_due']);
        $this->notice(['cancelled_at' => now('UTC')]);
        $this->notice(['read_at' => now('UTC')]);
        $this->notice(['scheduled_for' => now('UTC')->addHour()]);
        $this->notice(['scheduled_for' => now('UTC')->subDays(2)]);

        $this->artisan('notifications:dispatch');

        $this->assertCount(0, $this->gateway->sent);
    }

    public function test_enabled_other_type_is_sent(): void
    {
        app(NotificationPreferences::class)->save($this->user->id, array_replace(NotificationPreferences::DEFAULTS, ['push_task_due' => true]));
        $this->notice(['type' => 'task_due']);

        $this->artisan('notifications:dispatch');

        $this->assertCount(1, $this->gateway->sent);
    }

    public function test_expired_subscription_is_removed(): void
    {
        $this->gateway->status = PushGateway::EXPIRED;
        $this->notice();

        $this->artisan('notifications:dispatch');

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_dry_run_neither_sends_nor_marks(): void
    {
        $notice = $this->notice();

        $this->artisan('notifications:dispatch', ['--dry-run' => true])->assertSuccessful();

        $this->assertCount(0, $this->gateway->sent);
        $this->assertNull($notice->fresh()->push_sent_at);
    }

    public function test_missing_configuration_sends_nothing_and_does_not_fail(): void
    {
        $this->gateway->problem = 'Faltan claves VAPID.';
        $notice = $this->notice();

        $this->artisan('notifications:dispatch')->assertSuccessful();

        $this->assertCount(0, $this->gateway->sent);
        $this->assertNull($notice->fresh()->push_sent_at);
    }

    /** CHG-070: el aviso de la franja Noche abre el cierre del día con su propio canal. */
    public function test_night_nudge_opens_day_close_without_push_channel(): void
    {
        $this->notice(['occurrence_key' => '2026-11-11:night']);
        $this->notice(['occurrence_key' => '2026-11-11:morning', 'source_id' => GuardianNudgePlanner::sourceId('habits')]);

        $this->artisan('notifications:dispatch')->assertSuccessful();

        $urls = collect($this->gateway->sent)->pluck('payload.url');
        $this->assertTrue($urls->contains(route('day.close', ['via' => 'close'])));
        $this->assertTrue($urls->contains(route('habits').'?via=push'));
    }
}
