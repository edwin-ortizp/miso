<?php

namespace Tests\Feature\Game;

use App\Livewire\Game\GuardianCard;
use App\Livewire\Health\HealthBodyMap;
use App\Livewire\Health\HealthMeasurements;
use App\Livewire\Health\Medications;
use App\Livewire\Health\Sleep;
use App\Livewire\Health\Trends;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\HealthEvent;
use App\Models\HealthMeasurement;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\SleepLog;
use App\Models\User;
use App\Services\Game\Guardian\HealthContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HealthGuardianContextTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        $species = GameSpecies::where('name', 'Gotin')->first();
        GameCompanion::create(['line_key' => $species->line_key, 'species_id' => $species->id, 'level' => 3, 'first_met_at' => now()]);
    }

    public function test_all_seven_pages_mount_the_same_health_guardian(): void
    {
        foreach (['health', 'health.body', 'health.measurements', 'health.medications', 'health.sleep', 'health.cycle', 'health.trends'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Ver ficha de Gotin')->assertSee('md-module-workspace--rail', false);
        }
    }

    public function test_context_advice_is_escaped_rotates_and_keeps_the_sheet(): void
    {
        $context = HealthContext::make('measurements', 'Mediciones', ['Valor <script>alert(1)</script>', 'Otro registro'], 'Sin registros');
        $card = Livewire::test(GuardianCard::class, ['module' => 'health', 'sectionContext' => $context]);
        $before = $card->get('adviceKey');
        $guardian = $card->get('guardian');
        $card->call('another')->assertDontSeeHtml('<script>alert(1)</script>');
        $this->assertNotSame($before, $card->get('adviceKey'));
        $card->call('openSheet')->assertSet('showSheet', true)->call('refreshAdvice');
        $this->assertSame($guardian, $card->get('guardian'));
    }

    public function test_empty_context_does_not_fall_back_to_general_health_advice(): void
    {
        Livewire::test(GuardianCard::class, ['module' => 'health', 'sectionContext' => HealthContext::make('trends', 'Periodo', [], 'Sin datos del periodo')])
            ->assertSee('Sin datos del periodo')->assertDontSee('Todo en orden');
    }

    public function test_trend_context_updates_with_the_selected_period_and_is_user_scoped(): void
    {
        HealthMeasurement::create(['type' => 'weight', 'value' => 70, 'unit' => 'kg', 'measured_at' => now()->subDays(50), 'source' => 'app']);
        $other = User::factory()->create();
        $other->healthMeasurements()->create(['type' => 'weight', 'value' => 80, 'unit' => 'kg', 'measured_at' => now(), 'source' => 'app']);
        $page = Livewire::test(Trends::class)->assertSee('Últimos 30 días');
        $this->assertSame([], $page->viewData('guardianContext')['facts']);
        $page->call('setDays', '90')->assertSee('Últimos 90 días');
        $this->assertStringContainsString('1 registro en 1 día', $page->viewData('guardianContext')['facts'][0]);
    }

    public function test_sleep_save_and_delete_refresh_context_without_exposing_another_user(): void
    {
        $other = User::factory()->create();
        $other->sleepLogs()->create(['slept_at' => now()->subHours(12), 'woke_at' => now()->subHours(2)]);
        $page = Livewire::test(Sleep::class);
        $this->assertStringContainsString('Aún no hay descansos', implode(' ', $page->viewData('guardianContext')['facts']));
        $page->call('openForm')->set('slept_at', now()->subHours(9)->format('Y-m-d\TH:i'))->set('woke_at', now()->subHour()->format('Y-m-d\TH:i'))->call('save')->assertHasNoErrors();
        $this->assertStringContainsString('Último descanso:', implode(' ', $page->viewData('guardianContext')['facts']));
        $page->call('delete', SleepLog::first()->id);
        $this->assertStringContainsString('Aún no hay descansos', implode(' ', $page->viewData('guardianContext')['facts']));
    }

    public function test_measurement_edits_and_deletes_update_the_summary(): void
    {
        $page = Livewire::test(HealthMeasurements::class)
            ->call('openForm')->set('value', '120')->set('value2', '80')->call('save')->assertHasNoErrors();
        $this->assertStringContainsString('120', $page->viewData('guardianContext')['facts'][0]);
        $record = HealthMeasurement::firstOrFail();
        $page->call('edit', $record->id)->set('value', '115')->call('save')->assertHasNoErrors();
        $this->assertStringContainsString('115', $page->viewData('guardianContext')['facts'][0]);
        $page->call('delete', $record->id);
        $this->assertSame([], $page->viewData('guardianContext')['facts']);
    }

    public function test_medication_take_skip_and_undo_update_the_counts(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $medication = Medication::create(['name' => 'Medicamento de prueba', 'frequency_type' => 'daily', 'times' => ['08:00', '20:00'], 'starts_on' => today(), 'is_active' => true]);
        $page = Livewire::test(Medications::class);
        $this->assertStringContainsString('2 pendientes', $page->viewData('guardianContext')['facts'][0]);
        $page->call('take', $medication->id, today()->setTime(8, 0)->toDateTimeString());
        $this->assertStringContainsString('1 tomadas', $page->viewData('guardianContext')['facts'][0]);
        $page->call('openSkip', $medication->id, today()->setTime(20, 0)->toDateTimeString())->call('saveSkip');
        $this->assertStringContainsString('1 omitidas', $page->viewData('guardianContext')['facts'][0]);
        $page->call('undo', MedicationIntake::where('status', 'skipped')->firstOrFail()->id);
        $this->assertStringContainsString('1 pendientes', $page->viewData('guardianContext')['facts'][0]);
        $this->travelBack();
    }

    public function test_body_selection_updates_even_for_a_zone_without_events(): void
    {
        $zone = array_key_first(HealthEvent::BODY_AREAS);
        $page = Livewire::test(HealthBodyMap::class)->call('selectZone', $zone);
        $this->assertStringContainsString('sin eventos', $page->viewData('guardianContext')['facts'][0]);
        $page->call('selectZone', 'invalid')->assertSet('selectedZone', null);
    }

    public function test_summaries_remain_when_the_game_is_off_or_there_are_no_companions(): void
    {
        $this->gameSettings(['enabled' => false]);
        $this->get(route('health.medications'))->assertOk()->assertSee('Tomas de hoy')->assertDontSee('Ver ficha de Gotin');
        GameCompanion::query()->delete();
        $this->gameSettings(['enabled' => true]);
        $this->get(route('health.measurements'))->assertOk()->assertSee('Últimas mediciones')->assertDontSee('Ver ficha de Gotin');
    }
}
