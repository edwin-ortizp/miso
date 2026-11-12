<?php

namespace App\Livewire\Home;

use App\Actions\Task\ShiftTasksToDate;
use App\Models\Task;
use App\Models\UserActiveDay;
use App\Services\Game\DailyBuddy;
use App\Services\Game\DailyChallenge;
use App\Services\Game\Guardian\ModuleProgress;
use App\Services\Game\ModuleGuardian;
use App\Services\Game\Streak;
use App\Services\Notifications\NotificationSource;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CHG-070: cierre del día. Resumen de las metas diarias, el reto y la racha, y
 * pasar a mañana las tareas que vencen hoy. Sin recompensa y sin culpa.
 */
#[Layout('layouts.app')]
#[Title('Cerrar el día')]
class DayClose extends Component
{
    /** Metas diarias que se resumen, en orden de lectura. */
    /** Metas diarias que se resumen, en orden de lectura. Ícono y color salen del módulo (config/modules.php y su acento). */
    public const MODULES = [
        'water' => ['label' => 'Agua', 'unit' => 'ml', 'route' => 'water.daily'],
        'exercise' => ['label' => 'Ejercicio', 'unit' => 'min', 'route' => 'exercise'],
        'pomodoro' => ['label' => 'Foco', 'unit' => 'min', 'route' => 'pomodoro'],
        'habits' => ['label' => 'Hábitos', 'unit' => '', 'route' => 'habits'],
        'tasks' => ['label' => 'Tareas', 'unit' => '', 'route' => 'tasks.list'],
    ];

    /** @var array<int|string, array{start: ?string, end: ?string}> fechas previas para Deshacer */
    public array $undo = [];

    public string $message = '';

    /** El reto «check-in» del hero pide abrir el check-in; ese vive en Inicio. */
    public bool $checkin = false;

    public function updatedCheckin(): void
    {
        $this->redirectRoute('home', navigate: true);
    }

    public function moveAll(): void
    {
        $this->move($this->pending()->get());
    }

    public function moveOne(string $taskId): void
    {
        $this->move($this->pending()->whereKey($taskId)->get());
    }

    public function undoLast(): void
    {
        ShiftTasksToDate::restore($this->undo);
        $this->undo = [];
        $this->message = 'Deshecho. Las tareas quedaron como estaban.';
    }

    public function finish()
    {
        UserActiveDay::closeDay((int) auth()->id(), self::today()->toDateString());

        return $this->redirectRoute('home', navigate: true);
    }

    private function move($tasks): void
    {
        if ($tasks->isEmpty()) {
            return;
        }
        $this->undo = ShiftTasksToDate::handle($tasks, self::today()->addDay());
        $this->message = 'Listo, quedaron para mañana.';
    }

    /** Tareas sin completar cuya fecha límite cae hoy (las de días anteriores no entran). */
    private function pending()
    {
        $day = [self::today()->startOfDay(), self::today()->endOfDay()];

        return Task::query()
            ->where('completed', false)
            ->where('is_recurrence_history', false)
            ->where(fn ($q) => $q->whereBetween('end_date', $day)
                ->orWhere(fn ($q) => $q->whereNull('end_date')->whereBetween('start_date', $day)));
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(NotificationSource::TIMEZONE)->startOfDay();
    }

    /** @return list<array{key: string, label: string, unit: string, icon: string, href: string, done: int, total: int, met: bool, percent: int, guardian: ?array}> */
    private function goals(ModuleProgress $progress, ModuleGuardian $guardians, int $userId): array
    {
        $goals = [];
        foreach (self::MODULES as $key => $meta) {
            $p = $progress->for($key);
            // En tareas, ModuleProgress cuenta como total solo las pendientes: la meta es hechas + pendientes.
            $total = $key === 'tasks' ? $p['done'] + $p['total'] : $p['total'];
            if ($total <= 0) {
                continue;
            }
            $goals[] = $meta + [
                'icon' => config("modules.{$key}.icon", 'bi-circle'),
                'key' => $key, 'href' => route($meta['route']), 'done' => $p['done'], 'total' => $total,
                'met' => $p['done'] >= $total, 'percent' => (int) min(100, round($p['done'] / $total * 100)),
                // El guardián del módulo (CHG-055) acompaña su meta; sin juego, queda el ícono.
                'guardian' => $guardians->pick($userId, $key),
            ];
        }

        return $goals;
    }

    public function render(ModuleProgress $progress, ModuleGuardian $guardians, DailyChallenge $challenge, Streak $streak, DailyBuddy $buddies)
    {
        $userId = (int) auth()->id();
        $goals = $this->goals($progress, $guardians, $userId);
        $allMet = $goals !== [] && collect($goals)->every('met');
        $closed = UserActiveDay::isClosed($userId, self::today()->toDateString());

        return view('livewire.home.day-close', [
            'date' => ucfirst(self::today()->locale('es')->translatedFormat('l j \d\e F')),
            'buddy' => $buddies->pick($userId),
            'closing' => [
                'done' => $closed,
                'quote' => $closed ? '“Zzz… mañana seguimos.”' : ($allMet ? '“Cumpliste todo lo de hoy. Descansa.”' : '“Buen trabajo hoy. Vamos cerrando.”'),
                'bubble' => $closed ? 'Ya cerraste el día. Descansa.' : 'Revisa tu día y deja mañana listo.',
            ],
            'goals' => $goals,
            'metCount' => collect($goals)->where('met', true)->count(),
            'challenge' => $challenge->forToday($userId),
            'streak' => $streak->summary($userId),
            'tasks' => $this->pending()->orderBy('end_date')->get(),
        ]);
    }
}
