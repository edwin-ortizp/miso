<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Tools\Common\DeleteEntryTool;
use App\Mcp\Tools\Health\ListMedicationsTool;
use App\Mcp\Tools\Health\LogMedicationIntakeTool;
use App\Mcp\Tools\Health\LogMedicationTool;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LifeTrackerMedicationMcpTest extends TestCase
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

    public function test_log_medication_creates_a_program(): void
    {
        $user = User::factory()->create();

        LifeTrackerServer::actingAs($user)
            ->tool(LogMedicationTool::class, [
                'name' => 'Metformina', 'dose_amount' => 850, 'dose_unit' => 'mg',
                'frequency_type' => 'daily', 'times' => ['08:00', '20:00'],
            ])
            ->assertOk()
            ->assertSee('Metformina 850 mg');

        $med = Medication::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['08:00', '20:00'], $med->times);
        $this->assertSame('2026-11-16', $med->starts_on->toDateString());
        $this->assertTrue($med->is_active);
    }

    public function test_log_medication_rejects_incomplete_data(): void
    {
        $user = User::factory()->create();
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(LogMedicationTool::class, ['name' => 'X', 'frequency_type' => 'every_hours', 'times' => ['08:00']])->assertHasErrors();
        $server->tool(LogMedicationTool::class, ['name' => 'X', 'frequency_type' => 'daily', 'times' => ['mañana']])->assertHasErrors();

        $this->assertSame(0, Medication::count());
    }

    public function test_update_finish_and_resume(): void
    {
        $user = User::factory()->create();
        $med = $this->medication($user);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(LogMedicationTool::class, ['action' => 'update', 'medication' => 'Losart', 'dose_amount' => 100])->assertOk();
        $this->assertSame(100.0, $med->fresh()->dose_amount);
        $this->assertSame(['08:00', '20:00'], $med->fresh()->times);

        $server->tool(LogMedicationTool::class, ['action' => 'finish', 'medication' => $med->id])->assertOk()->assertSee('historial se conserva');
        $this->assertFalse($med->fresh()->is_active);

        $server->tool(LogMedicationTool::class, ['action' => 'resume', 'medication' => $med->id])->assertOk();
        $this->assertTrue($med->fresh()->is_active);
        $this->assertNull($med->fresh()->ends_on);
    }

    public function test_list_today_matches_what_remains_to_take(): void
    {
        $user = User::factory()->create();
        $this->medication($user);

        LifeTrackerServer::actingAs($user)
            ->tool(ListMedicationsTool::class, [])
            ->assertOk()
            ->assertSee('Losartán')
            ->assertSee('overdue')
            ->assertSee('pending');
    }

    public function test_intake_without_a_time_marks_the_nearest_open_dose_and_reports_what_is_left(): void
    {
        $user = User::factory()->create();
        $this->medication($user);

        LifeTrackerServer::actingAs($user)
            ->tool(LogMedicationIntakeTool::class, ['medication' => 'Losartán'])
            ->assertOk()
            ->assertSee('Toma registrada')
            ->assertSee('Pendientes hoy: Losartán 20:00');

        $intake = MedicationIntake::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('taken', $intake->status);
        $this->assertSame('08:00', $intake->scheduled_for->format('H:i'));
        $this->assertSame('mcp', $intake->source);
    }

    public function test_skipping_records_the_reason(): void
    {
        $user = User::factory()->create();
        $this->medication($user);

        LifeTrackerServer::actingAs($user)
            ->tool(LogMedicationIntakeTool::class, ['medication' => 'Losartán', 'status' => 'skipped', 'skip_reason' => 'Náuseas'])
            ->assertOk()
            ->assertSee('omitida');

        $this->assertSame('Náuseas', MedicationIntake::firstOrFail()->skip_reason);
    }

    public function test_ad_hoc_intake_and_idempotent_retry_with_operation_key(): void
    {
        $user = User::factory()->create();
        $server = LifeTrackerServer::actingAs($user);
        $args = ['name' => 'Ibuprofeno', 'dose_amount' => 400, 'dose_unit' => 'mg', 'operation_key' => 'retry-1'];

        $server->tool(LogMedicationIntakeTool::class, $args)->assertOk()->assertSee('Toma puntual');
        $server->tool(LogMedicationIntakeTool::class, $args)->assertOk();

        $this->assertSame(1, MedicationIntake::where('user_id', $user->id)->count());
        $this->assertNull(MedicationIntake::firstOrFail()->scheduled_for);
    }

    public function test_unknown_or_ambiguous_medication_returns_a_clear_error(): void
    {
        $user = User::factory()->create();
        $this->medication($user, ['name' => 'Ibuprofeno 400']);
        $this->medication($user, ['name' => 'Ibuprofeno 600']);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(LogMedicationIntakeTool::class, ['medication' => 'Ibuprofeno'])->assertHasErrors();
        $server->tool(LogMedicationIntakeTool::class, ['medication' => 'Inexistente'])->assertHasErrors();
        $this->assertSame(0, MedicationIntake::count());
    }

    public function test_sensitive_medications_and_intakes_are_hidden_unless_requested(): void
    {
        $user = User::factory()->create();
        $this->medication($user, ['name' => 'Visible']);
        $this->medication($user, ['name' => 'Reservado', 'is_sensitive' => true]);
        $user->medicationIntakes()->create(['name' => 'TomaReservada', 'status' => 'taken', 'taken_at' => now(), 'is_sensitive' => true]);
        $server = LifeTrackerServer::actingAs($user);

        $server->tool(ListMedicationsTool::class, ['scope' => 'today'])->assertSee('Visible')->assertDontSee('Reservado')->assertSee('hidden_sensitive');
        $server->tool(ListMedicationsTool::class, ['scope' => 'active'])->assertDontSee('Reservado');
        $server->tool(ListMedicationsTool::class, ['scope' => 'history'])->assertDontSee('TomaReservada');

        $server->tool(ListMedicationsTool::class, ['scope' => 'active', 'include_sensitive' => true])->assertSee('Reservado');
        $server->tool(ListMedicationsTool::class, ['scope' => 'history', 'include_sensitive' => true])->assertSee('TomaReservada');
    }

    public function test_history_lists_taken_and_skipped(): void
    {
        $user = User::factory()->create();
        $user->medicationIntakes()->create(['name' => 'Omeprazol', 'status' => 'taken', 'taken_at' => now()->subDay(), 'scheduled_for' => now()->subDay()->setTime(7, 0)]);
        $user->medicationIntakes()->create(['name' => 'Omeprazol', 'status' => 'skipped', 'scheduled_for' => now()->setTime(7, 0), 'skip_reason' => 'Viaje']);

        LifeTrackerServer::actingAs($user)
            ->tool(ListMedicationsTool::class, ['scope' => 'history'])
            ->assertOk()
            ->assertSee('Omeprazol')
            ->assertSee('Viaje');
    }

    public function test_never_returns_or_touches_another_users_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->medication($owner, ['name' => 'SoloDelDueño']);
        $server = LifeTrackerServer::actingAs($other);

        $server->tool(ListMedicationsTool::class, ['scope' => 'active'])->assertDontSee('SoloDelDueño');
        $server->tool(LogMedicationIntakeTool::class, ['medication' => 'SoloDelDueño'])->assertHasErrors();
        $this->assertSame(0, MedicationIntake::withoutGlobalScopes()->count());
    }

    public function test_delete_entry_tool_removes_medication_but_keeps_its_history(): void
    {
        $user = User::factory()->create();
        $med = $this->medication($user);
        $user->medicationIntakes()->create(['medication_id' => $med->id, 'name' => 'Losartán', 'status' => 'taken', 'taken_at' => now(), 'scheduled_for' => now()->setTime(8, 0)]);

        LifeTrackerServer::actingAs($user)
            ->tool(DeleteEntryTool::class, ['type' => 'medication', 'id' => $med->id, 'confirm' => true])
            ->assertOk();

        $this->assertSame(0, Medication::count());
        $this->assertSame(1, MedicationIntake::count());
        $this->assertNull(MedicationIntake::firstOrFail()->medication_id);
    }
}
