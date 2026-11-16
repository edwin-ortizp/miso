<?php

namespace Tests\Feature\Notifications;

use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\GenerateNotifications;
use App\Services\Notifications\Push\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** CHG-130: botón «Tomado» del aviso de un medicamento. */
class QuickMedicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Medication $medication;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 08:10:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->medication = $this->user->medications()->create([
            'name' => 'Losartán', 'dose_amount' => 50, 'dose_unit' => 'mg', 'frequency_type' => 'daily',
            'times' => ['08:00'], 'starts_on' => '2026-11-01', 'is_active' => true,
        ]);
        app(GenerateNotifications::class)->run($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function notice(): UserNotification
    {
        return UserNotification::withoutGlobalScopes()->where('type', 'medication_due')->firstOrFail();
    }

    private function url(?UserNotification $notice = null): string
    {
        return NotificationDispatcher::actions($notice ?? $this->notice())['quick_url'];
    }

    public function test_taken_registers_the_dose_once_without_a_session(): void
    {
        $url = $this->url();

        $this->postJson($url)->assertOk()->assertJson(['status' => 'logged']);
        $this->postJson($url)->assertOk()->assertJson(['status' => 'already-done']);

        $intake = MedicationIntake::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('taken', $intake->status);
        $this->assertSame('push', $intake->source);
        $this->assertSame('08:00', $intake->scheduled_for->format('H:i'));
        $this->assertSame($this->medication->id, $intake->medication_id);
        $this->assertNotNull($this->notice()->action_done_at);
        $this->assertNotNull($this->notice()->cancelled_at);
        $this->assertNull(auth()->user());
    }

    public function test_the_link_lasts_until_the_end_of_the_local_day(): void
    {
        $url = $this->url();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame(
            Carbon::parse('2026-11-16 23:59:59', 'America/Bogota')->timestamp,
            (int) $query['expires'],
        );

        // Al día siguiente el enlace ya no sirve.
        Carbon::setTestNow(Carbon::parse('2026-11-17 00:01:00', 'America/Bogota'));
        $this->postJson($url)->assertForbidden();
        $this->assertSame(0, MedicationIntake::withoutGlobalScopes()->count());
    }

    public function test_unsigned_or_tampered_links_are_rejected(): void
    {
        $notice = $this->notice();
        $expired = URL::temporarySignedRoute('push.quick-medication', now()->subMinute(), ['notification' => $notice->id]);

        $this->postJson(route('push.quick-medication', ['notification' => $notice->id]))->assertForbidden();
        $this->postJson($expired)->assertForbidden();
        $this->postJson($this->url().'x')->assertForbidden();
        $this->assertSame(0, MedicationIntake::withoutGlobalScopes()->count());
    }

    public function test_a_link_cannot_be_used_with_another_kind_of_notice(): void
    {
        $other = UserNotification::withoutGlobalScopes()->create([
            'user_id' => $this->user->id, 'source_type' => 'guardian', 'source_id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'guardian_nudge', 'occurrence_key' => 'x', 'title' => 'T', 'body' => 'B', 'scheduled_for' => now('UTC'),
        ]);
        $url = URL::temporarySignedRoute('push.quick-medication', now()->addHour(), ['notification' => $other->id]);

        $this->postJson($url)->assertNotFound();
        $this->assertSame(0, MedicationIntake::withoutGlobalScopes()->count());
    }

    public function test_a_finished_medication_reports_unavailable_and_frees_the_link(): void
    {
        $url = $this->url();
        $this->medication->update(['is_active' => false]);

        $this->postJson($url)->assertOk()->assertJson(['status' => 'unavailable']);

        $this->assertNull($this->notice()->action_done_at);
        $this->assertSame(0, MedicationIntake::withoutGlobalScopes()->count());
    }

    public function test_a_dose_already_taken_in_the_app_is_not_duplicated_or_retimed(): void
    {
        $url = $this->url();
        $this->actingAs($this->user);
        \App\Actions\LogMedicationIntake::take($this->medication, Carbon::parse('2026-11-16 08:00', 'America/Bogota'), Carbon::parse('2026-11-16 08:05', 'America/Bogota'));
        auth()->logout();
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00', 'America/Bogota'));

        $this->postJson($url)->assertOk()->assertJson(['status' => 'already-done']);

        $intakes = MedicationIntake::withoutGlobalScopes()->get();
        $this->assertCount(1, $intakes);
        $this->assertSame('08:05', $intakes[0]->taken_at->format('H:i'));
    }
}
