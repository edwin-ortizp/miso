<?php

namespace App\Livewire\Pomodoro;

use App\Livewire\Concerns\HasUrlDate;
use App\Livewire\Concerns\WithDefaultDateFilter;
use App\Livewire\Concerns\WithManagementCard;
use App\Models\ModuleSetting;
use App\Models\PomodoroSession;
use App\Support\GoalCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pomodoro')]
class PomodoroTimer extends Component
{
    use HasUrlDate;
    use WithDefaultDateFilter;
    use WithManagementCard;

    public const SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
        'longest' => 'Mayor duración',
        'shortest' => 'Menor duración',
    ];

    public const ORIGINS = [
        'timer' => 'Temporizador',
        'manual' => 'Registro manual',
    ];

    public int $workDuration = 25;

    public int $shortBreak = 5;

    public int $longBreak = 15;

    public int $weekdayGoal = 300;

    public int $weekendGoal = 120;

    #[Url(as: 'sort', history: true, except: 'recent')]
    public string $sort = 'recent';

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'origin', history: true, except: '')]
    public string $originFilter = '';

    #[Url(as: 'month', history: true, except: '')]
    public string $calendarMonth = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $editingDate = '';

    public string $manualStart = '';

    public string $manualEnd = '';

    public string $manualDescription = '';

    public function mount(): void
    {
        $this->initializeSelectedDate();
        $this->setDateScope($this->dateScope);
        $this->calendarMonth = $this->normalizeCalendarMonth($this->calendarMonth);

        $settings = ModuleSetting::where('module', 'pomodoro')->value('settings') ?? [];

        $this->workDuration = (int) ($settings['work_duration_minutes'] ?? 25);
        $this->shortBreak = (int) ($settings['short_break_minutes'] ?? 5);
        $this->longBreak = (int) ($settings['long_break_minutes'] ?? 15);
        $this->weekdayGoal = (int) ($settings['weekday_goal_minutes'] ?? 300);
        $this->weekendGoal = (int) ($settings['weekend_goal_minutes'] ?? 120);
    }

    public function saveSession(int $startedAt, int $endedAt, string $description, string $clientToken): void
    {
        $start = self::storeTimerSession($startedAt, $endedAt, $description, $clientToken);

        $this->selectDate($start->toDateString());
        $this->dispatch('focus-session-saved');
    }

    /** Valida y guarda una sesión del temporizador; es idempotente por client_token. */
    public static function storeTimerSession(int $startedAt, int $endedAt, string $description, string $clientToken): Carbon
    {
        $validated = Validator::make([
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'description' => $description,
            'client_token' => $clientToken,
        ], [
            'started_at' => ['required', 'integer', 'min:1'],
            'ended_at' => ['required', 'integer', 'gt:started_at'],
            'description' => ['nullable', 'string', 'max:1000'],
            'client_token' => ['required', 'uuid'],
        ])->after(function ($validator) use ($startedAt, $endedAt): void {
            $duration = $endedAt - $startedAt;

            if ($duration < 60 || $duration > 86400) {
                $validator->errors()->add('ended_at', 'La sesión debe durar entre 1 minuto y 24 horas.');
            }

            if ($endedAt > now()->addSeconds(5)->timestamp) {
                $validator->errors()->add('ended_at', 'La sesión todavía no ha terminado.');
            }
        })->validate();

        $start = Carbon::createFromTimestamp($validated['started_at'], config('app.timezone'));
        $duration = $validated['ended_at'] - $validated['started_at'];

        PomodoroSession::firstOrCreate([
            'client_token' => $validated['client_token'],
        ], [
            'date' => $start->toDateString(),
            'start_time' => ['timestamp' => $validated['started_at']],
            'end_time' => ['timestamp' => $validated['ended_at']],
            'duration' => $duration,
            'completed' => true,
            'description' => trim($validated['description']) ?: null,
        ]);

        return $start;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'recent';
        $this->resetPage();
    }

    public function applyFilters(string $scope, string $origin): void
    {
        $this->setDateScope($scope);
        $this->originFilter = array_key_exists($origin, self::ORIGINS) ? $origin : '';
        $this->resetPage();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        $this->resetPage();
        match ($key) {
            'date' => $this->dateScope = 'all',
            'origin' => $this->originFilter = '',
            default => null,
        };
    }

    public function clearFilters(): void
    {
        $this->resetPage();
        $this->dateScope = 'all';
        $this->originFilter = '';
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth.'-01')->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    public function openForm(?string $id = null): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->manualStart = '';
        $this->manualEnd = '';
        $this->manualDescription = '';

        // Editar solo ajusta las horas: la sesión conserva el día en que empezó.
        if ($id && ($session = PomodoroSession::find($id))) {
            $timezone = config('app.timezone');
            $start = Carbon::createFromTimestamp($session->start_time['timestamp'], $timezone);
            $this->editingId = $session->id;
            $this->editingDate = $start->toDateString();
            $this->manualStart = $start->format('H:i');
            $this->manualEnd = Carbon::createFromTimestamp($session->end_time['timestamp'], $timezone)->format('H:i');
            $this->manualDescription = (string) $session->description;
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function saveManualSession(): void
    {
        $format = $this->manualUsesToday() || $this->editingId ? 'H:i' : 'Y-m-d\TH:i';
        $validated = $this->validate([
            'manualStart' => ['required', 'date_format:'.$format],
            'manualEnd' => ['required', 'date_format:'.$format],
            'manualDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        [$start, $end] = $this->manualInterval();

        if ($end->lessThanOrEqualTo($start)) {
            $this->addError('manualEnd', 'La fecha y hora de fin debe ser posterior al inicio.');

            return;
        }

        if ($end->isFuture()) {
            $this->addError('manualEnd', 'No puedes registrar tiempo que todavía no ha transcurrido.');

            return;
        }

        $duration = $start->diffInSeconds($end);

        if ($duration > 57600) {
            $this->addError('manualEnd', 'El registro manual no puede superar 16 horas.');

            return;
        }

        $attributes = [
            'date' => $start->toDateString(),
            'start_time' => ['timestamp' => $start->timestamp],
            'end_time' => ['timestamp' => $end->timestamp],
            'duration' => $duration,
            'completed' => true,
            'description' => trim($validated['manualDescription'] ?? '') ?: null,
        ];

        // Editar conserva el origen (client_token) del registro.
        $session = $this->editingId ? PomodoroSession::find($this->editingId) : null;
        $session ? $session->update($attributes) : PomodoroSession::create($attributes);
        $this->dispatch('focus-session-saved');

        $this->selectDate($start->toDateString());
        $this->manualStart = '';
        $this->manualEnd = '';
        $this->manualDescription = '';
        $this->closeForm();
    }

    public function deleteSession(string $id): void
    {
        PomodoroSession::findOrFail($id)->delete();
        $this->dispatch('focus-session-saved');
    }

    public function previousDay(): void
    {
        $this->selectDate(Carbon::parse($this->selectedDate)->subDay()->toDateString());
    }

    public function nextDay(): void
    {
        $this->selectDate(Carbon::parse($this->selectedDate)->addDay()->toDateString());
    }

    public function today(): void
    {
        $this->selectDate(now()->toDateString());
    }

    public function render()
    {
        $selectedDate = Carbon::parse($this->selectedDate)->startOfDay();
        $dayQuery = fn () => PomodoroSession::whereDate('date', $selectedDate)->where('completed', true);

        $totalSeconds = (int) $dayQuery()->sum('duration');
        $totalMinutes = intdiv($totalSeconds, 60);
        $dailyGoal = $this->goalFor($selectedDate);
        $progressPercentage = $dailyGoal > 0 ? (int) round(($totalMinutes / $dailyGoal) * 100) : 0;

        $search = trim($this->search);
        $sessions = $this->applyDateScope(PomodoroSession::query()->where('completed', true))
            ->when($search !== '', fn ($q) => $q->where('description', 'like', '%'.$search.'%'))
            ->when($this->originFilter === 'timer', fn ($q) => $q->whereNotNull('client_token'))
            ->when($this->originFilter === 'manual', fn ($q) => $q->whereNull('client_token'))
            ->when(in_array($this->sort, ['longest', 'shortest'], true),
                fn ($q) => $q->orderBy('duration', $this->sort === 'longest' ? 'desc' : 'asc'))
            ->orderBy('date', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('created_at', $this->sort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage());

        $activeFilters = $this->dateFilterChips();
        if ($this->originFilter !== '') {
            $activeFilters[] = ['key' => 'origin', 'value' => null, 'label' => 'Origen: '.self::ORIGINS[$this->originFilter], 'icon' => 'bi-stopwatch'];
        }

        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekTotals = $this->minutesByDate($weekStart, $weekStart->copy()->endOfWeek());
        $weekDays = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $weekTotals): array {
            $date = $weekStart->copy()->addDays($offset);
            $minutes = (int) ($weekTotals[$date->toDateString()] ?? 0);
            $goal = $this->goalFor($date);

            return [
                'date' => $date,
                'minutes' => $minutes,
                'goal' => $goal,
                'percentage' => $goal > 0 ? (int) round(($minutes / $goal) * 100) : 0,
            ];
        });

        // Meta distinta entre semana y fin de semana: el calendario la resuelve por fecha.
        $goalFor = fn (Carbon $date): int => $this->goalFor($date);
        $monthStart = Carbon::parse($this->calendarMonth.'-01');
        [$gridStart, $gridEnd] = GoalCalendar::gridBounds($selectedDate, $monthStart);
        $monthTotals = $this->minutesByDate($gridStart, $gridEnd);
        $monthData = GoalCalendar::month($monthTotals, $selectedDate, $goalFor, $monthStart);
        $monthData['focus_minutes'] = (int) $monthTotals
            ->filter(fn ($minutes, $date) => str_starts_with($date, $monthStart->format('Y-m')))
            ->sum();

        return view('livewire.pomodoro.pomodoro-timer', [
            'sessions' => $sessions,
            'totalSessions' => PomodoroSession::where('completed', true)->count(),
            'sorts' => self::SORTS,
            'origins' => self::ORIGINS,
            'activeFilters' => $activeFilters,
            'dateScopes' => $this->dateScopeOptions(),
            'displayDate' => $selectedDate,
            'weekStart' => $weekStart,
            'weekDays' => $weekDays,
            'dailyGoal' => $dailyGoal,
            'totalSeconds' => $totalSeconds,
            'totalMinutes' => $totalMinutes,
            'sessionCount' => $dayQuery()->count(),
            'progressPercentage' => $progressPercentage,
            'manualDurationSeconds' => $this->manualDurationSeconds(),
            'monthData' => $monthData,
            'streak' => GoalCalendar::streak($this->minutesByDate(today()->subYear(), today()), $goalFor),
        ]);
    }

    /**
     * Minutos de enfoque completados por fecha (`Y-m-d` => minutos) dentro del rango.
     */
    private function minutesByDate(Carbon $from, Carbon $to): Collection
    {
        return PomodoroSession::query()
            ->where('completed', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'duration'])
            ->groupBy(fn (PomodoroSession $session) => $session->date->toDateString())
            ->map(fn (Collection $group) => intdiv((int) $group->sum('duration'), 60));
    }

    private function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->calendarMonth = substr($date, 0, 7);
    }

    private function normalizeCalendarMonth(string $month): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : substr($this->selectedDate, 0, 7);
    }

    private function goalFor(Carbon $date): int
    {
        return $date->isWeekend() ? $this->weekendGoal : $this->weekdayGoal;
    }

    protected function manualDurationSeconds(): ?int
    {
        if ($this->manualStart === '' || $this->manualEnd === '') {
            return null;
        }

        try {
            [$start, $end] = $this->manualInterval();

            return $end->greaterThan($start) ? $start->diffInSeconds($end) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Intervalo del formulario. Al editar se reciben solo horas sobre el día de la sesión;
     * si el fin queda antes del inicio, la sesión cruzó la medianoche.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function manualInterval(): array
    {
        if ($this->manualUsesToday()) {
            $date = today()->toDateString();

            return [$this->parseManualDateTime($date.'T'.$this->manualStart), $this->parseManualDateTime($date.'T'.$this->manualEnd)];
        }

        if (! $this->editingId) {
            return [$this->parseManualDateTime($this->manualStart), $this->parseManualDateTime($this->manualEnd)];
        }

        $start = $this->parseManualDateTime($this->editingDate.'T'.$this->manualStart);
        $end = $this->parseManualDateTime($this->editingDate.'T'.$this->manualEnd);

        return [$start, $end->lessThanOrEqualTo($start) ? $end->addDay() : $end];
    }

    private function parseManualDateTime(string $value): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d\TH:i', $value, config('app.timezone'));
    }

    protected function manualUsesToday(): bool
    {
        return false;
    }
}
