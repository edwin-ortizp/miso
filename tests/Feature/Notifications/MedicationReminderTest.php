<?php

namespace Tests\Feature\Notifications;

use App\Actions\LogMedicationIntake;
use App\Actions\SaveMedication;
use App\Livewire\Notifications\NotificationBell;
use App\Models\Medication;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\GenerateNotifications;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\Push\NotificationDispatcher;
use App\Services\Notifications\Push\PushGateway;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-134: aviso `medication_due` a la hora de cada toma, sin cron nuevo. */
class MedicationReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FakePushGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:30:00', 'America/Bogota'));
        $this->gateway = new FakePushGateway;
        $this->app->instance(PushGateway::class, $this->gateway);
        $this->user = User::factory()->create();
        PushSubscription::create(['user_id' => $this->user->id, 'endpoint' => 'https://push.example/1', 'public_key' => 'k', 'auth_token' => 'a']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function medication(?User $user = null, array $overrides = []): Medication
    {
        return ($user ?? $this->user)->medications()->create([
            'name' => 'Losartán', 'dose_amount' => 50, 'dose_unit' => 'mg', 'frequency_type' => 'daily',
            'times' => ['08:00', '20:00'], 'starts_on' => '2026-11-01', 'is_active' => true, ...$overrides,
        ]);
    }

    private function notices(?User $user = null)
    {
        return UserNotification::withoutGlobalScopes()->where('user_id', ($user ?? $this->user)->id)->where('type', 'medication_due');
    }

    public function test_creates_one_notice_for_each_dose_whose_time_has_come(): void
    {
        $this->medication();

        $created = app(GenerateNotifications::class)->run($this->user)['created'];

        $this->assertSame(1, $created);
        $notice = $this->notices()->firstOrFail();
        $this->assertSame('Es hora de tu Losartán 50 mg', $notice->title);
        $this->assertSame('Toma programada a las 08:00.', $notice->body);
        $this->assertSame('2026-11-16:08:00', $notice->occurrence_key);
        $this->assertSame('2026-11-16 13:00', $notice->scheduled_for->format('Y-m-d H:i'));
        $this->assertSame(route('health.medications'), $notice->destination());
    }

    public function test_no_notice_is_created_when_the_user_turned_medications_off(): void
    {
        $this->medication();
        app(\App\Services\Experience\PersonalExperience::class)->saveFeatures($this->user->id, ['health.medications']);

        $created = app(GenerateNotifications::class)->run($this->user)['created'];

        $this->assertSame(0, $created);
        $this->assertSame(0, $this->notices()->count());
        $this->assertSame(1, Medication::withoutGlobalScopes()->where('user_id', $this->user->id)->count());
    }

    public function test_running_twice_keeps_a_single_notice(): void
    {
        $this->medication();

        $this->artisan('notifications:generate')->assertSuccessful();
        $this->artisan('notifications:generate')->assertSuccessful();

        $this->assertSame(1, $this->notices()->count());
    }

    public function test_it_is_not_held_back_by_the_nine_o_clock_gate_or_the_preferences(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 06:10:00', 'America/Bogota'));
        $this->medication(null, ['times' => ['06:00']]);

        // Todas las preferencias de creación de los demás avisos están apagadas por defecto.
        $this->assertFalse(app(NotificationPreferences::class)->get($this->user->id)['tasks']);
        app(GenerateNotifications::class)->run($this->user);

        $this->assertSame(1, $this->notices()->count());
    }

    public function test_a_registered_dose_gets_no_notice_and_cancels_an_existing_one(): void
    {
        $med = $this->medication();
        app(GenerateNotifications::class)->run($this->user);
        $this->assertNull($this->notices()->firstOrFail()->cancelled_at);

        $this->actingAs($this->user);
        LogMedicationIntake::take($med, Carbon::parse('2026-11-16 08:00', 'America/Bogota'));

        $this->assertNotNull($this->notices()->firstOrFail()->cancelled_at);
        app(GenerateNotifications::class)->run($this->user);
        $this->assertSame(1, $this->notices()->count());
        $this->assertNotNull($this->notices()->firstOrFail()->cancelled_at);
    }

    public function test_finishing_or_changing_the_program_cancels_todays_notice(): void
    {
        $med = $this->medication();
        app(GenerateNotifications::class)->run($this->user);

        $this->actingAs($this->user);
        SaveMedication::finish($med);

        $this->assertNotNull($this->notices()->firstOrFail()->cancelled_at);
        app(GenerateNotifications::class)->run($this->user);
        $this->assertSame(1, $this->notices()->count());
    }

    public function test_inactive_or_ended_medications_do_not_notify(): void
    {
        $this->medication(null, ['is_active' => false]);
        $this->medication(null, ['name' => 'Fin', 'ends_on' => '2026-11-15']);

        app(GenerateNotifications::class)->run($this->user);

        $this->assertSame(0, $this->notices()->count());
    }

    public function test_sensitive_medications_use_a_generic_text(): void
    {
        $this->medication(null, ['name' => 'Reservado', 'is_sensitive' => true]);

        app(GenerateNotifications::class)->run($this->user);

        $notice = $this->notices()->firstOrFail();
        $this->assertSame('Es hora de una toma programada', $notice->title);
        $this->assertStringNotContainsString('Reservado', $notice->title.$notice->body);
    }

    public function test_only_the_owner_is_notified(): void
    {
        $other = User::factory()->create();
        $this->medication($other, ['name' => 'Ajeno']);

        app(GenerateNotifications::class)->run($this->user);

        $this->assertSame(0, $this->notices()->count());
        $this->assertSame(0, $this->notices($other)->count());
    }

    public function test_push_goes_out_once_with_the_taken_button_inside_the_window(): void
    {
        $this->medication(null, ['times' => ['09:00']]);
        app(GenerateNotifications::class)->run($this->user);

        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->artisan('notifications:dispatch')->assertSuccessful();

        $this->assertCount(1, $this->gateway->sent);
        $payload = $this->gateway->sent[0]['payload'];
        $this->assertSame('quick-medication', $payload['actions'][0]['action']);
        $this->assertSame('Tomado', $payload['actions'][0]['title']);
        $this->assertArrayHasKey('quick_url', $payload);
        $this->assertStringContainsString('/health/medications', $payload['url']);
    }

    public function test_an_old_dose_stays_in_the_bell_but_is_not_pushed(): void
    {
        // La toma de las 08:00 ya tiene 90 minutos de retraso.
        $this->medication(null, ['times' => ['08:00']]);
        app(GenerateNotifications::class)->run($this->user);

        $this->artisan('notifications:dispatch')->assertSuccessful();

        $this->assertCount(0, $this->gateway->sent);
        $this->assertNull($this->notices()->firstOrFail()->push_sent_at);
        Livewire::actingAs($this->user)->test(NotificationBell::class)->set('open', true)->assertSee('Es hora de tu Losartán 50 mg');
    }

    public function test_turning_the_switch_off_stops_push_but_not_the_bell(): void
    {
        $this->medication(null, ['times' => ['09:00']]);
        app(NotificationPreferences::class)->save($this->user->id, array_replace(NotificationPreferences::DEFAULTS, ['push_medication_due' => false]));
        app(GenerateNotifications::class)->run($this->user);

        $this->artisan('notifications:dispatch')->assertSuccessful();

        $this->assertCount(0, $this->gateway->sent);
        Livewire::actingAs($this->user)->test(NotificationBell::class)->set('open', true)->assertSee('Es hora de tu Losartán 50 mg');
    }

    public function test_the_switch_defaults_to_on(): void
    {
        $this->assertTrue(app(NotificationPreferences::class)->get($this->user->id)['push_medication_due']);
        $this->assertTrue(NotificationPreferences::pushAllowed(NotificationPreferences::DEFAULTS, 'medication_due'));
    }

    public function test_actions_for_a_medication_notice_have_no_checkin_button(): void
    {
        $this->medication(null, ['times' => ['09:00']]);
        app(GenerateNotifications::class)->run($this->user);

        $actions = NotificationDispatcher::actions($this->notices()->firstOrFail());

        $this->assertSame(['quick-medication', 'open'], array_column($actions['actions'], 'action'));
    }
}
