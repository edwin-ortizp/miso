<?php

namespace Tests\Feature;

use App\Livewire\Health\HealthMeasurements;
use App\Models\HealthMeasurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HealthMeasurementsTest extends TestCase
{
    use RefreshDatabase;

    private function measurement(User $user, float $sys, float $dia, array $extra = []): HealthMeasurement
    {
        return $user->healthMeasurements()->create([
            'type' => 'blood_pressure', 'value' => $sys, 'value2' => $dia, 'unit' => 'mmHg',
            'measured_at' => now(), ...$extra,
        ]);
    }

    public function test_page_shows_notice_and_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('health.measurements'))
            ->assertOk()
            ->assertSee('No es un dispositivo médico')
            ->assertSee('Aún no tienes mediciones');
    }

    public function test_saves_a_blood_pressure_and_lists_it(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')
            ->set('value', '120')
            ->set('value2', '80')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('120/80 mmHg')
            ->assertSee('En rango');

        $this->assertDatabaseHas('health_measurements', ['user_id' => $user->id, 'type' => 'blood_pressure', 'unit' => 'mmHg', 'source' => 'app']);
    }

    public function test_out_of_range_value_is_flagged_without_diagnosis(): void
    {
        $user = User::factory()->create();
        $this->measurement($user, 160, 100);

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->assertSee('Fuera de rango')
            ->assertSee('profesional de salud')
            ->assertDontSee('hipertensión');
    }

    public function test_validation_rejects_impossible_values_with_clear_messages(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')
            ->set('value', '80')->set('value2', '90')
            ->call('save')
            ->assertHasErrors(['value2'])
            ->set('value', '400')->set('value2', '80')
            ->call('save')
            ->assertHasErrors(['value'])
            ->set('value', '120')->set('value2', '')
            ->call('save')
            ->assertHasErrors(['value2'])
            ->set('value2', '80')->set('pulse', '10')
            ->call('save')
            ->assertHasErrors(['pulse']);

        $this->assertSame(0, HealthMeasurement::count());
    }

    public function test_edits_and_deletes_a_measurement(): void
    {
        $user = User::factory()->create();
        $m = $this->measurement($user, 118, 76);

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('edit', $m->id)
            ->assertSet('value', '118')
            ->set('value', '125')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(125.0, $m->fresh()->value);

        Livewire::actingAs($user)->test(HealthMeasurements::class)->call('delete', $m->id);
        $this->assertSame(0, HealthMeasurement::count());
    }

    public function test_user_never_sees_or_edits_another_users_measurements(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $m = $this->measurement($owner, 133, 88);

        Livewire::actingAs($other)->test(HealthMeasurements::class)
            ->assertDontSee('133/88')
            ->call('delete', $m->id);

        $this->assertSame(1, HealthMeasurement::withoutGlobalScopes()->count());

        Livewire::actingAs($other)->test(HealthMeasurements::class)
            ->call('edit', $m->id)
            ->assertSet('showForm', false)
            ->assertSet('value', '');
    }

    public function test_period_filter_limits_the_history(): void
    {
        $user = User::factory()->create();
        $this->measurement($user, 111, 71, ['measured_at' => now()->subDays(60)]);
        $this->measurement($user, 122, 82);

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->assertSee('111/71')
            ->call('setPeriod', '7')
            ->assertDontSee('111/71')
            ->assertSee('122/82');
    }

    // --- CHG-123: glucosa ---------------------------------------------------------------

    public function test_saves_glucose_with_its_moment_and_flags_it_against_that_moment(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')
            ->set('type', 'glucose')
            ->set('value', '92')
            ->set('context', 'fasting')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('92 mg/dL')
            ->assertSee('En ayunas')
            ->assertSee('En rango');

        // 120 está dentro del rango después de comer (70–140) pero fuera del de ayunas (70–99).
        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')->set('type', 'glucose')->set('value', '120')->set('context', 'after_meal')->call('save')->assertHasNoErrors();
        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')->set('type', 'glucose')->set('value', '120')->set('context', 'fasting')->call('save')->assertHasNoErrors()
            ->assertSee('Fuera de rango')
            ->assertSee('70–99 mg/dL');

        $this->assertSame(1, HealthMeasurement::where('context', 'fasting')->get()->filter->isOutOfRange()->count());
        $this->assertSame(0, HealthMeasurement::where('context', 'after_meal')->get()->filter->isOutOfRange()->count());
    }

    public function test_glucose_rejects_impossible_values_and_unknown_moments(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')->set('type', 'glucose')
            ->set('value', '5')->call('save')->assertHasErrors(['value'])
            ->set('value', '700')->call('save')->assertHasErrors(['value'])
            ->set('value', '95')->set('context', 'siempre')->call('save')->assertHasErrors(['context']);

        $this->assertSame(0, HealthMeasurement::count());
    }

    public function test_changing_type_clears_fields_that_the_new_type_does_not_use(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')
            ->set('value2', '80')->set('pulse', '70')
            ->set('type', 'glucose')
            ->assertSet('value2', '')->assertSet('pulse', '')
            ->assertDontSee('Diastólica');
    }

    public function test_latest_measurement_shows_a_card_per_type(): void
    {
        $user = User::factory()->create();
        $this->measurement($user, 128, 84);
        $user->healthMeasurements()->create(['type' => 'glucose', 'value' => 92, 'unit' => 'mg/dL', 'context' => 'fasting', 'measured_at' => now()]);

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->assertSee('Presión arterial')->assertSee('128/84 mmHg')
            ->assertSee('Glucosa en sangre')->assertSee('92 mg/dL');
    }

    // --- CHG-124: creatinina y examen relacionado -----------------------------------------

    private function exam(User $user, string $title = 'Laboratorio 12 nov', string $type = 'checkup'): \App\Models\HealthEvent
    {
        return $user->healthEvents()->create(['type' => $type, 'title' => $title, 'event_date' => '2026-11-12']);
    }

    public function test_saves_creatinine_linked_to_an_exam_and_flags_it_out_of_range(): void
    {
        $user = User::factory()->create();
        $exam = $this->exam($user);

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')->set('type', 'creatinine')->set('value', '0,9')->set('health_event_id', $exam->id)
            ->call('save')->assertHasNoErrors()
            ->assertSee('0,9 mg/dL')->assertSee('En rango')->assertSee('Laboratorio 12 nov');

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')->set('type', 'creatinine')->set('value', '2.4')
            ->call('save')->assertHasNoErrors()
            ->assertSee('Fuera de rango')->assertSee('0,6–1,3 mg/dL');

        $this->assertSame($exam->id, HealthMeasurement::where('value', 0.9)->firstOrFail()->health_event_id);
    }

    public function test_an_exam_that_is_not_a_checkup_or_belongs_to_someone_else_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $symptom = $this->exam($user, 'Dolor', 'symptom');
        $foreign = $this->exam($other, 'Ajeno');

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('openForm')->set('type', 'creatinine')->set('value', '1')
            ->set('health_event_id', $symptom->id)->call('save')->assertHasErrors(['health_event_id'])
            ->set('health_event_id', $foreign->id)->call('save')->assertHasErrors(['health_event_id'])
            ->set('health_event_id', '')->call('save')->assertHasNoErrors();
    }

    public function test_the_exam_can_be_changed_and_removed_on_edit(): void
    {
        $user = User::factory()->create();
        $exam = $this->exam($user);
        $m = $user->healthMeasurements()->create(['type' => 'creatinine', 'value' => 1, 'unit' => 'mg/dL', 'measured_at' => now(), 'health_event_id' => $exam->id]);

        Livewire::actingAs($user)->test(HealthMeasurements::class)
            ->call('edit', $m->id)->assertSet('health_event_id', $exam->id)
            ->set('health_event_id', '')->call('save')->assertHasNoErrors();

        $this->assertNull($m->fresh()->health_event_id);
    }

    public function test_deleting_the_exam_keeps_the_measurement_without_the_link(): void
    {
        $user = User::factory()->create();
        $exam = $this->exam($user);
        $m = $user->healthMeasurements()->create(['type' => 'creatinine', 'value' => 1, 'unit' => 'mg/dL', 'measured_at' => now(), 'health_event_id' => $exam->id]);

        $this->actingAs($user);
        $exam->delete();

        $this->assertNull($m->fresh()->health_event_id);
        $this->assertSame(1.0, $m->fresh()->value);
    }
}
