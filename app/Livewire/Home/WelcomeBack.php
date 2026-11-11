<?php

namespace App\Livewire\Home;

use App\Actions\LogDrink;
use App\Models\DrinkType;
use App\Models\Task;
use App\Services\Engagement\ReturnDetector;
use App\Services\Game\ModuleGuardian;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * CHG-071: bienvenida al volver tras 3+ días sin entrar. Sin culpa: no dice
 * cuántos días faltaste ni habla de "vencidas"; ofrece empezar limpio.
 */
class WelcomeBack extends Component
{
    public ?array $absence = null;

    /** @var array<string, array{start: ?string, end: ?string}> fechas previas para Deshacer */
    public array $undo = [];

    public string $message = '';

    public bool $choosingDate = false;

    public string $targetDate = '';

    public function mount(ReturnDetector $detector): void
    {
        $this->absence = $detector->detect((int) auth()->id());
        $this->targetDate = today()->toDateString();
    }

    public function moveToToday(): void
    {
        $this->moveTo(today()->toDateString(), 'moved');
    }

    public function moveToDate(): void
    {
        $this->validate(['targetDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']]);
        $this->moveTo($this->targetDate, 'rescheduled');
    }

    /** "Dejar sin fecha": la tarea sigue, solo deja de presionar. */
    public function clearDates(): void
    {
        $this->apply(fn (Task $task) => $task->fill(['start_date' => null, 'end_date' => null]), 'undated');
        $this->message = 'Listo, quedaron sin fecha. Empezamos de nuevo.';
    }

    public function undoLast(): void
    {
        DB::transaction(function () {
            foreach (Task::whereIn('id', array_keys($this->undo))->get() as $task) {
                $task->fill(['start_date' => $this->undo[$task->id]['start'], 'end_date' => $this->undo[$task->id]['end']])->save();
            }
        });
        $this->undo = [];
        $this->message = 'Deshecho. Las tareas quedaron como estaban.';
    }

    public function addWater(): void
    {
        $water = DrinkType::where('name', 'like', '%agua%')->orderBy('name')->first() ?? DrinkType::orderBy('name')->first();
        if ($water) {
            LogDrink::handle($water, today()->toDateString(), 250);
            $this->message = 'Un vaso de agua. Buen comienzo.';
        }
        $this->close('water');
    }

    public function dismiss(): void
    {
        $this->close('dismissed');
        $this->absence = null;
    }

    private function moveTo(string $date, string $outcome): void
    {
        $target = CarbonImmutable::parse($date)->startOfDay();
        $this->apply(function (Task $task) use ($target) {
            // Se corre el bloque entero para que la fecha límite caiga en el día elegido, sin cambiar la hora ni la duración.
            $deadline = CarbonImmutable::instance($task->end_date ?? $task->start_date);
            $shift = (int) $deadline->startOfDay()->diffInDays($target, false);
            $task->fill([
                'start_date' => $task->start_date?->copy()->addDays($shift),
                'end_date' => $task->end_date?->copy()->addDays($shift),
            ]);
        }, $outcome);
        $this->message = $date === today()->toDateString() ? 'Listo, empezamos de nuevo.' : 'Listo, quedaron para esa fecha.';
        $this->choosingDate = false;
    }

    private function apply(callable $change, string $outcome): void
    {
        $this->undo = [];
        DB::transaction(function () use ($change) {
            foreach ($this->behind()->get() as $task) {
                $this->undo[$task->id] = [
                    'start' => $task->start_date?->toDateTimeString(),
                    'end' => $task->end_date?->toDateTimeString(),
                ];
                $change($task);
                $task->save();
            }
        });
        $this->close($outcome);
    }

    private function close(string $outcome): void
    {
        if ($this->absence) {
            app(ReturnDetector::class)->close((int) auth()->id(), (int) $this->absence['days_away'], $outcome);
        }
    }

    /** Tareas que se quedaron atrás: sin completar, con fecha pasada, sin historial de recurrencia. */
    private function behind()
    {
        return Task::query()->overdue()->where('is_recurrence_history', false);
    }

    public function render()
    {
        $visible = $this->absence !== null || $this->message !== '';

        return view('livewire.home.welcome-back', [
            'visible' => $visible,
            'behind' => $visible && $this->absence ? $this->behind()->count() : 0,
            'guardian' => $visible && $this->absence ? app(ModuleGuardian::class)->pick((int) auth()->id(), 'habits') : null,
            'name' => strtok((string) auth()->user()?->name, ' ') ?: '',
        ]);
    }
}
