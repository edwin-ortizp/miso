<?php

namespace Tests\Feature\Engagement;

use App\Livewire\Home\WelcomeBack;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActiveDay;
use App\Services\Engagement\ReturnDetector;
use App\Services\Game\MissionProposalValidator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-072: volver sin culpa después de una ausencia. */
class WelcomeBackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function lastActive(string $date): void
    {
        UserActiveDay::touch($this->user->id, 'direct', $date);
    }

    private function overdueTask(string $title = 'Pagar factura'): Task
    {
        return Task::create(['title' => $title, 'completed' => false, 'end_date' => '2026-11-05 00:00:00']);
    }

    public function test_absence_of_three_days_or_more_shows_welcome(): void
    {
        $this->lastActive('2026-11-07');

        Livewire::test(WelcomeBack::class)->assertSee('Qué bueno verte')->assertSee('¿Retomamos con algo pequeño?');
    }

    public function test_visit_yesterday_shows_nothing(): void
    {
        $this->lastActive('2026-11-10');

        Livewire::test(WelcomeBack::class)->assertDontSee('Qué bueno verte');
    }

    public function test_new_user_without_history_shows_nothing(): void
    {
        $this->assertNull(app(ReturnDetector::class)->detect($this->user->id));
        Livewire::test(WelcomeBack::class)->assertDontSee('Qué bueno verte');
    }

    public function test_move_to_today_only_moves_tasks_left_behind_and_can_be_undone(): void
    {
        $this->lastActive('2026-11-01');
        $behind = $this->overdueTask();
        $future = Task::create(['title' => 'Viaje', 'completed' => false, 'end_date' => '2026-11-20 00:00:00']);

        $component = Livewire::test(WelcomeBack::class)->assertSee('1 tarea se quedó atrás')->call('moveToToday');

        $this->assertSame('2026-11-11', $behind->fresh()->end_date->toDateString());
        $this->assertSame('2026-11-20', $future->fresh()->end_date->toDateString());

        $component->call('undoLast');
        $this->assertSame('2026-11-05', $behind->fresh()->end_date->toDateString());
    }

    public function test_clear_dates_leaves_task_without_date(): void
    {
        $this->lastActive('2026-11-01');
        $task = $this->overdueTask();

        Livewire::test(WelcomeBack::class)->call('clearDates');

        $this->assertNull($task->fresh()->end_date);
        $this->assertFalse($task->fresh()->completed);
    }

    public function test_choose_date_rejects_past_dates(): void
    {
        $this->lastActive('2026-11-01');
        $task = $this->overdueTask();

        Livewire::test(WelcomeBack::class)->set('targetDate', '2026-11-01')->call('moveToDate')->assertHasErrors('targetDate');
        Livewire::test(WelcomeBack::class)->set('targetDate', '2026-11-14')->call('moveToDate');

        $this->assertSame('2026-11-14', $task->fresh()->end_date->toDateString());
    }

    public function test_dismissed_welcome_does_not_come_back_the_same_day(): void
    {
        $this->lastActive('2026-11-01');

        Livewire::test(WelcomeBack::class)->call('dismiss');
        Livewire::test(WelcomeBack::class)->call('dismiss');

        $this->assertSame(1, DB::table('welcome_backs')->count());
        Livewire::test(WelcomeBack::class)->assertDontSee('Qué bueno verte');
    }

    public function test_habits_link_opens_first_missed_day(): void
    {
        $this->lastActive('2026-11-06');

        Livewire::test(WelcomeBack::class)->assertSee('date=2026-11-07', false);
        $this->assertSame(5, app(ReturnDetector::class)->detect($this->user->id, CarbonImmutable::parse('2026-11-11'))['days_away']);
    }

    public function test_texts_carry_no_guilt(): void
    {
        foreach (['Qué bueno verte.', '¿Retomamos con algo pequeño?', '3 tareas se quedaron atrás', 'Listo, empezamos de nuevo.', '¿Hiciste algún hábito estos días? Márcalo'] as $text) {
            $this->assertNull(MissionProposalValidator::penaltyWord($text), $text);
        }
    }
}
