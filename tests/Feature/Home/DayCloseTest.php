<?php

namespace Tests\Feature\Home;

use App\Livewire\Home\DayClose;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActiveDay;
use App\Services\Game\DailyChallenge;
use App\Services\Game\Streak;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CHG-070: cierre del día. */
class DayCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 20:00:00', 'America/Bogota'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(string $title, ?string $end, array $extra = []): Task
    {
        return Task::create(['title' => $title, 'completed' => false, 'end_date' => $end] + $extra);
    }

    private function fakeGame(?array $challenge, ?array $streak): void
    {
        $this->mock(DailyChallenge::class, fn ($m) => $m->shouldReceive('forToday')->andReturn($challenge));
        $this->mock(Streak::class, fn ($m) => $m->shouldReceive('summary')->andReturn($streak));
    }

    public function test_page_renders_and_entering_with_via_close_marks_channel(): void
    {
        $this->get('/cierre?via=close')->assertOk()->assertSee('Cerrar el día')->assertSee('Así te fue hoy');

        $this->assertSame('close', UserActiveDay::where('user_id', $this->user->id)->first()->first_channel);
    }

    public function test_summary_shows_goals_with_progress_and_hides_modules_without_goal(): void
    {
        $done = \App\Models\TaskStatus::ensureFor($this->user->id)->firstWhere('type', \App\Models\TaskStatus::DONE);
        $this->task('Hecha hoy', '2026-11-11 18:00:00', ['status_id' => $done->id]);
        $this->task('Pendiente hoy', '2026-11-11 18:00:00');

        Livewire::test(DayClose::class)
            ->assertSee('Agua')
            ->assertSee('Ejercicio')
            ->assertSee('Tareas')
            ->assertSee('1/2')->assertSee('bi-droplet', false)->assertSee('de 4 metas')
            ->assertDontSee('Hábitos');
    }

    public function test_challenge_and_streak_blocks_show_when_available(): void
    {
        $this->fakeGame(['key' => 'water', 'text' => 'Toma 3 vasos', 'done' => 3, 'target' => 3, 'completed' => true, 'url' => null, 'checkin' => false],
            ['days' => 6, 'best' => 6, 'today' => 3, 'today_areas' => 2, 'goal' => 3, 'min_areas' => 2, 'met_today' => true, 'grace_left' => 1, 'grace_total' => 1, 'month' => '2026-11', 'last7' => [], 'broken' => false]);

        Livewire::test(DayClose::class)
            ->assertSee('Reto de hoy')->assertSee('Cumplido. Mañana hay otro.')->assertSee('Toma 3 vasos')
            ->assertSee('6 días');
    }

    public function test_challenge_and_streak_blocks_hide_without_error(): void
    {
        $this->fakeGame(null, null);

        Livewire::test(DayClose::class)->assertOk()->assertDontSee('Reto de hoy')->assertDontSee('game-day-streak', false);
    }

    public function test_only_tasks_due_today_are_pending(): void
    {
        $this->task('Llamar al banco', '2026-11-11 15:00:00');
        $this->task('Vieja', '2026-11-09 15:00:00');
        $this->task('Sin fecha', null);
        $this->task('Mañana ya', '2026-11-12 15:00:00');
        $this->task('Historial', '2026-11-11 15:00:00', ['is_recurrence_history' => true]);

        Livewire::test(DayClose::class)
            ->assertSee('Quedó pendiente (1)')
            ->assertSee('Llamar al banco')
            ->assertDontSee('Vieja')->assertDontSee('Sin fecha')->assertDontSee('Mañana ya')->assertDontSee('Historial');
    }

    public function test_move_all_keeps_time_and_duration_and_undo_restores(): void
    {
        $a = $this->task('A', '2026-11-11 15:00:00', ['start_date' => '2026-11-11 14:00:00']);
        $b = $this->task('B', '2026-11-11 09:30:00');

        $component = Livewire::test(DayClose::class)->call('moveAll')
            ->assertSee('Listo, quedaron para mañana.')
            ->assertSee('Deshacer')
            ->assertSee('No quedan tareas pendientes en este cierre.');

        $this->assertSame('2026-11-12 14:00:00', $a->fresh()->start_date->toDateTimeString());
        $this->assertSame('2026-11-12 15:00:00', $a->fresh()->end_date->toDateTimeString());
        $this->assertSame('2026-11-12 09:30:00', $b->fresh()->end_date->toDateTimeString());

        $component->call('undoLast')->assertSee('Deshecho. Las tareas quedaron como estaban.');
        $this->assertSame('2026-11-11 15:00:00', $a->fresh()->end_date->toDateTimeString());
        $this->assertSame('2026-11-11 09:30:00', $b->fresh()->end_date->toDateTimeString());
    }

    public function test_move_one_only_moves_that_task(): void
    {
        $a = $this->task('A', '2026-11-11 15:00:00');
        $b = $this->task('B', '2026-11-11 16:00:00');

        Livewire::test(DayClose::class)->call('moveOne', (string) $a->id)->assertSee('Quedó pendiente (1)');

        $this->assertSame('2026-11-12', $a->fresh()->end_date->toDateString());
        $this->assertSame('2026-11-11', $b->fresh()->end_date->toDateString());
    }

    public function test_all_goals_met_shows_praise(): void
    {
        $this->mock(\App\Services\Game\Guardian\ModuleProgress::class, fn ($m) => $m->shouldReceive('for')->andReturnUsing(fn (string $module) => ['done' => 2, 'total' => $module === 'tasks' ? 0 : 2, 'care' => false]));

        Livewire::test(DayClose::class)->assertSee('Completaste las metas de este resumen. Descansa.')->assertSee('5 de 5 metas');
    }

    public function test_finish_closes_day_once_and_shows_closed_state(): void
    {
        Livewire::test(DayClose::class)->assertSee('Listo, terminé el día')->call('finish')->assertRedirect(route('home'));

        $day = UserActiveDay::where('user_id', $this->user->id)->first();
        $this->assertNotNull($day->closed_at);
        $first = $day->closed_at->toDateTimeString();

        Carbon::setTestNow(now()->addMinutes(5));
        UserActiveDay::closeDay($this->user->id, '2026-11-11');
        $this->assertSame($first, $day->fresh()->closed_at->toDateTimeString());

        Livewire::test(DayClose::class)
            ->assertSee('Ya cerraste el día. Descansa.')
            ->assertSee('game-buddy-hero--night', false)
            ->assertDontSee('Listo, terminé el día');
    }

    public function test_home_hero_turns_night_and_invites_to_close_from_seven_pm_until_closed(): void
    {
        $entry = 'data-testid="day-close-entry"';
        Carbon::setTestNow(Carbon::parse('2026-11-11 18:59:00', 'America/Bogota'));
        Livewire::test(\App\Livewire\Home\Dashboard::class)->assertDontSee($entry, false)->assertDontSee('game-buddy-hero--night', false);

        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'America/Bogota'));
        Livewire::test(\App\Livewire\Home\Dashboard::class)
            ->assertSee($entry, false)
            ->assertSee('game-buddy-hero--night', false)
            ->assertSee(route('day.close', ['via' => 'close']), false);

        UserActiveDay::closeDay($this->user->id, '2026-11-11');
        Livewire::test(\App\Livewire\Home\Dashboard::class)->assertDontSee($entry, false)->assertSee('game-buddy-hero--night', false);

        Carbon::setTestNow(Carbon::parse('2026-11-12 19:00:00', 'America/Bogota'));
        Livewire::test(\App\Livewire\Home\Dashboard::class)->assertSee($entry, false);
    }

    public function test_menu_does_not_duplicate_my_day(): void
    {
        $this->assertNull(collect(config('modules.navigation.overview.items'))->firstWhere('route', 'day.close'));
    }

    public function test_checkin_challenge_link_goes_home(): void
    {
        Livewire::test(DayClose::class)->set('checkin', true)->assertRedirect(route('home'));
    }

    public function test_streak_without_days_but_with_actions_today_says_how_much_is_left(): void
    {
        $this->fakeGame(null, ['days' => 0, 'best' => 0, 'today' => 1, 'today_areas' => 1, 'goal' => 3, 'min_areas' => 2, 'met_today' => false, 'grace_left' => 1, 'grace_total' => 1, 'month' => '2026-11', 'last7' => [], 'broken' => false]);

        Livewire::test(DayClose::class)
            ->assertSee('A 2 acciones de tu racha')
            ->assertSee('Llevas 1 de 3 hoy. Aún estás a tiempo.')
            ->assertDontSee('Empieza hoy');
    }

    public function test_pending_goes_in_the_wide_column_only_when_there_are_tasks(): void
    {
        Livewire::test(DayClose::class)->assertDontSee('day-close__grid--pending', false);

        $this->task('Llamar al banco', '2026-11-11 15:00:00');
        Livewire::test(DayClose::class)->assertSee('day-close__grid--pending', false);
    }
}
