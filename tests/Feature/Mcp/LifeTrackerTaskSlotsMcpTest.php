<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\LifeTrackerServer;
use App\Mcp\Support\ToolCatalog;
use App\Mcp\Tools\Task\FindTaskSlotsTool;
use App\Mcp\Tools\Task\ListScheduleConflictsTool;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-145: huecos y cruces de agenda en el MCP. */
class LifeTrackerTaskSlotsMcpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-17 07:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function timed(User $user, string $title, string $start, string $end, array $extra = []): Task
    {
        return $user->tasks()->create(['title' => $title, 'start_date' => Carbon::parse($start, 'America/Bogota'), 'start_is_date' => false,
            'end_date' => Carbon::parse($end, 'America/Bogota'), 'end_is_date' => false, ...$extra]);
    }

    public function test_find_slots_respects_the_planning_hours_the_busy_blocks_and_the_task_duration(): void
    {
        $task = $this->user->tasks()->create(['title' => 'Preparar informe', 'estimated_time' => 90]);
        $this->timed($this->user, 'Reunión', '2026-11-17 10:00', '2026-11-17 11:00', ['schedule_locked' => true]);

        $response = LifeTrackerServer::actingAs($this->user)->tool(FindTaskSlotsTool::class, [
            'task_id' => $task->id, 'from' => '2026-11-17', 'to' => '2026-11-17',
        ]);

        $response->assertOk()->assertSee('"minutes":90', false)->assertSee('2026-11-17 08:00')->assertSee('America/Bogota', false)
            ->assertSee('Preparar informe')->assertDontSee('2026-11-17 10:00')->assertDontSee('2026-11-18');
    }

    public function test_find_slots_accepts_minutes_part_and_a_task_code_and_does_not_count_the_task_itself(): void
    {
        $task = $this->timed($this->user, 'Mover esta', '2026-11-17 09:00', '2026-11-17 10:00');

        LifeTrackerServer::actingAs($this->user)->tool(FindTaskSlotsTool::class, [
            'task_id' => $task->code, 'minutes' => 60, 'from' => '2026-11-17', 'to' => '2026-11-17', 'part' => 'morning',
        ])->assertOk()->assertSee('2026-11-17 08:00')->assertDontSee('2026-11-17 14:');
    }

    public function test_find_slots_validates_the_range_and_the_task(): void
    {
        LifeTrackerServer::actingAs($this->user)->tool(FindTaskSlotsTool::class, ['from' => '2026-11-17', 'to' => '2026-12-31'])
            ->assertHasErrors(['demasiado grande']);
        LifeTrackerServer::actingAs($this->user)->tool(FindTaskSlotsTool::class, ['task_id' => 'no-existe'])
            ->assertHasErrors(['No se encontró la tarea']);
    }

    public function test_find_slots_never_uses_another_users_data(): void
    {
        $other = User::factory()->create();
        $this->timed($other, 'Ajena', '2026-11-17 08:00', '2026-11-17 18:00');
        $foreign = $other->tasks()->create(['title' => 'Tarea ajena']);

        LifeTrackerServer::actingAs($this->user)->tool(FindTaskSlotsTool::class, ['from' => '2026-11-17', 'to' => '2026-11-17'])
            ->assertOk()->assertSee('2026-11-17 08:00');
        LifeTrackerServer::actingAs($this->user)->tool(FindTaskSlotsTool::class, ['task_id' => $foreign->id])
            ->assertHasErrors(['No se encontró la tarea']);
    }

    public function test_conflicts_group_overlaps_and_flag_what_cannot_move(): void
    {
        $this->timed($this->user, 'Reunión con cliente', '2026-11-17 09:00', '2026-11-17 10:30', ['schedule_locked' => true]);
        $this->timed($this->user, 'Preparar propuesta', '2026-11-17 10:00', '2026-11-17 11:00');
        $this->timed($this->user, 'Aparte', '2026-11-17 15:00', '2026-11-17 16:00');

        $response = LifeTrackerServer::actingAs($this->user)->tool(ListScheduleConflictsTool::class, ['from' => '2026-11-17', 'to' => '2026-11-17']);

        $response->assertOk()->assertSee('Reunión con cliente')->assertSee('Preparar propuesta')->assertSee('"movable":false', false)->assertSee('"movable":true', false)
            ->assertDontSee('Aparte');
    }

    public function test_conflicts_with_a_meal_are_reported_and_empty_ranges_say_so(): void
    {
        \App\Models\MealPlanEntry::create(['date' => '2026-11-17', 'meal_type' => 'almuerzo', 'status' => 'planned']);
        $this->timed($this->user, 'Entrevista', '2026-11-17 12:00', '2026-11-17 13:00');

        LifeTrackerServer::actingAs($this->user)->tool(ListScheduleConflictsTool::class, ['from' => '2026-11-17', 'to' => '2026-11-17'])
            ->assertOk()->assertSee('Entrevista')->assertSee('"source":"meal"', false);
        LifeTrackerServer::actingAs($this->user)->tool(ListScheduleConflictsTool::class, ['from' => '2026-11-24', 'to' => '2026-11-24'])
            ->assertOk()->assertSee('No hay tareas que se crucen');
        LifeTrackerServer::actingAs($this->user)->tool(ListScheduleConflictsTool::class, ['from' => '2026-11-17', 'to' => '2026-12-31'])
            ->assertHasErrors(['demasiado grande']);
    }

    public function test_the_tools_are_registered_in_the_server_catalog_as_read_only_task_tools(): void
    {
        $tools = collect(app(ToolCatalog::class)->tools())->keyBy('name');

        foreach (['find-task-slots-tool', 'list-schedule-conflicts-tool'] as $name) {
            $this->assertTrue($tools->has($name), $name);
            $this->assertSame('Task', $tools[$name]['module']);
            $this->assertTrue($tools[$name]['readOnly']);
        }
    }
}
