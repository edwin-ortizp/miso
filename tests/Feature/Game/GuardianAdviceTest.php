<?php

namespace Tests\Feature\Game;

use App\Models\DrinkLog;
use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\HealthEvent;
use App\Models\PomodoroSession;
use App\Models\HealthLog;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\Game\Guardian\GuardianAdvisor;
use App\Services\Game\Guardian\ExerciseAdvice;
use App\Services\Game\Guardian\HabitAdvice;
use App\Services\Game\Guardian\HealthAdvice;
use App\Services\Game\Guardian\PomodoroAdvice;
use App\Services\Game\Guardian\WaterAdvice;
use App\Services\Game\Guardian\TaskAdvice;
use App\Services\Game\MissionProposalValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CHG-057: consejos contextuales del guardián (reglas sin IA). */
class GuardianAdviceTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-11-10 10:00:00'; // martes

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::NOW));
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function categories(): void
    {
        $trabajo = TaskCategory::create(['key' => 'trabajo', 'name' => 'Trabajo']);
        TaskCategory::create(['key' => 'siigo', 'name' => 'Siigo', 'parent_id' => $trabajo->id]);
    }

    /** @return array<string, string> key => texto plano */
    private function tasks(): array
    {
        return collect(app(TaskAdvice::class)->candidates(now()))->mapWithKeys(fn ($c) => [$c['key'] => strip_tags(html_entity_decode($c['html']))])->all();
    }

    /** @return array<string, string> */
    private function of(string $provider): array
    {
        return collect(app($provider)->candidates(now()))->mapWithKeys(fn ($c) => [$c['key'] => strip_tags(html_entity_decode($c['html']))])->all();
    }

    private function drink(string $date, string $time, int $ml): void
    {
        DrinkLog::create(['date' => $date, 'time' => $time, 'timestamp' => 0, 'drink_type' => 'Agua', 'amount' => $ml, 'hydration_value' => $ml]);
    }

    public function test_water_left_pace_gap_and_streak(): void
    {
        auth()->user()->update(['daily_water_goal' => 2000]);
        $this->drink('2026-11-10', '06:30', 300);
        foreach ([1, 2] as $d) {
            $this->drink(now()->subDays($d)->toDateString(), '08:00', 2000);
        }

        $advice = $this->of(WaterAdvice::class);
        $this->assertSame('Te faltan 1,7 L para tu meta de hoy.', $advice['left']);
        $this->assertStringStartsWith('Vas 0,3 L; a esta hora sueles llevar', $advice['pace']);
        $this->assertSame('Llevas 3 h sin tomar agua; un vaso ahora ayuda.', $advice['gap']);
        $this->assertSame('2 días seguidos cumpliendo tu meta de agua.', $advice['streak']);
    }

    public function test_exercise_left_idle_forgotten_and_week(): void
    {
        $yoga = ExerciseType::create(['name' => 'Yoga']);
        foreach ([20, 25, 30] as $d) {
            ExerciseLog::create(['date' => now()->subDays($d)->toDateString(), 'exercise_type_id' => $yoga->id, 'duration' => 30]);
        }
        ExerciseLog::create(['date' => now()->subDays(3)->toDateString(), 'duration' => 10]);

        $advice = $this->of(ExerciseAdvice::class);
        $this->assertSame('Te faltan 30 min de movimiento para tu meta.', $advice['left']);
        $this->assertSame('Llevas 3 días sin moverte; ¿algo corto hoy?', $advice['idle']);
        $this->assertSame('Hace 20 días no haces Yoga; antes era de tus favoritos.', $advice['forgotten']);

        ExerciseLog::create(['date' => today()->toDateString(), 'duration' => 40]);
        $this->assertSame('Esta semana llevas 40 min, más que los 10 de la anterior.', $this->of(ExerciseAdvice::class)['week']);
    }

    public function test_pomodoro_left_top_slot_and_now(): void
    {
        foreach ([1, 2] as $d) {
            PomodoroSession::create(['date' => now()->subDays($d)->toDateString(), 'task_title' => 'Informe', 'duration' => 1500,
                'start_time' => ['timestamp' => now()->subDays($d)->setTime(9, 0)->timestamp], 'end_time' => [], 'completed' => true]);
        }
        Task::create(['title' => 'Daily', 'start_date' => now()->subMinutes(10), 'end_date' => now()->addMinutes(20), 'start_is_date' => false]);

        $advice = $this->of(PomodoroAdvice::class);
        $this->assertSame('Te faltan 300 min de foco para tu meta.', $advice['left']);
        $this->assertSame('Tu tarea con más foco esta semana: Informe (50 min).', $advice['top']);
        $this->assertSame('La mañana es tu mejor franja de foco: aprovéchala.', $advice['slot']);
        $this->assertSame('Ahora toca Daily; ¿un bloque de foco?', $advice['now']);
    }

    public function test_habits_coach_moment_risk_and_forgotten(): void
    {
        $caminar = HabitDefinition::create(['name' => 'Caminar', 'time_of_day' => 'morning', 'goal_duration' => '10']);
        HabitDefinition::create(['name' => 'Leer', 'time_of_day' => 'morning', 'goal_duration' => '30']);
        foreach ([1, 2, 3] as $d) {
            HabitCompletion::create(['habit_id' => $caminar->id, 'date' => now()->subDays($d)->toDateString(), 'completed' => true]);
        }

        $candidates = collect(app(HabitAdvice::class)->candidates(now()))->keyBy('key');
        $this->assertTrue($candidates->has('coach'));
        $advice = $this->of(HabitAdvice::class);
        $this->assertSame('Te faltan 2 de la mañana; Caminar es el más corto.', $advice['moment']);
        $this->assertSame('Caminar lleva 3 días seguidos; hoy mantiene la racha.', $advice['risk']);
        $this->assertSame('Leer se ha quedado 10 días este mes; ¿lo retomamos?', $advice['forgotten']);
    }

    /** @return array<string, string> */
    private function health(): array
    {
        return collect(app(HealthAdvice::class)->candidates(now()))->mapWithKeys(fn ($c) => [$c['key'] => strip_tags(html_entity_decode($c['html']))])->all();
    }

    public function test_no_task_advice_without_data(): void
    {
        $this->assertSame([], $this->tasks());
    }

    public function test_next_task_with_time(): void
    {
        Task::create(['title' => 'Daily Producto', 'start_date' => today()->setTime(10, 30), 'start_is_date' => false]);
        Task::create(['title' => 'Ya pasó', 'start_date' => today()->setTime(8, 0), 'start_is_date' => false]);

        $this->assertSame('Tu próxima es Daily Producto a las 10:30.', $this->tasks()['next']);
    }

    public function test_overload_against_hours_left_until_midnight(): void
    {
        Task::create(['title' => 'Grande', 'start_date' => today(), 'estimated_time' => 15 * 60]);

        $this->assertSame('Tienes 15 h planificadas y quedan 13 h 59 min de día: mueve algo.', $this->tasks()['overload']);
    }

    public function test_stale_overdue_in_one_category(): void
    {
        $this->categories();
        foreach (range(1, 3) as $i) {
            Task::create(['title' => "Vieja {$i}", 'category' => 'siigo', 'start_date' => today()->subDays(10)]);
        }
        Task::create(['title' => 'Reciente', 'category' => 'siigo', 'start_date' => today()->subDays(2)]);

        $this->assertSame('3 vencidas de Trabajo / Siigo llevan más de 7 días: ¿cerramos o movemos una?', $this->tasks()['stale']);
    }

    public function test_streak_beats_same_weekday_average(): void
    {
        foreach (range(1, 2) as $i) {
            Task::create(['title' => "Hecha {$i}", 'completed' => true, 'completed_at' => now()->subHour()]);
        }
        Task::create(['title' => 'Martes pasado', 'completed' => true, 'completed_at' => now()->subWeek()]);

        $this->assertSame('Ya cerraste 2 hoy, más que tus martes de costumbre.', $this->tasks()['streak']);
    }

    public function test_single_category_day(): void
    {
        $this->categories();
        foreach (['trabajo', 'siigo', 'siigo'] as $i => $category) {
            Task::create(['title' => "T{$i}", 'category' => $category, 'start_date' => today()]);
        }

        $this->assertSame('Hoy todo es Trabajo; ¿cabe algo distinto?', $this->tasks()['single']);
    }

    public function test_names_are_escaped(): void
    {
        Task::create(['title' => '<script>x</script>', 'start_date' => today()->setTime(11, 0), 'start_is_date' => false]);
        $html = collect(app(TaskAdvice::class)->candidates(now()))->firstWhere('key', 'next')['html'];

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<strong>&lt;script&gt;', $html);
    }

    public function test_health_followup_unlogged_next_and_calm(): void
    {
        $gripa = HealthEvent::create(['type' => 'illness', 'title' => 'Gripa', 'event_date' => today()->subDays(4)]);
        HealthLog::create(['health_event_id' => $gripa->id, 'date' => today()->subDays(3), 'intensity' => 4]);
        HealthEvent::create(['type' => 'appointment', 'title' => 'Cita', 'event_date' => today()->addDay()]);
        foreach ([1, 2, 3] as $d) {
            HealthEvent::create(['type' => 'symptom', 'title' => "S{$d}", 'event_date' => Carbon::parse('2026-09-0'.$d), 'end_date' => Carbon::parse('2026-09-0'.$d)]);
        }

        $advice = $this->health();
        $this->assertSame('Hoy toca seguimiento de Gripa (día 5).', $advice['followup']);
        $this->assertSame('Llevas 3 días sin anotar cómo sigue Gripa.', $advice['unlogged']);
        $this->assertSame('Próximo cuidado: Cita mañana.', $advice['next']);
        $this->assertStringStartsWith('Noviembre va con', $advice['calm']);
    }

    public function test_another_excludes_current_and_falls_back(): void
    {
        $advisor = app(GuardianAdvisor::class);
        $progress = ['done' => 0, 'total' => 0, 'care' => false];

        $this->assertSame('progress', $advisor->advise('tasks', now(), null, $progress)['key']);

        Task::create(['title' => 'Daily', 'start_date' => today()->setTime(11, 0), 'start_is_date' => false]);
        $this->assertSame('next', $advisor->advise('tasks', now(), 'next', $progress)['key'], 'con uno solo se mantiene');

        foreach (range(1, 2) as $i) {
            Task::create(['title' => "T{$i}", 'start_date' => today()]);
        }
        Task::create(['title' => 'Grande', 'start_date' => today(), 'estimated_time' => 20 * 60]);
        foreach (range(1, 20) as $_) {
            $this->assertNotSame('next', $advisor->advise('tasks', now(), 'next', $progress)['key']);
        }
    }

    public function test_templates_are_kind_and_short(): void
    {
        $sample = ['task' => 'Revisar correo', 'time' => '10:30', 'planned' => '6 h', 'left' => '4 h 30 min', 'count' => 12,
            'category' => 'Trabajo / Siigo', 'weekday' => 'miércoles', 'event' => 'Gripa', 'day' => 3, 'days' => 4,
            'when' => 'mañana', 'month' => 'Noviembre', 'peak' => 'septiembre', 'peak_count' => 3];
        $sample += ['left' => '1,7 L', 'now' => '0,8 L', 'usual' => '1,2 L', 'hours' => 3, 'minutes' => 120, 'type' => 'Natación',
            'last' => 90, 'slot' => 'mañana', 'moment' => 'mañana', 'habit' => 'Meditar 10 minutos'];
        foreach (['tasks', 'health', 'water', 'exercise', 'pomodoro', 'habits'] as $module) {
            foreach (config("game.guardian_advice.{$module}") as $text) {
                $filled = strtr($text, collect($sample)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
                $this->assertLessThanOrEqual(90, mb_strlen($filled), $filled);
                $this->assertNull(MissionProposalValidator::penaltyWord($filled), $filled);
            }
        }
    }
}
