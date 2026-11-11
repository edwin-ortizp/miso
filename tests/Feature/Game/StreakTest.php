<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\Game\MissionProposalValidator;
use App\Services\Game\Streak;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** CHG-076 · T1/T2: racha con umbral, dos áreas, gracias derivadas e hitos. */
class StreakTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    private const TODAY = '2026-11-20';

    protected function setUp(): void
    {
        parent::setUp();
        $this->now(self::TODAY);
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function now(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 10:00:00', 'America/Bogota'));
    }

    /** Registra una acción del juego en $date (también suma en user_active_days). */
    private function act(string $date, string $source, ?string $key = null, array $attributes = []): void
    {
        $this->now($date);
        GameEvent::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => auth()->id(), 'idempotency_key' => $key ?? Str::uuid()->toString()],
            ['kind' => GameEvent::KIND_ACTIVITY, 'source_label' => 'x', 'effect' => ['source' => $source, 'ball' => false], 'occurred_at' => now()] + $attributes,
        );
        $this->now(self::TODAY);
    }

    /** Un día cumplido con el umbral por defecto (3 acciones en 2 áreas). */
    private function fullDay(string $date): void
    {
        $this->act($date, 'tasks');
        $this->act($date, 'habits');
        $this->act($date, 'habits');
    }

    private function summary(): array
    {
        return app(Streak::class)->summary(auth()->id());
    }

    public function test_three_actions_in_two_areas_complete_today(): void
    {
        $this->fullDay(self::TODAY);

        $summary = $this->summary();
        $this->assertTrue($summary['met_today']);
        $this->assertSame(1, $summary['days']);
        $this->assertSame(3, $summary['today']);
        $this->assertSame(3, $summary['goal']);
    }

    public function test_one_area_alone_does_not_complete_the_day(): void
    {
        foreach (range(1, 3) as $_) {
            $this->act(self::TODAY, 'health');
        }

        $summary = $this->summary();
        $this->assertSame(3, $summary['today']);
        $this->assertSame(1, $summary['today_areas']);
        $this->assertFalse($summary['met_today']);
        $this->assertSame(0, $summary['days']);
    }

    public function test_the_same_action_counts_once(): void
    {
        $this->act(self::TODAY, 'tasks', 'task:1:'.self::TODAY);
        $this->act(self::TODAY, 'tasks', 'task:1:'.self::TODAY);

        $this->assertSame(1, $this->summary()['today']);
    }

    public function test_task_created_and_closed_today_without_date_does_not_count(): void
    {
        $farm = Task::create(['title' => 'Tarea rápida']);
        $planned = Task::create(['title' => 'Planeada', 'start_date' => today()]);
        $this->act(self::TODAY, 'tasks', null, ['source_type' => 'task', 'source_id' => (string) $farm->id]);
        $this->act(self::TODAY, 'tasks', null, ['source_type' => 'task', 'source_id' => (string) $planned->id]);

        $this->assertSame(1, $this->summary()['today']);
    }

    public function test_consecutive_days_build_the_streak_and_today_never_breaks_it(): void
    {
        $this->fullDay('2026-11-18');
        $this->fullDay('2026-11-19');

        $summary = $this->summary();
        $this->assertSame(2, $summary['days']);
        $this->assertFalse($summary['met_today']);
        $this->assertSame('today', end($summary['last7'])['status']);
    }

    public function test_a_missed_day_uses_a_grace_and_keeps_the_streak(): void
    {
        $this->fullDay('2026-11-17');
        $this->act('2026-11-18', 'tasks');
        $this->fullDay('2026-11-19');

        $summary = $this->summary();
        $this->assertSame(2, $summary['days']);
        $this->assertSame(1, $summary['grace_left']);
        $statuses = array_column($summary['last7'], 'status', 'date');
        $this->assertSame('grace', $statuses['2026-11-18']);
    }

    public function test_without_graces_the_streak_restarts_and_keeps_the_best(): void
    {
        foreach (['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-19'] as $date) {
            $this->fullDay($date);
        }
        // Del 13 al 18 sin cumplir: dos gracias de noviembre y el tercer día corta.

        $summary = $this->summary();
        $this->assertSame(1, $summary['days']);
        $this->assertSame(3, $summary['best']);
        $this->assertSame(0, $summary['grace_left']);
    }

    public function test_changing_the_threshold_applies_from_today(): void
    {
        $this->fullDay('2026-11-19');
        $this->assertSame(1, $this->summary()['days']);

        $this->gameSettings(['streak_threshold' => 5]);
        $this->fullDay(self::TODAY);

        $summary = $this->summary();
        $this->assertSame(1, $summary['days'], 'Ayer sigue cumplido; hoy necesita 5.');
        $this->assertFalse($summary['met_today']);
        $this->assertSame(5, $summary['goal']);
    }

    public function test_hidden_streak_or_disabled_game_shows_nothing(): void
    {
        $this->gameSettings(['streak_visible' => false]);
        $this->assertNull(app(Streak::class)->summary(auth()->id()));

        $this->gameSettings(['streak_visible' => true, 'enabled' => false]);
        $this->assertNull(app(Streak::class)->summary(auth()->id()));
    }

    public function test_milestone_pays_one_ball_once_even_after_a_restart(): void
    {
        foreach (['2026-11-17', '2026-11-18', '2026-11-19'] as $date) {
            $this->fullDay($date);
        }
        $streak = app(Streak::class);
        $streak->awardMilestones(auth()->id(), $this->summary());
        $streak->awardMilestones(auth()->id(), $this->summary());

        $bonus = GameEvent::where('kind', GameEvent::KIND_BONUS)->where('idempotency_key', 'streak:3')->get();
        $this->assertCount(1, $bonus);
        $this->assertSame(1, GameEncounter::count());
        $this->assertStringContainsString('3 días seguidos', $bonus->first()->effect['title']);
        $this->assertStringContainsString('con hábitos', $bonus->first()->effect['detail']);

        $streak->awardMilestones(auth()->id(), ['days' => 3] + $this->summary());
        $this->assertSame(1, GameEncounter::count());
    }

    public function test_texts_have_no_penalty_words_and_fit(): void
    {
        $texts = [
            'Tu racha', '12 días', 'Hoy: 2 de 3 acciones', 'Hoy ya está: 3 de 3 acciones', 'Suma otra área para cumplir el día.',
            'Hoy empieza tu racha: te faltan 3 acciones.', 'Gracia: 1 de 2 en noviembre', 'Tu mejor racha fue de 21 días. Hoy empieza otra.',
            'Tu mejor racha: 21 días', '¡365 días seguidos cuidándote!', '5 días con hábitos, 4 con tareas. Ganaste una ball.',
            'Acciones para cumplir el día', 'Entre 1 y 10, en al menos dos áreas distintas (tareas, hábitos, salud…).',
            'Cada día necesitas 3 acciones. Si un día no llegas, se usa una gracia (2 por mes).',
        ];
        foreach ($texts as $text) {
            $this->assertLessThanOrEqual(90, mb_strlen($text), $text);
            $this->assertNull(MissionProposalValidator::penaltyWord($text), $text);
        }
    }
}
