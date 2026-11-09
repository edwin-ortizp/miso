<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\Game\RewardGranter;
use App\Services\HabitGamificationService;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardGranterTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'America/Bogota'));
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_completing_a_task_grants_one_pending_ball_with_its_origin(): void
    {
        $task = $this->completeTask('Pagar arriendo');

        $this->assertSame(1, GameEncounter::whereNull('opened_at')->count());
        $event = GameEvent::first();
        $this->assertSame($task->getMorphClass(), $event->source_type);
        $this->assertSame((string) $task->id, $event->source_id);
        $this->assertSame('Pagar arriendo', $event->source_label);
        $this->assertTrue($event->effect['ball']);
        $this->assertSame(5, $event->config_snapshot['daily_limit']);
    }

    public function test_complete_reopen_complete_and_retry_never_duplicates(): void
    {
        $task = $this->completeTask();
        $service = app(TaskGamificationService::class);
        $service->reopen($task);
        $service->complete($task->refresh());
        app(RewardGranter::class)->forTask($task->refresh());

        $this->assertSame(1, GameEncounter::count());
        $this->assertSame(1, GameEvent::count());
    }

    public function test_reopening_never_removes_a_granted_ball(): void
    {
        $task = $this->completeTask();
        app(TaskGamificationService::class)->reopen($task);

        $this->assertSame(1, GameEncounter::count());
    }

    public function test_daily_limit_records_event_without_ball_and_without_error(): void
    {
        $this->gameSettings(['daily_limit' => 2]);
        foreach (['A', 'B', 'C'] as $title) {
            $this->completeTask($title);
        }

        $this->assertSame(2, GameEncounter::count());
        $this->assertSame(3, GameEvent::count());
        $this->assertSame('daily_limit', GameEvent::latest('id')->first()->effect['reason']);
    }

    public function test_limit_resets_the_next_day(): void
    {
        $this->gameSettings(['daily_limit' => 1]);
        $this->completeTask('Hoy');
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'America/Bogota'));
        $this->completeTask('Mañana');

        $this->assertSame(2, GameEncounter::count());
    }

    public function test_disabled_game_or_source_grants_nothing(): void
    {
        $this->gameSettings(['enabled' => false]);
        $this->completeTask('A');
        $this->gameSettings(['enabled' => true, 'sources' => ['tasks' => false, 'habits' => true]]);
        $this->completeTask('B');

        $this->assertSame(0, GameEncounter::count());
    }

    public function test_only_completing_the_whole_period_grants_a_ball(): void
    {
        $walk = $this->habit('Caminar');
        $stretch = $this->habit('Estirar');
        $read = \App\Models\HabitDefinition::create(['name' => 'Leer', 'time_of_day' => 'night']);
        $service = app(HabitGamificationService::class);

        $service->toggle($walk->id, '2026-11-09');
        $this->assertSame(0, GameEncounter::count());

        $service->toggle($stretch->id, '2026-11-09');
        $this->assertSame(1, GameEncounter::count());
        $this->assertSame('Hábitos de la mañana', GameEvent::first()->source_label);

        $service->toggle($read->id, '2026-11-09');
        $this->assertSame(2, GameEncounter::count());
    }

    public function test_redoing_a_completed_period_the_same_day_does_not_duplicate(): void
    {
        $walk = $this->habit('Caminar');
        $service = app(HabitGamificationService::class);
        $service->toggle($walk->id, '2026-11-09');
        $service->toggle($walk->id, '2026-11-09');
        $service->toggle($walk->id, '2026-11-09');

        $this->assertSame(1, GameEncounter::count());
    }

    public function test_deleted_source_keeps_its_label(): void
    {
        $task = $this->completeTask('Llamar a mamá');
        Task::whereKey($task->id)->delete();

        $this->assertSame('Llamar a mamá', GameEvent::first()->source_label);
        $this->assertSame(1, GameEncounter::count());
    }

    public function test_empty_catalog_does_not_break_task_completion(): void
    {
        \App\Models\Game\GameSpecies::query()->delete();
        $task = $this->completeTask();

        $this->assertTrue((bool) $task->completed);
        $this->assertSame(0, GameEncounter::count());
    }

    public function test_completing_a_recurrent_task_grants_a_ball_per_occurrence(): void
    {
        $task = Task::create(['title' => 'Regar plantas', 'start_date' => today(), 'is_recurrent' => true, 'recurrence' => ['frequency' => 'daily', 'interval' => 1]]);
        $recurrence = app(\App\Services\TaskRecurrenceService::class);
        $gamification = app(TaskGamificationService::class);

        $recurrence->completeAndScheduleAutomatically($task->refresh(), $gamification);
        $this->assertSame(1, GameEncounter::count());
        $this->assertSame('Regar plantas', GameEvent::first()->source_label);

        $recurrence->completeAndScheduleAutomatically($task->refresh(), $gamification);
        $this->assertSame(2, GameEncounter::count());
    }

    public function test_moving_a_task_to_a_done_status_grants_a_ball(): void
    {
        $task = Task::create(['title' => 'Kanban', 'start_date' => today()]);
        $done = \App\Models\TaskStatus::ensureFor($task->user_id)->firstWhere('type', \App\Models\TaskStatus::DONE);

        $task->update(['status_id' => $done->id]);

        $this->assertTrue((bool) $task->refresh()->completed);
        $this->assertSame(1, GameEncounter::count());
    }

    public function test_balls_are_isolated_between_users(): void
    {
        $this->completeTask();
        $this->actingAs(User::factory()->create());

        $this->assertSame(0, GameEncounter::count());
    }
}
