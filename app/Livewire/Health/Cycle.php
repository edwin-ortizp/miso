<?php

namespace App\Livewire\Health;

use App\Actions\LogCycle;
use App\Models\CycleDayLog;
use App\Models\MenstrualCycle;
use App\Support\CycleProgress;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * CHG-130: ciclo menstrual. Registra y muestra tendencias; no diagnostica ni es un método anticonceptivo.
 * Los datos son íntimos: sensibles por defecto y con notas y síntomas cifrados.
 */
#[Layout('layouts.app')]
#[Title('Ciclo')]
class Cycle extends Component
{
    #[Url(as: 'month', history: true, except: '')]
    public string $month = '';

    /** '', start, end, day o edit. */
    public string $dialog = '';
    public ?string $editingId = null;
    public string $started_on = '';
    public string $ended_on = '';
    public string $date = '';
    public string $flow = '';
    /** @var list<string> */
    public array $symptoms = [];
    public string $notes = '';
    public bool $is_sensitive = true;

    public function mount(): void
    {
        $this->month = $this->normalizedMonth()->format('Y-m');
    }

    private function normalizedMonth(): Carbon
    {
        $tz = config('app.timezone');
        try {
            return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) ? Carbon::createFromFormat('Y-m-d', $this->month.'-01', $tz)->startOfDay() : now($tz)->startOfMonth();
        } catch (\Throwable) {
            return now($tz)->startOfMonth();
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->normalizedMonth()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->normalizedMonth()->addMonth()->format('Y-m');
    }

    // --- Un toque ----------------------------------------------------------------------------

    public function quickStart(): void
    {
        LogCycle::start(null, null, true, 'app');
    }

    public function quickEnd(): void
    {
        LogCycle::end();
    }

    // --- Diálogos ----------------------------------------------------------------------------

    public function openStart(): void
    {
        $this->resetForm();
        $this->dialog = 'start';
        $this->started_on = LogCycle::today()->toDateString();
    }

    public function openEnd(): void
    {
        $this->resetForm();
        $this->dialog = 'end';
        $this->ended_on = LogCycle::today()->toDateString();
    }

    public function openDay(?string $date = null): void
    {
        $this->resetForm();
        $this->dialog = 'day';
        $this->date = $date ?: LogCycle::today()->toDateString();
        if ($log = CycleDayLog::whereDate('date', $this->date)->first()) {
            $this->flow = (string) $log->flow;
            $this->symptoms = $log->symptoms ?? [];
            $this->notes = (string) $log->notes;
            $this->is_sensitive = $log->is_sensitive;
        }
    }

    public function editCycle(string $id): void
    {
        $cycle = MenstrualCycle::findOrFail($id);

        $this->resetForm();
        $this->dialog = 'edit';
        $this->editingId = $cycle->id;
        $this->started_on = $cycle->started_on->toDateString();
        $this->ended_on = $cycle->ended_on?->toDateString() ?? '';
        $this->notes = (string) $cycle->notes;
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->resetForm();
    }

    public function save(): void
    {
        match ($this->dialog) {
            'start' => LogCycle::start($this->started_on ?: null, $this->notes ?: null, $this->is_sensitive, 'app'),
            'end' => LogCycle::end($this->ended_on ?: null),
            'day' => LogCycle::day($this->date ?: null, $this->flow ?: null, $this->symptoms, $this->notes ?: null, $this->is_sensitive, 'app'),
            'edit' => LogCycle::update(MenstrualCycle::findOrFail($this->editingId), $this->started_on, $this->ended_on ?: null, $this->notes ?: null),
            default => null,
        };

        $this->closeDialog();
    }

    public function deleteCycle(string $id): void
    {
        MenstrualCycle::where('id', $id)->delete();
    }

    public function deleteDay(string $id): void
    {
        CycleDayLog::where('id', $id)->delete();
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->started_on = '';
        $this->ended_on = '';
        $this->date = '';
        $this->flow = '';
        $this->symptoms = [];
        $this->notes = '';
        $this->is_sensitive = true;
    }

    public function render()
    {
        $today = LogCycle::today();
        $month = $this->normalizedMonth();
        $cycles = MenstrualCycle::orderByDesc('started_on')->get();
        $summary = CycleProgress::summary($cycles);

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $logs = CycleDayLog::whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])->orderByDesc('date')->get();

        // Duración de cada ciclo: días hasta el inicio del siguiente periodo.
        $asc = $cycles->sortBy(fn (MenstrualCycle $c) => $c->started_on->timestamp)->values();
        $lengths = [];
        foreach ($asc as $i => $cycle) {
            $next = $asc->get($i + 1);
            $lengths[$cycle->id] = $next ? (int) $cycle->started_on->diffInDays($next->started_on) : null;
        }

        return view('livewire.health.cycle', [
            'cycles' => $cycles,
            'lengths' => $lengths,
            'summary' => $summary,
            'cycleDay' => CycleProgress::cycleDay($cycles, $today),
            'openCycle' => $cycles->first(fn (MenstrualCycle $c) => $c->isOpen()),
            'weeks' => CycleProgress::calendar($month, $cycles, $logs, $summary, $today),
            'monthDate' => $month,
            'monthLogs' => $logs->filter(fn (CycleDayLog $l) => $l->date->month === $month->month && $l->date->year === $month->year)->values(),
            'flows' => CycleDayLog::FLOWS,
            'symptomOptions' => CycleDayLog::SYMPTOMS,
        ]);
    }
}
