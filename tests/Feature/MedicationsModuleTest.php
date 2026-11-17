<?php

namespace Tests\Feature;

use App\Livewire\Health\Medications;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MedicationsModuleTest extends TestCase
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

    public function test_page_shows_notice_and_empty_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('health.medications'))
            ->assertOk()
            ->assertSee('MiSo no sustituye la indicación de tu profesional de salud')
            ->assertSee('Aún no tienes medicamentos')
            ->assertSee('Aún no hay tomas registradas');
    }

    public function test_creates_a_medication_and_it_appears_in_today(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Medications::class)
            ->call('openForm')
            ->set('name', 'Metformina')
            ->set('dose_amount', '850')
            ->set('dose_unit', 'mg')
            ->set('times', ['08:00', '20:00'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Metformina 850 mg')
            ->assertSee('0 de 2 tomas registradas hoy');

        $med = Medication::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['08:00', '20:00'], $med->times);
    }

    public function test_validation_errors_land_on_the_screen_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Medications::class)
            ->call('openForm')
            ->set('name', '')
            ->set('times', ['mañana'])
            ->call('save')
            ->assertHasErrors(['name', 'times.0'])
            ->set('name', 'X')->set('times', ['08:00'])->set('frequency_type', 'weekdays')
            ->call('save')
            ->assertHasErrors(['weekdays'])
            ->set('weekdays', ['1', '3'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([1, 3], Medication::firstOrFail()->weekdays);
    }

    public function test_take_skip_and_undo_a_scheduled_dose(): void
    {
        $user = User::factory()->create();
        $med = $this->medication($user);

        Livewire::actingAs($user)->test(Medications::class)
            ->assertSee('Atrasada')
            ->assertSee('Pendiente')
            ->call('take', $med->id, '2026-11-16 08:00')
            ->assertSee('Tomada a las 09:30')
            ->call('openSkip', $med->id, '2026-11-16 20:00')
            ->assertSet('showSkip', true)
            ->set('skipReason', 'Viaje')
            ->call('saveSkip')
            ->assertSet('showSkip', false)
            ->assertSee('Omitida · Viaje')
            ->assertSee('No tienes tomas pendientes hoy');

        $this->assertSame(2, MedicationIntake::count());

        $taken = MedicationIntake::where('status', 'taken')->firstOrFail();
        Livewire::actingAs($user)->test(Medications::class)->call('undo', $taken->id);

        $this->assertSame(1, MedicationIntake::count());
    }

    public function test_ad_hoc_intake_is_saved_without_a_schedule(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Medications::class)
            ->call('openAdHoc')
            ->set('adhocName', '')
            ->call('saveAdHoc')
            ->assertHasErrors(['adhocName'])
            ->set('adhocName', 'Ibuprofeno')->set('adhocDoseAmount', '400')->set('adhocDoseUnit', 'mg')
            ->call('saveAdHoc')
            ->assertHasNoErrors()
            ->assertSee('Ibuprofeno')
            ->assertSee('puntual');

        $intake = MedicationIntake::firstOrFail();
        $this->assertNull($intake->scheduled_for);
        $this->assertNull($intake->medication_id);
    }

    public function test_finished_and_deleted_medications_keep_their_history(): void
    {
        $user = User::factory()->create();
        $med = $this->medication($user);
        $user->medicationIntakes()->create(['medication_id' => $med->id, 'name' => 'Losartán', 'status' => 'taken', 'taken_at' => now(), 'scheduled_for' => now()->setTime(8, 0)]);

        $component = Livewire::actingAs($user)->test(Medications::class);
        $component->call('finish', $med->id);
        $this->assertFalse($med->fresh()->is_active);

        $component->call('resume', $med->id);
        $this->assertTrue($med->fresh()->is_active);

        $component->call('deleteMedication', $med->id);
        $this->assertSame(0, Medication::count());
        $this->assertSame(1, MedicationIntake::count());
    }

    public function test_edit_updates_the_program(): void
    {
        $user = User::factory()->create();
        $med = $this->medication($user);

        Livewire::actingAs($user)->test(Medications::class)
            ->call('edit', $med->id)
            ->assertSet('name', 'Losartán')
            ->assertSet('times', ['08:00', '20:00'])
            ->set('times', ['09:00'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['09:00'], $med->fresh()->times);
    }

    public function test_times_are_individual_selectors_that_can_be_added_and_removed(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Medications::class)
            ->call('openForm')
            ->assertSet('times', ['08:00'])
            ->call('addTime')
            ->assertSet('times', ['08:00', '10:00'])
            ->call('removeTime', 0)
            ->assertSet('times', ['10:00'])
            ->call('removeTime', 0)
            ->assertSet('times', ['10:00'])
            ->assertSee('Agregar hora')
            ->assertSee('Hora 1');
    }

    public function test_weekdays_use_the_touch_chip_selector_with_a_visible_privacy_switch(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Medications::class)
            ->call('openForm')
            ->set('frequency_type', 'weekdays')
            ->assertSee('md-choice-chips', false)
            ->assertSee('aria-label="Miércoles"', false)
            ->assertSee('Mantener oculto al asistente')
            ->assertDontSee('Más detalle');
    }

    public function test_user_never_sees_or_touches_another_users_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $med = $this->medication($owner, ['name' => 'SoloDelDueño']);
        $intake = $owner->medicationIntakes()->create(['medication_id' => $med->id, 'name' => 'SoloDelDueño', 'status' => 'taken', 'taken_at' => now(), 'scheduled_for' => now()->setTime(8, 0)]);

        Livewire::actingAs($other)->test(Medications::class)
            ->assertDontSee('SoloDelDueño')
            ->call('deleteMedication', $med->id)
            ->call('deleteIntake', $intake->id)
            ->call('edit', $med->id)
            ->assertSet('showForm', false);

        $this->assertSame(1, Medication::withoutGlobalScopes()->count());
        $this->assertSame(1, MedicationIntake::withoutGlobalScopes()->count());
    }

    public function test_period_filter_limits_the_history(): void
    {
        $user = User::factory()->create();
        $user->medicationIntakes()->create(['name' => 'Antigua', 'status' => 'taken', 'taken_at' => now()->subDays(60)]);
        $user->medicationIntakes()->create(['name' => 'Reciente', 'status' => 'taken', 'taken_at' => now()]);

        Livewire::actingAs($user)->test(Medications::class)
            ->assertSee('Antigua')
            ->call('applyFilters', '7')
            ->assertDontSee('Antigua')
            ->assertSee('Reciente');
    }
}
