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
}
