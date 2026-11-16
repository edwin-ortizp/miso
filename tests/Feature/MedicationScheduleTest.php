<?php

namespace Tests\Feature;

use App\Actions\LogMedicationIntake;
use App\Actions\SaveMedication;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\User;
use App\Support\MedicationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MedicationScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:30:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function medication(User $user, array $overrides = []): Medication
    {
        return $user->medications()->create([
            'name' => 'Losartán', 'dose_amount' => 50, 'dose_unit' => 'mg', 'frequency_type' => 'daily',
            'times' => ['08:00', '20:00'], 'starts_on' => '2026-11-01', 'is_active' => true, ...$overrides,
        ]);
    }

    private function hours($slots): array
    {
        return $slots->map(fn ($s) => $s->format('H:i'))->all();
    }

    public function test_daily_slots_follow_the_times(): void
    {
        $med = $this->medication(User::factory()->create());

        $this->assertSame(['08:00', '20:00'], $this->hours(MedicationSchedule::slots($med, now())));
    }

    public function test_every_hours_starts_at_the_first_time_and_stays_within_the_day(): void
    {
        $med = $this->medication(User::factory()->create(), ['frequency_type' => 'every_hours', 'interval_hours' => 8, 'times' => ['06:00']]);

        $this->assertSame(['06:00', '14:00', '22:00'], $this->hours(MedicationSchedule::slots($med, now())));
    }

    public function test_weekday_schedule_only_applies_on_chosen_days(): void
    {
        // 2026-11-16 es lunes (1).
        $med = $this->medication(User::factory()->create(), ['frequency_type' => 'weekdays', 'weekdays' => [1, 3], 'times' => ['20:00']]);

        $this->assertSame(['20:00'], $this->hours(MedicationSchedule::slots($med, Carbon::parse('2026-11-16'))));
        $this->assertCount(0, MedicationSchedule::slots($med, Carbon::parse('2026-11-17')));
        $this->assertCount(1, MedicationSchedule::slots($med, Carbon::parse('2026-11-18')));
    }

    public function test_no_slots_before_start_after_end_or_when_inactive(): void
    {
        $user = User::factory()->create();

        $this->assertCount(0, MedicationSchedule::slots($this->medication($user, ['starts_on' => '2026-11-20']), now()));
        $this->assertCount(0, MedicationSchedule::slots($this->medication($user, ['ends_on' => '2026-11-15']), now()));
        $this->assertCount(2, MedicationSchedule::slots($this->medication($user, ['ends_on' => '2026-11-16']), now()));
        $this->assertCount(0, MedicationSchedule::slots($this->medication($user, ['is_active' => false]), now()));
    }

    public function test_day_rows_have_pending_overdue_taken_and_skipped_states(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $morning = $this->medication($user, ['name' => 'Mañana', 'times' => ['08:00']]);
        $this->medication($user, ['name' => 'Noche', 'times' => ['20:00']]);
        $skipped = $this->medication($user, ['name' => 'Omitido', 'times' => ['07:00']]);

        $rows = fn () => MedicationSchedule::forDay(now())->keyBy(fn ($r) => $r['medication']->name);

        $this->assertSame('overdue', $rows()['Mañana']['state']);
        $this->assertSame('pending', $rows()['Noche']['state']);

        LogMedicationIntake::take($morning, Carbon::parse('2026-11-16 08:00'), now());
        LogMedicationIntake::skip($skipped, Carbon::parse('2026-11-16 07:00'), 'Se me olvidó');

        $this->assertSame('taken', $rows()['Mañana']['state']);
        $this->assertSame('skipped', $rows()['Omitido']['state']);
    }

    public function test_taking_the_same_dose_twice_keeps_one_row_and_undo_makes_it_pending_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $med = $this->medication($user, ['times' => ['08:00']]);
        $slot = Carbon::parse('2026-11-16 08:00');

        $first = LogMedicationIntake::take($med, $slot);
        $again = LogMedicationIntake::take($med, $slot);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, MedicationIntake::count());

        LogMedicationIntake::skip($med, $slot, 'Náuseas');
        $this->assertSame('skipped', MedicationIntake::firstOrFail()->status);

        LogMedicationIntake::undo(MedicationIntake::firstOrFail());
        $this->assertSame('overdue', MedicationSchedule::forDay(now())->first()['state']);
    }

    public function test_operation_key_makes_retries_idempotent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $data = LogMedicationIntake::validate(['name' => 'Ibuprofeno', 'dose_amount' => 400, 'dose_unit' => 'mg', 'operation_key' => 'abc']);
        LogMedicationIntake::adHoc($data);
        LogMedicationIntake::adHoc($data);

        $this->assertSame(1, MedicationIntake::count());
    }

    public function test_deleting_a_medication_keeps_its_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $med = $this->medication($user, ['times' => ['08:00']]);
        LogMedicationIntake::take($med, Carbon::parse('2026-11-16 08:00'));

        $med->delete();

        $intake = MedicationIntake::firstOrFail();
        $this->assertNull($intake->medication_id);
        $this->assertSame('Losartán', $intake->name);
    }

    public function test_finishing_stops_future_slots_but_keeps_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $med = $this->medication($user);

        SaveMedication::finish($med);

        $this->assertFalse($med->fresh()->is_active);
        $this->assertCount(0, MedicationSchedule::forDay(now()));
    }

    public function test_validation_rejects_incomplete_programs(): void
    {
        $base = ['name' => 'X', 'frequency_type' => 'daily', 'times' => ['08:00'], 'starts_on' => '2026-11-16'];

        $this->assertArrayHasKey('name', $this->errors([...$base, 'name' => '']));
        $this->assertArrayHasKey('times.0', $this->errors([...$base, 'times' => ['8am']]));
        $this->assertArrayHasKey('interval_hours', $this->errors([...$base, 'frequency_type' => 'every_hours']));
        $this->assertArrayHasKey('weekdays', $this->errors([...$base, 'frequency_type' => 'weekdays']));
        $this->assertArrayHasKey('ends_on', $this->errors([...$base, 'ends_on' => '2026-11-01']));
        $this->assertSame([], $this->errors($base));
    }

    private function errors(array $data): array
    {
        try {
            SaveMedication::validate($data);

            return [];
        } catch (ValidationException $e) {
            return $e->errors();
        }
    }
}
