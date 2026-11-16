<?php

namespace App\Livewire\Health;

use App\Actions\LogSleep;
use App\Livewire\Concerns\WithManagementCard;
use App\Models\SleepLog;
use App\Support\SleepProgress;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** CHG-125: seguimiento del sueño con meta de horas. Registra y muestra tendencias; no diagnostica. */
#[Layout('layouts.app')]
#[Title('Sueño')]
class Sleep extends Component
{
    use WithManagementCard;

    public const PERIODS = [
        'all' => 'Todo',
        '7' => 'Últimos 7 días',
        '30' => 'Últimos 30 días',
        '90' => 'Últimos 90 días',
    ];

    public const SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
    ];

    #[Url(as: 'period', history: true, except: 'all')]
    public string $period = 'all';

    #[Url(as: 'sort', history: true, except: 'recent')]
    public string $sort = 'recent';

    public bool $showForm = false;
    public ?string $editingId = null;
    public string $slept_at = '';
    public string $woke_at = '';
    public string $quality = '';
    public string $notes = '';
    public bool $is_sensitive = false;

    public bool $showGoal = false;
    public string $goalHours = '';

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = 'all';
        }
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (! array_key_exists($this->sort, self::SORTS)) {
            $this->sort = 'recent';
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('period');
        $this->resetPage();
    }

    public function applyFilters(string $period): void
    {
        $this->period = $period;
        $this->updatedPeriod();
    }

    public function openForm(): void
    {
        $this->resetForm();
        // Precargado con la meta: despertar ahora y dormir la meta de horas antes.
        $woke = now()->startOfMinute();
        $this->woke_at = $woke->format('Y-m-d\TH:i');
        $this->slept_at = $woke->copy()->subMinutes(SleepProgress::goalMinutes(auth()->user()))->format('Y-m-d\TH:i');
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $log = SleepLog::findOrFail($id);

        $this->resetForm();
        $this->editingId = $log->id;
        $this->slept_at = $log->slept_at->format('Y-m-d\TH:i');
        $this->woke_at = $log->woke_at->format('Y-m-d\TH:i');
        $this->quality = $log->quality !== null ? (string) $log->quality : '';
        $this->notes = (string) $log->notes;
        $this->is_sensitive = $log->is_sensitive;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $editing = $this->editingId ? SleepLog::findOrFail($this->editingId) : null;

        $data = LogSleep::validate([
            'slept_at' => $this->slept_at === '' ? null : $this->slept_at,
            'woke_at' => $this->woke_at === '' ? null : $this->woke_at,
            'quality' => $this->quality === '' ? null : $this->quality,
            'notes' => $this->notes === '' ? null : $this->notes,
            'is_sensitive' => $this->is_sensitive,
        ], $editing);

        $editing ? LogSleep::update($editing, $data) : LogSleep::handle($data, 'app');

        $this->closeForm();
        $this->resetPage();
    }

    public function delete(string $id): void
    {
        SleepLog::where('id', $id)->delete();
    }

    public function openGoal(): void
    {
        $this->resetErrorBag();
        $this->goalHours = rtrim(rtrim(number_format(SleepProgress::goalMinutes(auth()->user()) / 60, 1, '.', ''), '0'), '.');
        $this->showGoal = true;
    }

    public function closeGoal(): void
    {
        $this->showGoal = false;
        $this->resetErrorBag();
    }

    public function saveGoal(): void
    {
        $value = str_replace(',', '.', trim($this->goalHours));
        if (! is_numeric($value) || (float) $value < SleepProgress::MIN_HOURS || (float) $value > SleepProgress::MAX_HOURS) {
            throw ValidationException::withMessages(['goalHours' => 'La meta debe estar entre 4 y 14 horas.']);
        }

        auth()->user()->forceFill(['daily_sleep_hours' => round((float) $value * 2) / 2])->save();
        $this->closeGoal();
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->slept_at = '';
        $this->woke_at = '';
        $this->quality = '';
        $this->notes = '';
        $this->is_sensitive = false;
    }

    public function render()
    {
        $goal = SleepProgress::goalMinutes(auth()->user());
        $last = SleepLog::orderByDesc('woke_at')->first();
        $week = SleepProgress::recent(7);

        $history = SleepLog::query()
            ->when($this->period !== 'all', fn ($q) => $q->where('woke_at', '>=', now()->subDays((int) $this->period)->startOfDay()))
            ->orderBy('woke_at', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage());

        return view('livewire.health.sleep', [
            'goal' => $goal,
            'last' => $last,
            'weekSummary' => SleepProgress::summary($week, $goal),
            'history' => $history,
            'totalLogs' => SleepLog::count(),
            'activeFilters' => $this->period !== 'all'
                ? [['key' => 'period', 'value' => null, 'label' => 'Periodo: '.self::PERIODS[$this->period], 'icon' => 'bi-calendar-event']]
                : [],
            'periods' => self::PERIODS,
            'sorts' => self::SORTS,
            'qualities' => SleepLog::QUALITIES,
        ]);
    }
}
