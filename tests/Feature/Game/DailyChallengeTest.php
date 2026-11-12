<?php

namespace Tests\Feature\Game;

use App\Models\DrinkLog;
use App\Models\EnergyEntry;
use App\Models\Game\GameDailyChallenge;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\HabitCompletion;
use App\Models\MoodEntry;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\DailyChallenge;
use App\Services\Game\MissionProposalValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-077: reto del día determinista, con progreso derivado y premio único. */
class DailyChallengeTest extends TestCase
{
    use GameTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->at('2026-11-11 10:00:00');
        $this->actingAs(User::factory()->create());
        $this->seedSpecies();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, 'America/Bogota'));
    }

    private function only(string ...$templates): void
    {
        config(['game.daily_challenge.templates' => $templates]);
    }

    private function today(): ?array
    {
        return app(DailyChallenge::class)->forToday(auth()->id());
    }

    /** Tareas pendientes creadas antes de hoy. */
    private function oldTasks(int $count, ?string $category = null): array
    {
        $tasks = [];
        foreach (range(1, $count) as $i) {
            $task = Task::create(['title' => "Tarea {$i}", 'category' => $category]);
            $task->forceFill(['created_at' => now()->subDays(3)])->save();
            $tasks[] = $task;
        }

        return $tasks;
    }

    private function drink(): void
    {
        DrinkLog::create(['date' => today(), 'drink_type' => 'agua', 'amount' => 250, 'hydration_value' => 250, 'time' => '09:00', 'timestamp' => now()]);
    }

    private function close(Task $task): void
    {
        $task->update(['completed' => true, 'completed_at' => now()]);
    }

    public function test_one_challenge_is_chosen_and_kept_all_day(): void
    {
        $this->oldTasks(3);
        $first = $this->today();
        $this->at('2026-11-11 18:00:00');
        $second = $this->today();

        $this->assertNotNull($first);
        $this->assertSame($first['text'], $second['text']);
        $this->assertSame(1, GameDailyChallenge::count());
    }

    public function test_closing_two_tasks_completes_and_pays_once(): void
    {
        $this->only('tasks_done');
        config(['game.daily_challenge.targets.tasks_done' => [2, 2]]);
        [$a, $b] = $this->oldTasks(3);

        $this->assertSame('Cierra 2 tareas', $this->today()['text']);
        $this->close($a);
        $this->assertSame(1, $this->today()['done']);
        $this->close($b);

        $state = $this->today();
        $this->assertTrue($state['completed']);
        $this->today();

        $this->assertSame(1, GameEvent::where('kind', GameEvent::KIND_BONUS)->where('idempotency_key', 'daily-challenge:2026-11-11')->count());
        $this->assertSame(1, GameEncounter::whereHas('event', fn ($q) => $q->withoutGlobalScopes()->where('kind', GameEvent::KIND_BONUS))->count());
    }

    public function test_task_created_and_closed_today_does_not_advance(): void
    {
        $this->only('tasks_done');
        config(['game.daily_challenge.targets.tasks_done' => [2, 2]]);
        $this->oldTasks(2);
        $this->today();

        $farm = Task::create(['title' => 'Rápida']);
        $this->close($farm);
        $planned = Task::create(['title' => 'Planeada', 'start_date' => today()]);
        $this->close($planned);

        $this->assertSame(1, $this->today()['done']);
    }

    public function test_category_challenge_uses_the_category_with_most_pending(): void
    {
        $this->only('category_tasks');
        TaskCategory::create(['key' => 'trabajo', 'name' => 'Trabajo']);
        TaskCategory::create(['key' => 'casa', 'name' => 'Casa']);
        $this->oldTasks(3, 'trabajo');
        $this->oldTasks(1, 'casa');

        $state = $this->today();
        $this->assertSame('Cierra 2 de Trabajo', $state['text']);
    }

    public function test_habit_period_counts_the_period_habits(): void
    {
        $this->only('habit_period');
        $walk = $this->habit('Caminar');
        $read = $this->habit('Leer');

        $this->assertSame('Completa los hábitos de la mañana', $this->today()['text']);
        HabitCompletion::create(['habit_id' => $walk->id, 'date' => today(), 'completed' => true]);
        $this->assertSame(1, $this->today()['done']);
        $this->assertSame(2, $this->today()['target']);
    }

    public function test_water_and_checkin_progress(): void
    {
        $this->only('water_logs');
        config(['game.daily_challenge.targets.water_logs' => [2, 2]]);
        $this->drink();
        $this->assertSame(1, $this->today()['done']);

        GameDailyChallenge::query()->delete();
        $this->only('checkin');
        $this->assertSame('Haz tu check-in de hoy', $this->today()['text']);
        $this->assertTrue($this->today()['checkin']);
    }

    public function test_unavailable_templates_are_not_chosen(): void
    {
        $this->only('habit_period', 'category_tasks', 'checkin');
        MoodEntry::factory()->create(['user_id' => auth()->id(), 'date' => today()->toDateString()]);
        EnergyEntry::create(['date' => today(), 'level' => 3, 'time' => '08:00', 'timestamp' => now()]);

        $this->assertNull($this->today(), 'Sin hábitos, sin pendientes y con check-in hecho no hay reto.');
    }

    public function test_a_disabled_template_stored_today_is_replaced_unless_completed(): void
    {
        $this->only('checkin');
        $this->assertTrue($this->today()['checkin']);

        $this->only('water_logs');
        $this->assertSame('water_logs', $this->today()['key']);
        $this->assertSame('water_logs', GameDailyChallenge::sole()->key);

        GameDailyChallenge::sole()->update(['completed_at' => now()]);
        $this->only('tasks_done');
        $this->assertSame('water_logs', $this->today()['key'], 'Un reto cumplido se conserva.');
    }

    /** CHG-089: con las plantillas por defecto nunca sale check-in y el reto varía entre días. */
    public function test_default_templates_vary_and_never_pick_checkin(): void
    {
        config(['game.daily_challenge.templates' => (require config_path('game.php'))['daily_challenge']['templates']]);
        TaskCategory::create(['key' => 'trabajo', 'name' => 'Trabajo']);
        $this->oldTasks(4, 'trabajo');
        $keys = [];
        foreach (range(0, 29) as $day) {
            $this->at(Carbon::parse('2026-11-12 10:00:00', 'America/Bogota')->addDays($day)->toDateTimeString());
            $keys[] = $this->today()['key'];
        }
        $this->assertNotContains('checkin', $keys);
        $this->assertGreaterThan(1, count(array_unique($keys)));
    }

    public function test_next_day_brings_a_new_challenge(): void
    {
        $this->only('water_logs');
        $this->today();
        $this->at('2026-11-12 09:00:00');
        $this->today();

        $this->assertSame(2, GameDailyChallenge::count());
    }

    public function test_switching_it_off_hides_and_pays_nothing(): void
    {
        $this->gameSettings(['daily_challenge' => false]);

        $this->assertNull($this->today());
        $this->assertSame(0, GameDailyChallenge::count());
    }

    public function test_the_most_neglected_area_is_prioritized(): void
    {
        $this->only('water_logs', 'checkin');
        // Agua al día esta semana; check-in nunca: el reto empuja el check-in.
        foreach (range(1, 9) as $i) {
            $this->drink();
        }

        $this->assertSame('checkin', $this->today()['checkin'] ? 'checkin' : 'water');
    }

    public function test_texts_fit_and_have_no_penalty_words(): void
    {
        $texts = ['Reto de hoy · Uno al día', 'Cierra 3 tareas', 'Cierra 2 de Trabajo y estudio', 'Completa los hábitos de cualquier momento',
            'Registra agua 3 veces', 'Haz tu check-in de hoy', '1 de 2 · Te falta 1', 'Cumplido. Mañana hay otro.',
            '¡Reto del día cumplido!', 'Ganaste una ball.', 'Un reto pequeño cada día, con una ball al cumplirlo.'];
        foreach ($texts as $text) {
            $this->assertLessThanOrEqual(90, mb_strlen($text), $text);
            $this->assertNull(MissionProposalValidator::penaltyWord($text), $text);
        }
    }
}
