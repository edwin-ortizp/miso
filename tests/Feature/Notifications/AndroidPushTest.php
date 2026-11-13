<?php

namespace Tests\Feature\Notifications;

use App\Models\AndroidInstallation;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\Android\AndroidDispatcher;
use App\Services\Notifications\Android\FcmGateway;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AndroidPushTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    private string $outcome = 'sent';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $gateway = \Mockery::mock(FcmGateway::class);
        $gateway->shouldReceive('problem')->andReturn(null);
        $gateway->shouldReceive('send')->andReturnUsing(function ($token, $payload) {
            $this->sent[] = compact('token', 'payload');

            return $this->outcome;
        });
        $this->app->instance(FcmGateway::class, $gateway);
    }

    private function identity(): array
    {
        return ['installation_id' => (string) Str::uuid(), 'secret' => str_repeat('a', 64), 'token' => 'test-device-token'];
    }

    private function device(User $user): AndroidInstallation
    {
        return AndroidInstallation::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'secret_hash' => hash('sha256', str_repeat('a', 64)),
            'token' => 'test-device-token', 'token_hash' => hash('sha256', Str::uuid()), 'binding' => (string) Str::uuid(), 'active' => true, 'activated_at' => now()->subHour()]);
    }

    private function notice(User $user, array $extra = []): UserNotification
    {
        return UserNotification::withoutGlobalScopes()->create(array_replace(['user_id' => $user->id, 'type' => 'reminder', 'source_type' => 'custom',
            'source_id' => (string) Str::uuid(), 'occurrence_key' => (string) Str::uuid(), 'title' => 'Private title', 'body' => 'Private body', 'scheduled_for' => now()->subMinute()], $extra));
    }

    private function allowed(User $user): void
    {
        app(NotificationPreferences::class)->save($user->id, array_replace(NotificationPreferences::DEFAULTS, ['push_reminder' => true]));
    }

    public function test_requires_session_and_uses_authenticated_user(): void
    {
        $identity = $this->identity();
        $this->postJson(route('android.register'), $identity)->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('android.register'), $identity + ['user_id' => 99999])->assertOk();
        $device = AndroidInstallation::findOrFail($identity['installation_id']);
        $this->assertEquals($user->id, $device->user_id);
        $this->assertStringNotContainsString('test-device-token', $device->getRawOriginal('token'));
    }

    public function test_registration_idempotent_and_secret_required(): void
    {
        $user = User::factory()->create();
        $identity = $this->identity();
        $first = $this->actingAs($user)->postJson(route('android.register'), $identity)->assertOk()->json('binding');
        $this->postJson(route('android.register'), $identity)->assertJsonPath('binding', $first);
        $this->postJson(route('android.register'), array_replace($identity, ['secret' => str_repeat('b', 64)]))->assertForbidden();
        $this->assertDatabaseCount('android_installations', 1);
    }

    public function test_account_change_rotates_binding_and_logout_revokes(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $identity = $this->identity();
        $first = $this->actingAs($a)->postJson(route('android.register'), $identity)->json('binding');
        $second = $this->actingAs($b)->postJson(route('android.register'), $identity)->assertOk()->json('binding');
        $this->assertNotEquals($first, $second);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertFalse(AndroidInstallation::find($identity['installation_id'])->active);
    }

    public function test_delivery_is_unique_generic_and_independent_of_web_push(): void
    {
        $user = User::factory()->create();
        $this->allowed($user);
        $device = $this->device($user);
        $notice = $this->notice($user, ['push_sent_at' => now()]);
        $dispatcher = app(AndroidDispatcher::class);
        $dispatcher->run();
        $dispatcher->run();
        $this->assertCount(1, $this->sent);
        $this->assertSame(['version' => '1', 'notice_id' => $notice->id, 'binding' => $device->binding], $this->sent[0]['payload']);
        $this->assertDatabaseHas('android_deliveries', ['status' => 'sent', 'attempts' => 1]);
    }

    public function test_dry_run_has_no_writes_or_sends(): void
    {
        $u = User::factory()->create();
        $this->allowed($u);
        $this->device($u);
        $this->notice($u);
        $this->assertEquals(1, app(AndroidDispatcher::class)->run(true)['sent']);
        $this->assertDatabaseCount('android_deliveries', 0);
        $this->assertCount(0, $this->sent);
    }

    public function test_transient_retries_are_delayed_and_bounded(): void
    {
        $u = User::factory()->create();
        $this->allowed($u);
        $this->device($u);
        $this->notice($u);
        $this->outcome = 'transient';
        $d = app(AndroidDispatcher::class);
        $d->run();
        $d->run();
        $this->assertCount(1, $this->sent);
        $this->travel(1)->minutes();
        $d->run();
        $this->assertCount(2, $this->sent);
        $this->travel(5)->minutes();
        $d->run();
        $d->run();
        $this->assertCount(3, $this->sent);
        $this->assertDatabaseHas('android_deliveries', ['status' => 'failed', 'attempts' => 3]);
    }

    public function test_expired_token_revokes_installation(): void
    {
        $u = User::factory()->create();
        $this->allowed($u);
        $device = $this->device($u);
        $this->notice($u);
        $this->outcome = 'expired';
        app(AndroidDispatcher::class)->run();
        $this->assertFalse($device->fresh()->active);
    }

    public function test_preferences_activation_cancelled_read_and_future_are_respected(): void
    {
        $u = User::factory()->create();
        $this->allowed($u);
        $this->device($u);
        $this->notice($u, ['scheduled_for' => now()->subHours(2)]);
        $this->notice($u, ['cancelled_at' => now()]);
        $this->notice($u, ['read_at' => now()]);
        $this->notice($u, ['scheduled_for' => now()->addHour()]);
        $this->notice($u, ['type' => 'task_due']);
        app(AndroidDispatcher::class)->run();
        $this->assertCount(0, $this->sent);
    }

    public function test_other_account_cannot_open_or_revoke_notice(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->device($a);
        $notice = $this->notice($a);
        $this->actingAs($b)->get(route('android.notice', $notice))->assertNotFound();
        $this->actingAs($a)->get(route('android.notice', $notice))->assertRedirect();
    }

    public function test_disabled_installation_cannot_receive_and_token_can_rotate(): void
    {
        $u = User::factory()->create();
        $identity = $this->identity();
        $binding = $this->actingAs($u)->postJson(route('android.register'), $identity)->json('binding');
        $this->postJson(route('android.register'), array_replace($identity, ['token' => 'rotated-token']))->assertJsonPath('binding', $binding);
        $this->deleteJson(route('android.revoke'), $identity)->assertOk();
        $this->allowed($u);
        $this->notice($u, ['scheduled_for' => now()]);
        app(AndroidDispatcher::class)->run();
        $this->assertCount(0, $this->sent);
    }

    public function test_expired_session_preserves_intended_notice_and_foreign_secret_is_rejected(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $notice = $this->notice($a);
        $identity = $this->identity();
        $this->get(route('android.notice', $notice))->assertRedirect(route('login'))->assertSessionHas('url.intended', route('android.notice', $notice));
        $this->actingAs($a)->postJson(route('android.register'), $identity)->assertOk();
        $this->actingAs($b)->postJson(route('android.status'), array_replace($identity, ['secret' => str_repeat('b', 64)]))->assertForbidden();
        $this->actingAs($b)->postJson(route('android.status'), $identity)->assertJsonPath('active', false)->assertJsonPath('binding', null);
    }

    public function test_sent_rows_do_not_starve_later_notifications_under_small_batch_limit(): void
    {
        $u = User::factory()->create();
        $this->allowed($u);
        $this->device($u);
        $this->notice($u);
        $this->notice($u);
        $dispatcher = app(AndroidDispatcher::class);
        $dispatcher->run(false, 40, 1);
        $dispatcher->run(false, 40, 1);
        $this->assertCount(2, $this->sent);
    }

    public function test_native_dispatch_runs_without_vapid(): void
    {
        $u = User::factory()->create();
        $this->allowed($u);
        $this->device($u);
        $this->notice($u);
        config(['webpush.public_key' => null, 'webpush.private_key' => null]);
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertCount(1,$this->sent);
    }
}
