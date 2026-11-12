<?php

namespace App\Livewire\Home;

use App\Actions\LogDrink;
use App\Actions\Task\ShiftTasksToDate;
use App\Models\DrinkType;
use App\Models\Task;
use App\Services\Engagement\ReturnDetector;
use App\Services\Game\ModuleGuardian;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * CHG-072: bienvenida al volver tras 3+ días sin entrar. Sin culpa: no dice
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
        ShiftTasksToDate::restore($this->undo);
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
        $this->undo = ShiftTasksToDate::handle($this->behind()->get(), CarbonImmutable::parse($date));
        $this->close($outcome);
        $this->message = $date === today()->toDateString() ? 'Listo, empezamos de nuevo.' : 'Listo, quedaron para esa fecha.';
        $this->choosingDate = false;
    }

    private function apply(callable $change, string $outcome): void
    {
        $this->undo = ShiftTasksToDate::apply($this->behind()->get(), $change);
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
