<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Context\GetHealthContextTool;
use App\Mcp\Tools\Context\GetRecentActivityTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LifeTrackerHealthTrendsContextMcpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-30 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedData(User $user, bool $sensitive = false): void
    {
        $user->healthMeasurements()->create(['type' => 'blood_pressure', 'value' => 150, 'value2' => 95, 'unit' => 'mmHg', 'measured_at' => '2026-11-29 08:00', 'is_sensitive' => $sensitive]);
        $user->sleepLogs()->create(['slept_at' => '2026-11-29 23:00', 'woke_at' => '2026-11-30 06:30', 'is_sensitive' => $sensitive]);
        $user->medications()->create(['name' => 'Losartán', 'dose_amount' => 50, 'dose_unit' => 'mg', 'frequency_type' => 'daily', 'times' => ['08:00', '20:00'], 'starts_on' => '2026-11-01', 'is_active' => true, 'is_sensitive' => $sensitive]);
    }

    public function test_health_context_includes_measurements_sleep_and_medications_today(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);

        LifeTrackerServer::actingAs($user)->tool(GetHealthContextTool::class, [])
            ->assertOk()
            ->assertSee('measurements_last_30_days')->assertSee('Presión arterial')->assertSee('150/95')->assertSee('"out_of_range":1')
            ->assertSee('sleep_last_7_days')->assertSee('7 h 30 min')
            ->assertSee('medications_today')->assertSee('Losartán 08:00 (atrasada)')->assertSee('Losartán 20:00');
    }

    public function test_sensitive_data_is_hidden_from_the_context_unless_requested(): void
    {
        $user = User::factory()->create();
        $this->seedData($user, sensitive: true);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(GetHealthContextTool::class, [])
            ->assertOk()->assertDontSee('150/95')->assertDontSee('Losartán')->assertDontSee('7 h 30 min')->assertSee('"hidden_sensitive":3');

        $server->tool(GetHealthContextTool::class, ['include_sensitive' => true])
            ->assertOk()->assertSee('150/95')->assertSee('Losartán')->assertSee('7 h 30 min');
    }

    public function test_the_menstrual_cycle_never_appears_in_the_broad_context(): void
    {
        $user = User::factory()->create();
        $user->menstrualCycles()->create(['started_on' => '2026-11-25', 'is_sensitive' => false, 'notes' => 'NotaDelCiclo']);
        $user->cycleDayLogs()->create(['date' => '2026-11-26', 'flow' => 'heavy', 'is_sensitive' => false]);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(GetHealthContextTool::class, ['include_sensitive' => true])->assertOk()->assertDontSee('2026-11-25')->assertDontSee('NotaDelCiclo')->assertDontSee('heavy');
        $server->tool(GetRecentActivityTool::class, ['include_sensitive' => true])->assertOk()->assertDontSee('2026-11-25')->assertDontSee('NotaDelCiclo')->assertDontSee('heavy');
    }

    public function test_recent_activity_lists_the_new_modules_and_can_be_narrowed(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        $user->medicationIntakes()->create(['name' => 'Losartán', 'dose_amount' => 50, 'dose_unit' => 'mg', 'status' => 'taken', 'taken_at' => '2026-11-30 08:05', 'scheduled_for' => '2026-11-30 08:00']);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(GetRecentActivityTool::class, ['days' => 7])
            ->assertOk()->assertSee('[medición] Presión arterial 150/95 mmHg')->assertSee('fuera del rango de referencia general')
            ->assertSee('[sueño] Durmió 7 h 30 min')->assertSee('[medicamentos] Tomó Losartán 50 mg a las 08:05');

        $server->tool(GetRecentActivityTool::class, ['days' => 7, 'modules' => ['sleep']])
            ->assertOk()->assertSee('[sueño]')->assertDontSee('[medición]')->assertDontSee('[medicamentos]');
    }

    public function test_recent_activity_hides_sensitive_new_modules_unless_requested(): void
    {
        $user = User::factory()->create();
        $this->seedData($user, sensitive: true);
        $user->medicationIntakes()->create(['name' => 'Reservado', 'status' => 'taken', 'taken_at' => '2026-11-30 08:05', 'is_sensitive' => true]);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(GetRecentActivityTool::class, ['days' => 7])->assertOk()->assertDontSee('Presión arterial')->assertDontSee('Durmió')->assertDontSee('Reservado');
        $server->tool(GetRecentActivityTool::class, ['days' => 7, 'include_sensitive' => true])->assertOk()->assertSee('Presión arterial')->assertSee('Durmió')->assertSee('Reservado');
    }

    public function test_context_never_includes_another_users_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->seedData($owner);

        LifeTrackerServer::actingAs($other)->tool(GetHealthContextTool::class, [])
            ->assertOk()->assertDontSee('150/95')->assertDontSee('Losartán')->assertDontSee('7 h 30 min');
    }

    public function test_the_sleep_and_energy_finding_reaches_the_assistant_only_with_enough_data(): void
    {
        $user = User::factory()->create();
        foreach ([['2026-11-20', 8, 4], ['2026-11-21', 8.5, 5], ['2026-11-22', 8, 4], ['2026-11-23', 5, 2], ['2026-11-24', 6, 3], ['2026-11-25', 5.5, 3], ['2026-11-26', 8, 4]] as [$day, $hours, $energy]) {
            $woke = Carbon::parse($day.' 07:00', config('app.timezone'));
            $user->sleepLogs()->create(['slept_at' => $woke->copy()->subHours($hours), 'woke_at' => $woke]);
            $user->energyEntries()->create(['date' => $day, 'level' => $energy, 'time' => '12:00', 'timestamp' => $woke->timestamp]);
        }

        LifeTrackerServer::actingAs($user)->tool(GetHealthContextTool::class, [])
            ->assertOk()->assertSee('sleep_and_energy')->assertSee('tiende a ser más alta')->assertSee('Esto no implica una causa');
    }
}
