<?php

namespace Tests\Feature;

use App\Livewire\Health\Cycle;
use App\Models\CycleDayLog;
use App\Models\MenstrualCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CycleModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Las fechas de la pantalla se escriben en español; el .env de ejemplo trae APP_LOCALE=en.
        $this->app->setLocale('es');
        Carbon::setLocale('es');
        Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Carbon::setLocale('en');
        parent::tearDown();
    }

    private function cycle(User $user, string $start, ?string $end = null): MenstrualCycle
    {
        return $user->menstrualCycles()->create(['started_on' => $start, 'ended_on' => $end]);
    }

    public function test_page_shows_the_privacy_notice_and_the_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('health.cycle'))
            ->assertOk()->assertSee('No es un dispositivo médico ni un método anticonceptivo')
            ->assertSee('Aún no has registrado ningún periodo')->assertSee('Empezó mi periodo');
    }

    public function test_one_tap_start_and_end(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Cycle::class)->call('quickStart')->assertHasNoErrors()
            ->assertSee('Periodo en curso desde el 17 de noviembre')->assertSee('Día 1 del ciclo')->assertSee('Terminó mi periodo');

        $cycle = MenstrualCycle::firstOrFail();
        $this->assertTrue($cycle->is_sensitive, 'sensible por defecto');
        $this->assertSame('app', $cycle->source);

        Carbon::setTestNow(Carbon::parse('2026-11-20 08:00:00', config('app.timezone')));
        Livewire::actingAs($user)->test(Cycle::class)->call('quickEnd')->assertHasNoErrors();

        $this->assertSame('2026-11-20', $cycle->fresh()->ended_on->toDateString());
        $this->assertSame(4, $cycle->fresh()->periodDays());
    }

    public function test_start_rules(): void
    {
        $user = User::factory()->create();
        $this->cycle($user, '2026-10-18', '2026-10-22');
        $open = $this->cycle($user, '2026-11-15');

        $component = Livewire::actingAs($user)->test(Cycle::class);
        $component->call('openStart')->set('started_on', '2026-11-16')->call('save')->assertHasErrors(['started_on']);   // ya hay uno en curso
        $open->update(['ended_on' => '2026-11-17']);
        $component->set('started_on', '2026-10-20')->call('save')->assertHasErrors(['started_on']);                       // dentro de otro periodo
        $component->set('started_on', '2026-11-30')->call('save')->assertHasErrors(['started_on']);                       // futuro
        $component->set('started_on', '2026-11-01')->call('save')->assertHasNoErrors();

        $this->assertSame(3, MenstrualCycle::count());
    }

    public function test_starting_twice_on_the_same_day_is_idempotent(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Cycle::class)->call('quickStart')->call('quickStart')->assertHasNoErrors();

        $this->assertSame(1, MenstrualCycle::count());
    }

    public function test_end_rules(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Cycle::class)->call('quickEnd')->assertHasErrors(['ended_on']); // no hay periodo en curso

        $this->cycle($user, '2026-11-01');
        $component = Livewire::actingAs($user)->test(Cycle::class)->call('openEnd');
        $component->set('ended_on', '2026-10-30')->call('save')->assertHasErrors(['ended_on']);   // antes del inicio
        $component->set('ended_on', '2026-11-16')->call('save')->assertHasErrors(['ended_on']);   // 16 días
        $component->set('ended_on', '2026-11-05')->call('save')->assertHasNoErrors();
    }

    public function test_day_log_is_saved_once_per_day_and_can_be_updated(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Cycle::class)
            ->call('openDay')->set('flow', 'heavy')->set('symptoms', ['cramps', 'fatigue'])->call('save')->assertHasNoErrors()
            ->call('openDay')->assertSet('flow', 'heavy')->assertSet('symptoms', ['cramps', 'fatigue'])
            ->set('flow', 'light')->call('save')->assertHasNoErrors()
            ->assertSee('Ligero')->assertSee('Cólicos, Cansancio');

        $this->assertSame(1, CycleDayLog::count());
        $this->assertSame('light', CycleDayLog::firstOrFail()->flow);
    }

    public function test_day_log_validation(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Cycle::class)->call('openDay');

        $component->call('save')->assertHasErrors(['flow']);                                       // vacío
        $component->set('flow', 'torrencial')->call('save')->assertHasErrors(['flow']);           // flujo inválido
        $component->set('flow', 'light')->set('date', '2026-11-25')->call('save')->assertHasErrors(['date']); // futuro
        $component->set('date', '2026-11-17')->set('symptoms', ['inventado'])->call('save')->assertHasErrors();
        $this->assertSame(0, CycleDayLog::count());
    }

    public function test_notes_and_symptoms_are_encrypted_in_the_database_and_everything_is_sensitive_by_default(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Cycle::class)
            ->call('openStart')->set('notes', 'Nota privada del periodo')->call('save')
            ->call('openDay')->set('flow', 'medium')->set('symptoms', ['headache'])->set('notes', 'Nota privada del día')->call('save');

        $rawCycle = DB::table('menstrual_cycles')->first();
        $rawDay = DB::table('cycle_day_logs')->first();

        $this->assertStringNotContainsString('privada', (string) $rawCycle->notes);
        $this->assertStringNotContainsString('privada', (string) $rawDay->notes);
        $this->assertStringNotContainsString('headache', (string) $rawDay->symptoms);
        $this->assertSame('Nota privada del periodo', MenstrualCycle::firstOrFail()->notes);
        $this->assertSame(['headache'], CycleDayLog::firstOrFail()->symptoms);
        $this->assertSame(1, (int) $rawCycle->is_sensitive);
        $this->assertSame(1, (int) $rawDay->is_sensitive);
    }

    public function test_estimate_appears_with_three_starts_and_not_before(): void
    {
        $user = User::factory()->create();
        $this->cycle($user, '2026-09-20', '2026-09-24');
        $this->cycle($user, '2026-10-18', '2026-10-22');

        Livewire::actingAs($user)->test(Cycle::class)
            ->assertSee('Con 3 inicios registrados podremos estimar tu próximo periodo')->assertSee('Llevas 2');

        $this->cycle($user, '2026-11-15');
        Livewire::actingAs($user)->test(Cycle::class)
            ->assertSee('Tiende a empezar entre el 11 de diciembre y el 15 de diciembre')->assertSee('Ciclo promedio 28 días')
            ->assertSee('Es una estimación');
    }

    public function test_edit_and_delete_a_cycle_but_keep_day_logs(): void
    {
        $user = User::factory()->create();
        $cycle = $this->cycle($user, '2026-11-10', '2026-11-14');
        $user->cycleDayLogs()->create(['date' => '2026-11-11', 'flow' => 'heavy']);

        Livewire::actingAs($user)->test(Cycle::class)
            ->call('editCycle', $cycle->id)->assertSet('started_on', '2026-11-10')->set('ended_on', '2026-11-13')->call('save')->assertHasNoErrors();
        $this->assertSame('2026-11-13', $cycle->fresh()->ended_on->toDateString());

        Livewire::actingAs($user)->test(Cycle::class)->call('deleteCycle', $cycle->id);
        $this->assertSame(0, MenstrualCycle::count());
        $this->assertSame(1, CycleDayLog::count());
    }

    public function test_month_navigation_and_invalid_month_fall_back(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->withQueryParams(['month' => 'basura'])->test(Cycle::class)->assertSet('month', '2026-11')
            ->call('previousMonth')->assertSet('month', '2026-10')->assertSee('Octubre 2026')
            ->call('nextMonth')->call('nextMonth')->assertSet('month', '2026-12');
    }

    public function test_user_never_sees_or_changes_another_users_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $cycle = $this->cycle($owner, '2026-11-10', '2026-11-14');
        $log = $owner->cycleDayLogs()->create(['date' => '2026-11-11', 'flow' => 'heavy', 'notes' => 'SoloDelDueño']);

        Livewire::actingAs($other)->test(Cycle::class)
            ->assertDontSee('SoloDelDueño')->assertSee('Aún no has registrado ningún periodo')
            ->call('deleteCycle', $cycle->id)->call('deleteDay', $log->id)
            ->call('editCycle', $cycle->id)->assertSet('dialog', '');

        $this->assertSame(1, MenstrualCycle::withoutGlobalScopes()->count());
        $this->assertSame(1, CycleDayLog::withoutGlobalScopes()->count());
    }
}
