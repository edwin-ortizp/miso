<?php

namespace Tests\Feature;

use App\Livewire\Health\Trends;
use App\Models\User;
use App\Support\HealthTrends;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class HealthTrendsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('es');
        Carbon::setLocale('es');
        Carbon::setTestNow(Carbon::parse('2026-11-30 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Carbon::setLocale('en');
        parent::tearDown();
    }

    private function bp(User $user, string $day, float $sys, float $dia = 80): void
    {
        $user->healthMeasurements()->create(['type' => 'blood_pressure', 'value' => $sys, 'value2' => $dia, 'unit' => 'mmHg', 'measured_at' => $day.' 08:00']);
    }

    // --- Lógica pura ---------------------------------------------------------------------

    public function test_direction_needs_six_days_and_a_five_percent_change(): void
    {
        $this->assertNull(HealthTrends::direction([100, 100, 100, 100, 100]));
        $this->assertSame('up', HealthTrends::direction([100, 100, 100, 110, 110, 110]));
        $this->assertSame('down', HealthTrends::direction([100, 100, 100, 90, 90, 90]));
        $this->assertSame('stable', HealthTrends::direction([100, 100, 100, 102, 102, 102]));
        $this->assertSame('stable', HealthTrends::direction([0, 0, 0, 0, 0, 0]));
        $this->assertSame('Tiende a subir', HealthTrends::directionText('up'));
        $this->assertSame('Se mantiene estable', HealthTrends::directionText('stable'));
        $this->assertNull(HealthTrends::directionText(null));
    }

    public function test_measurement_series_averages_each_day_and_summarises(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->bp($user, '2026-11-28', 120, 80);
        $user->healthMeasurements()->create(['type' => 'blood_pressure', 'value' => 140, 'value2' => 90, 'unit' => 'mmHg', 'measured_at' => '2026-11-28 20:00']);
        $this->bp($user, '2026-11-29', 118, 78);
        $this->bp($user, '2026-09-01', 200, 100); // fuera del periodo

        $data = HealthTrends::measurement('blood_pressure', 30);

        $this->assertCount(2, $data['points']);
        $this->assertSame(130.0, $data['points'][0]['value']);
        $this->assertSame(85.0, $data['points'][0]['value2']);
        $this->assertTrue($data['points'][0]['out_of_range']);
        $this->assertSame(3, $data['summary']['count']);
        $this->assertSame(1, $data['summary']['out_of_range']);
        $this->assertNull($data['summary']['direction'], 'solo 2 días con datos');
        $this->assertSame(118.0, $data['summary']['min']);
        $this->assertSame(140.0, $data['summary']['max']);
    }

    public function test_sleep_series_sums_each_wake_day_and_flags_nights_under_the_goal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->sleepLogs()->create(['slept_at' => '2026-11-28 23:00', 'woke_at' => '2026-11-29 07:00']);
        $user->sleepLogs()->create(['slept_at' => '2026-11-29 23:00', 'woke_at' => '2026-11-30 05:00']);

        $data = HealthTrends::sleep(30);

        $this->assertSame([8.0, 6.0], $data['points']->pluck('value')->all());
        $this->assertSame([false, true], $data['points']->pluck('out_of_range')->all());
        $this->assertSame(1, $data['summary']['meeting_goal']);
        $this->assertSame(420, $data['summary']['average_minutes']);
    }

    private function pairedDays(User $user, array $nights): void
    {
        // [día de despertar, horas dormidas, energía]
        foreach ($nights as [$day, $hours, $energy]) {
            $woke = Carbon::parse($day.' 07:00', config('app.timezone'));
            $user->sleepLogs()->create(['slept_at' => $woke->copy()->subHours($hours), 'woke_at' => $woke]);
            $user->energyEntries()->create(['date' => $day, 'level' => $energy, 'time' => '12:00', 'timestamp' => $woke->timestamp]);
        }
    }

    public function test_sleep_energy_insight_is_cautious_and_needs_enough_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->pairedDays($user, [
            ['2026-11-20', 8, 4], ['2026-11-21', 8.5, 5], ['2026-11-22', 8, 4], ['2026-11-23', 5, 2], ['2026-11-24', 6, 3], ['2026-11-25', 5.5, 3], ['2026-11-26', 8, 4],
        ]);

        $insight = HealthTrends::sleepEnergy(30);

        $this->assertSame('higher', $insight['direction']);
        $this->assertSame(7, $insight['days']);
        $this->assertStringContainsString('tiende a ser más alta', $insight['text']);
        $this->assertStringContainsString('Esto no implica una causa', $insight['text']);
        $this->assertStringNotContainsString('causa de', $insight['text']);
    }

    public function test_sleep_energy_insight_is_hidden_when_data_is_short_or_the_difference_small(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->pairedDays($user, [['2026-11-20', 8, 4], ['2026-11-21', 5, 2], ['2026-11-22', 8, 4]]);
        $this->assertNull(HealthTrends::sleepEnergy(30), 'menos de 7 días');

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->pairedDays($other, [
            ['2026-11-20', 8, 4], ['2026-11-21', 8, 4], ['2026-11-22', 8, 3.8], ['2026-11-23', 5, 4], ['2026-11-24', 6, 3.9], ['2026-11-25', 5.5, 4], ['2026-11-26', 8, 4],
        ]);
        $this->assertNull(HealthTrends::sleepEnergy(30), 'diferencia menor de 0,5');
    }

    // --- Pantalla ------------------------------------------------------------------------

    public function test_page_shows_the_empty_state_and_the_notice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('health.trends'))
            ->assertOk()->assertSee('No diagnostica ni reemplaza a tu profesional de salud')->assertSee('Aún no hay datos para mostrar tendencias');
    }

    public function test_page_draws_a_chart_per_type_with_data_and_a_text_summary(): void
    {
        $user = User::factory()->create();
        foreach ([['2026-11-20', 118], ['2026-11-22', 119], ['2026-11-24', 117], ['2026-11-26', 135], ['2026-11-28', 138], ['2026-11-29', 140]] as [$day, $sys]) {
            $this->bp($user, $day, $sys, 80);
        }
        $user->healthMeasurements()->create(['type' => 'weight', 'value' => 72, 'unit' => 'kg', 'measured_at' => '2026-11-29 07:00']);

        Livewire::actingAs($user)->test(Trends::class)
            ->assertSee('Presión arterial')->assertSee('6 registros')->assertSee('Promedio 127,8/80 mmHg')->assertSee('3 fuera del rango de referencia general')
            ->assertSee('Tiende a subir')->assertSee('Rango de referencia')->assertSee('Sistólica')->assertSee('Diastólica')->assertSee('Ver datos')
            ->assertSee('Peso')->assertSee('1 registro')
            ->assertDontSee('Glucosa en sangre')->assertDontSee('Aún no hay datos');
    }

    public function test_a_single_record_shows_a_point_without_a_direction(): void
    {
        $user = User::factory()->create();
        $user->healthMeasurements()->create(['type' => 'weight', 'value' => 72, 'unit' => 'kg', 'measured_at' => '2026-11-29 07:00']);

        Livewire::actingAs($user)->test(Trends::class)
            ->assertSee('Promedio 72')->assertDontSee('Tiende a')->assertDontSee('Se mantiene estable');
    }

    public function test_period_switch_limits_the_data_and_rejects_bad_values(): void
    {
        $user = User::factory()->create();
        $this->bp($user, '2026-10-20', 150, 95); // hace 41 días
        $this->bp($user, '2026-11-29', 118, 78);

        Livewire::actingAs($user)->test(Trends::class)
            ->assertSee('1 registro')
            ->call('setDays', '90')->assertSet('days', '90')->assertSee('2 registros')
            ->call('setDays', '7')->assertSet('days', '30');
    }

    public function test_sleep_chart_and_the_insight_appear_on_the_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->pairedDays($user, [
            ['2026-11-20', 8, 4], ['2026-11-21', 8.5, 5], ['2026-11-22', 8, 4], ['2026-11-23', 5, 2], ['2026-11-24', 6, 3], ['2026-11-25', 5.5, 3], ['2026-11-26', 8, 4],
        ]);

        Livewire::actingAs($user)->test(Trends::class)
            ->assertSee('Sueño y energía')->assertSee('tiende a ser más alta')->assertSee('Horas dormidas')->assertSee('7 registros · meta 8 h')->assertSee('4 de 7 registros cumplieron');
    }

    public function test_page_never_shows_another_users_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->bp($owner, '2026-11-29', 133, 88);

        Livewire::actingAs($other)->test(Trends::class)->assertDontSee('Presión arterial')->assertSee('Aún no hay datos');
    }
}
