<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameMission;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameWeek;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\WeekPlanner;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-048: las tareas de la categoría Personal dan constancia (+1 xp) en vez de ball. */
class PersonalTaskConsistencyTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private GameWeek $week;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
        TaskCategory::create(['name' => 'Personal', 'key' => 'personal']);
        TaskCategory::create(['name' => 'Hogar', 'key' => 'hogar']);

        $species = GameSpecies::where('name', 'Gotin')->first();
        foreach (['a', 'b'] as $line) {
            GameCompanion::create(['line_key' => $line, 'species_id' => $species->id, 'first_met_at' => now()]);
        }
        $this->week = app(WeekPlanner::class)->forWeek(auth()->id(), now());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function completeIn(string $category, string $title): Task
    {
        $task = Task::create(['title' => $title, 'start_date' => today(), 'category' => $category]);
        app(TaskGamificationService::class)->complete($task);

        return $task->refresh();
    }

    /** @return array<int, int> */
    private function teamXp(): array
    {
        return GameCompanion::orderBy('id')->pluck('xp')->map(fn ($xp) => (int) $xp)->all();
    }

    public function test_personal_task_gives_one_xp_and_no_ball(): void
    {
        $before = $this->teamXp();
        $this->completeIn('personal', 'Llamar a mamá');

        $this->assertSame(0, GameEncounter::count());
        $this->assertSame(array_map(fn ($xp) => $xp + 1, $before), $this->teamXp());
        $this->assertSame('Constancia · Tareas personales', GameEvent::where('idempotency_key', 'micro:personal_tasks:2026-11-11')->value('source_label'));
    }

    public function test_second_personal_task_same_day_adds_nothing(): void
    {
        $this->completeIn('personal', 'Llamar a mamá');
        $after = $this->teamXp();
        $this->completeIn('personal', 'Tiempo con Alison');

        $this->assertSame($after, $this->teamXp());
        $this->assertSame(0, GameEncounter::count());
    }

    public function test_personal_task_does_not_advance_missions(): void
    {
        $mission = GameMission::create(['game_week_id' => $this->week->id, 'type' => GameMission::TYPE_CATEGORY, 'title' => 'Personal', 'params' => ['category' => 'personal'], 'target' => 2]);
        $this->completeIn('personal', 'Llamar a mamá');

        $this->assertSame(0, (int) $mission->refresh()->progress);
    }

    public function test_other_categories_still_give_a_ball(): void
    {
        $this->completeIn('hogar', 'Barrer');

        $this->assertSame(1, GameEncounter::count());
    }

    public function test_switch_off_gives_a_ball_again(): void
    {
        $this->gameSettings(['sources' => ['personal_tasks' => false]]);
        $this->completeIn('personal', 'Llamar a mamá');

        $this->assertSame(1, GameEncounter::count());
        $this->assertSame(0, GameEvent::where('idempotency_key', 'like', 'micro:%')->count());
    }
}
