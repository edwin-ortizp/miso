<?php

namespace App\Livewire\Health;

use App\Models\HealthEvent;
use App\Models\HealthLog;
use App\Models\Task;
use App\Models\TaskAssociation;
use App\Services\TaskGamificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Salud')]
class HealthIndex extends Component
{
    use \App\Livewire\Concerns\WithManagementCard;

    public const RANGES = [
        'all' => 'Todo el historial',
        '30d' => 'Últimos 30 días',
        '60d' => 'Últimos 60 días',
        '6m' => 'Últimos 6 meses',
        '1y' => 'Último año',
    ];

    public const STATUSES = [
        'all' => 'Todos',
        'active' => 'En seguimiento',
        'recovered' => 'Recuperados',
    ];

    public const ILLNESS_PERIODS = [
        'this_year' => 'Este año',
        'last_year' => 'Año anterior',
        'last_12m' => 'Últimos 12 meses',
    ];

    private const MONTH_LABELS = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    #[Url(as: 'range', history: true, keep: true)]
    public string $range = 'all';

    #[Url(as: 'status', history: true, keep: true)]
    public string $status = 'all';

    #[Url(as: 'types', history: true, keep: true)]
    public array $types = [];

    #[Url(as: 'zone', history: true, keep: true)]
    public string $zone = '';

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    public string $illnessPeriod = 'this_year';

    public bool $showForm = false;

    public bool $showTaskForm = false;

    public bool $showLogForm = false;

    public bool $showRecoveryForm = false;

    public bool $showRescheduleForm = false;

    public ?string $editingId = null;

    public ?string $loggingEventId = null;

    public ?string $editingLogId = null;

    public ?string $recoveringEventId = null;

    public ?string $reschedulingTaskId = null;

    public string $type = 'appointment';

    public string $title = '';

    public string $eventDate = '';

    public ?string $endDate = null;

    public string $notes = '';

    public array $bodyAreas = [];

    public string $customBodyArea = '';

    public ?int $initialIntensity = null;

    public ?int $storedIntensity = null;

    public string $illness = '';

    public string $customIllness = '';

    public string $provider = '';

    public string $specialty = '';

    public string $facility = '';

    public string $vaccineName = '';

    public string $vaccineDose = '';

    public string $pendingTitle = '';

    public ?string $pendingDate = null;

    public ?string $rescheduleDate = null;

    public string $logDate = '';

    public ?int $logIntensity = null;

    public string $logNotes = '';

    public string $recoveryDate = '';

    public ?int $recoveryIntensity = null;

    public function mount(): void
    {
        $this->normalizeFilters();

        // "Registrar evento en esta zona" desde la Vista del cuerpo.
        $area = request()->query('new_area');
        if (static::class !== self::class) {
            return;
        }
        if (is_string($area) && array_key_exists($area, HealthEvent::BODY_AREAS)) {
            $this->openForm();
            $this->type = 'symptom';
            $this->bodyAreas = [$area];
        } elseif (request()->boolean('new')) {
            $this->openForm();
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->resetPage();

            return;
        }

        if (in_array($property, ['range', 'status', 'types', 'zone', 'illnessPeriod'], true) || str_starts_with($property, 'types.')) {
            $this->normalizeFilters();
            if ($property !== 'illnessPeriod') $this->resetPage();
        }
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        $this->resetPage();
        match ($key) {
            'range' => $this->range = 'all',
            'status' => $this->status = 'all',
            'zone' => $this->zone = '',
            'types' => $this->types = $value === null ? [] : array_values(array_diff($this->types, [$value])),
            default => null,
        };
    }

    public function applyFilters(string $range, string $status, array $types): void
    {
        $this->range = $range;
        $this->status = $status;
        $this->types = $types;
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->resetPage();
        $this->range = 'all';
        $this->status = 'all';
        $this->types = [];
        $this->zone = '';
    }

    public function updatedRecoveryDate(): void
    {
        if ($this->recoveringEventId) {
            $this->recoveryIntensity = HealthLog::query()
                ->where('health_event_id', $this->recoveringEventId)
                ->whereDate('date', $this->recoveryDate)
                ->value('intensity');
        }
    }

    public function openForm(?string $id = null): void
    {
        $this->resetEventForm();
        $this->resetValidation();
        $this->eventDate = today()->toDateString();

        if ($id && ($event = HealthEvent::find($id))) {
            $this->editingId = $event->id;
            $this->type = $event->type;
            $this->title = $event->title;
            $this->eventDate = $event->event_date->toDateString();
            $this->endDate = $event->end_date?->toDateString();
            $this->notes = $event->notes ?? '';
            $details = $event->details ?? [];
            $this->bodyAreas = $event->bodyAreas();
            $storedArea = (string) ($details['body_area'] ?? '');
            $this->customBodyArea = $details['body_area_note'] ?? (in_array('other', $this->bodyAreas, true) && ! array_key_exists($storedArea, HealthEvent::BODY_AREAS) ? $storedArea : '');
            $this->storedIntensity = $details['severity'] ?? null;
            $storedIllness = $details['condition'] ?? '';
            $this->illness = array_key_exists($storedIllness, HealthEvent::COMMON_ILLNESSES) ? $storedIllness : ($storedIllness === '' ? '' : 'other');
            $this->customIllness = $details['condition_note'] ?? ($this->illness === 'other' ? $storedIllness : '');
            $this->provider = $details['provider'] ?? '';
            $this->specialty = $details['specialty'] ?? '';
            $this->facility = $details['facility'] ?? '';
            $this->vaccineName = $details['vaccine_name'] ?? '';
            $this->vaccineDose = $details['dose'] ?? '';
        }
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetEventForm();
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate($this->eventRules());
        $attributes = [
            'type' => $data['type'], 'title' => trim($data['title']), 'event_date' => $data['eventDate'],
            'end_date' => ($data['endDate'] ?? null) ?: null, 'notes' => ($data['notes'] ?? '') ?: null, 'details' => $this->detailsFor($data['type']),
        ];

        DB::transaction(function () use ($attributes, $data): void {
            $event = $this->editingId ? HealthEvent::findOrFail($this->editingId) : HealthEvent::create($attributes);
            if ($this->editingId) {
                $event->update($attributes);
            } elseif ($this->tracksEvolution($event) && filled($data['initialIntensity'] ?? null) && ! $event->event_date->isFuture()) {
                $event->logs()->create(['date' => $event->event_date, 'intensity' => $data['initialIntensity']]);
            }
            if (in_array($event->type, HealthEvent::SCHEDULED_TYPES, true) && $event->event_date->isFuture()) {
                $task = $event->scheduledTask();
                $taskData = ['title' => 'Salud: '.$event->title, 'category' => 'salud', 'start_date' => $event->event_date, 'end_date' => $event->event_date];
                $task ? $task->update($taskData) : TaskAssociation::link(Task::create([...$taskData, 'task_code' => rand(10000, 99999)]), $event);
            }
        });
        $this->closeForm();
    }

    public function deleteEvent(string $id): void
    {
        HealthEvent::find($id)?->delete();
    }

    public function openLogForm(string $eventId): void
    {
        $event = HealthEvent::findOrFail($eventId);
        abort_unless($this->tracksEvolution($event) && ! $event->end_date && ! $event->event_date->isFuture(), 404);
        $this->loggingEventId = $event->id;
        $this->editingLogId = null;
        $this->logDate = today()->toDateString();
        $this->logIntensity = null;
        $this->logNotes = '';
        $this->resetValidation();
        $this->showLogForm = true;
    }

    public function saveLog(): void
    {
        $data = $this->validate(['logDate' => ['required', 'date'], 'logIntensity' => ['required', 'integer', 'between:1,10'], 'logNotes' => ['nullable', 'string', 'max:1000']]);
        $event = HealthEvent::findOrFail($this->loggingEventId);
        abort_unless($this->tracksEvolution($event) && ! $event->end_date && ! $event->event_date->isFuture(), 404);
        if ($data['logDate'] < $event->event_date->toDateString()) {
            $this->addError('logDate', 'La fecha no puede ser anterior al inicio del malestar.');

            return;
        }
        if ($event->logs()->whereDate('date', $data['logDate'])->exists()) {
            $this->addError('logDate', 'Ya hay una intensidad registrada para este día. Puedes editarla en el historial.');

            return;
        }
        $event->logs()->create(['date' => $data['logDate'], 'intensity' => $data['logIntensity'], 'notes' => trim($data['logNotes']) ?: null]);
        $this->closeLogForm();
    }

    public function editLog(string $id): void
    {
        $log = HealthLog::findOrFail($id);
        $this->loggingEventId = $log->health_event_id;
        $this->editingLogId = $log->id;
        $this->logDate = $log->date->toDateString();
        $this->logIntensity = $log->intensity;
        $this->logNotes = $log->notes ?? '';
        $this->resetValidation();
        $this->showLogForm = true;
    }

    public function updateLog(): void
    {
        $data = $this->validate(['logIntensity' => ['required', 'integer', 'between:1,10'], 'logNotes' => ['nullable', 'string', 'max:1000']]);
        $log = HealthLog::findOrFail($this->editingLogId);
        $log->update(['intensity' => $data['logIntensity'], 'notes' => trim($data['logNotes']) ?: null]);
        $this->closeLogForm();
    }

    public function deleteLog(string $id): void
    {
        HealthLog::findOrFail($id)->delete();
    }

    public function closeLogForm(): void
    {
        $this->showLogForm = false;
        $this->loggingEventId = null;
        $this->editingLogId = null;
        $this->logDate = '';
        $this->logIntensity = null;
        $this->logNotes = '';
        $this->resetValidation();
    }

    public function openRecoveryForm(string $eventId): void
    {
        $event = HealthEvent::findOrFail($eventId);
        abort_unless($this->tracksEvolution($event) && ! $event->end_date && ! $event->event_date->isFuture(), 404);
        $this->recoveringEventId = $event->id;
        $this->recoveryDate = today()->toDateString();
        $this->recoveryIntensity = $event->logs()->whereDate('date', $this->recoveryDate)->value('intensity');
        $this->resetValidation();
        $this->showRecoveryForm = true;
    }

    public function closeRecoveryForm(): void
    {
        $this->showRecoveryForm = false;
        $this->recoveringEventId = null;
        $this->resetValidation();
    }

    public function reopenEvolution(string $eventId): void
    {
        $event = HealthEvent::findOrFail($eventId);
        abort_unless($this->tracksEvolution($event) && $event->end_date, 404);

        $event->update(['end_date' => null]);
    }

    public function saveRecovery(): void
    {
        $data = $this->validate(['recoveryDate' => ['required', 'date'], 'recoveryIntensity' => ['nullable', 'integer', 'between:1,10']]);
        $event = HealthEvent::findOrFail($this->recoveringEventId);
        abort_unless($this->tracksEvolution($event) && ! $event->end_date && ! $event->event_date->isFuture(), 404);
        if ($data['recoveryDate'] < $event->event_date->toDateString()) {
            $this->addError('recoveryDate', 'La recuperación no puede ser anterior al inicio.');

            return;
        }
        $log = $event->logs()->whereDate('date', $data['recoveryDate'])->first();
        if (! $log && $data['recoveryIntensity'] === null) {
            $this->addError('recoveryIntensity', 'Indica la intensidad del día de recuperación.');

            return;
        }
        if (! $log) {
            $event->logs()->create(['date' => $data['recoveryDate'], 'intensity' => $data['recoveryIntensity']]);
        }
        $event->update(['end_date' => $data['recoveryDate']]);
        $this->closeRecoveryForm();
    }

    public function openTaskForm(): void
    {
        $this->pendingTitle = '';
        $this->pendingDate = today()->toDateString();
        $this->resetValidation();
        $this->showTaskForm = true;
    }

    public function closeTaskForm(): void
    {
        $this->showTaskForm = false;
        $this->pendingTitle = '';
        $this->pendingDate = null;
        $this->resetValidation();
    }

    public function savePendingTask(): void
    {
        $data = $this->validate(['pendingTitle' => ['required', 'string', 'max:160'], 'pendingDate' => ['nullable', 'date']]);
        Task::create(['title' => trim($data['pendingTitle']), 'category' => 'salud', 'start_date' => $data['pendingDate'] ?: null, 'end_date' => $data['pendingDate'] ?: null, 'task_code' => rand(10000, 99999)]);
        $this->closeTaskForm();
    }

    public function completeTask(string $id, TaskGamificationService $gamification): void
    {
        $task = $this->healthTask($id);
        if ($task->completed) {
            return;
        }

        $result = $gamification->complete($task);
        if ($result['completed'] ?? false) {
            $this->dispatch('task-completed', ...$result);
        }
    }

    public function reopenTask(string $id, TaskGamificationService $gamification): void
    {
        $task = $this->healthTask($id);
        if ($task->completed) {
            $gamification->reopen($task);
        }
    }

    public function openRescheduleTask(string $id): void
    {
        $task = $this->healthTask($id);
        $this->reschedulingTaskId = $task->id;
        $this->rescheduleDate = ($task->start_date ?? $task->end_date)?->toDateString() ?? today()->toDateString();
        $this->resetValidation();
        $this->showRescheduleForm = true;
    }

    public function closeRescheduleForm(): void
    {
        $this->showRescheduleForm = false;
        $this->reschedulingTaskId = null;
        $this->rescheduleDate = null;
        $this->resetValidation();
    }

    public function saveRescheduleTask(): void
    {
        $data = $this->validate(['rescheduleDate' => ['required', 'date']]);
        $this->healthTask((string) $this->reschedulingTaskId)->update(['start_date' => $data['rescheduleDate'], 'end_date' => $data['rescheduleDate']]);
        $this->closeRescheduleForm();
    }

    public function deleteTask(string $id): void
    {
        $this->healthTask($id)->delete();
    }

    #[\Livewire\Attributes\On('health-records-changed')]
    public function refreshRecords(): void {}

    public function render()
    {
        $nextEvent = HealthEvent::query()->whereDate('event_date', '>', today())->orderBy('event_date')->first();
        $events = $this->filteredEvents()->with(['tasks', 'logs'])->orderByDesc('event_date')->orderByDesc('created_at')->orderBy('id')->paginate($this->perPage());

        return view('livewire.health.health-index', [
            'events' => $events,
            'totalCount' => HealthEvent::query()->count(),
            'activeFilters' => $this->activeFilters(),
            'typeLabels' => HealthEvent::TYPES,
            'ranges' => self::RANGES,
            'statuses' => self::STATUSES,
            'illnessPeriods' => self::ILLNESS_PERIODS,
            'bodyAreaOptions' => HealthEvent::groupedBodyAreas(),
            'commonIllnesses' => HealthEvent::COMMON_ILLNESSES,
            'nextEvent' => $nextEvent,
            'upcomingCount' => HealthEvent::query()->whereDate('event_date', '>', today())->count(),
            'pendingHealthTasks' => Task::query()->whereIn('category', \App\Models\TaskCategory::expandSelection(['salud'], auth()->id(), 'key'))->where('completed', false)->count(),
            'healthTasks' => $this->healthTasks(),
            'moments' => $this->illnessMoments(),
        ]);
    }

    /**
     * Filtros aplicados en forma de chips removibles.
     *
     * @return list<array{key: string, value: ?string, label: string, icon: string}>
     */
    public function activeFilters(): array
    {
        $filters = [];
        if ($this->range !== 'all') {
            $filters[] = ['key' => 'range', 'value' => null, 'label' => self::RANGES[$this->range], 'icon' => 'bi-calendar-range'];
        }
        if ($this->status !== 'all') {
            $filters[] = ['key' => 'status', 'value' => null, 'label' => self::STATUSES[$this->status], 'icon' => 'bi-activity'];
        }
        if ($this->zone !== '') {
            $filters[] = ['key' => 'zone', 'value' => null, 'label' => HealthEvent::BODY_AREAS[$this->zone], 'icon' => 'bi-person-standing'];
        }
        foreach ($this->types as $type) {
            $filters[] = ['key' => 'types', 'value' => $type, 'label' => HealthEvent::TYPES[$type], 'icon' => 'bi-tag'];
        }

        return $filters;
    }

    /**
     * Distribución mensual de síntomas y enfermedades en el periodo elegido.
     *
     * @return array{label: string, months: list<array{month: string, count: int}>, monthsWithEvents: int, total: int, average: string}
     */
    public function illnessMoments(): array
    {
        $start = match ($this->illnessPeriod) {
            'last_year' => CarbonImmutable::today()->subYear()->startOfYear(),
            'last_12m' => CarbonImmutable::today()->startOfMonth()->subMonths(11),
            default => CarbonImmutable::today()->startOfYear(),
        };
        $end = $start->addMonths(12)->subDay();

        $counts = HealthEvent::query()
            ->whereIn('type', ['symptom', 'illness'])
            ->whereDate('event_date', '>=', $start)
            ->whereDate('event_date', '<=', $end)
            ->pluck('event_date')
            ->countBy(fn ($date) => $date->format('Y-m'));

        $months = collect(range(0, 11))->map(function (int $offset) use ($start, $counts): array {
            $month = $start->addMonths($offset);

            return ['month' => self::MONTH_LABELS[$month->month], 'count' => (int) ($counts[$month->format('Y-m')] ?? 0)];
        })->all();

        $total = array_sum(array_column($months, 'count'));

        return [
            'label' => self::ILLNESS_PERIODS[$this->illnessPeriod],
            'months' => $months,
            'monthsWithEvents' => count(array_filter($months, fn (array $month) => $month['count'] > 0)),
            'total' => $total,
            'average' => number_format($total / 12, 1, ',', '.'),
        ];
    }

    private function filteredEvents(): Builder
    {
        $query = HealthEvent::query();

        if (($term = trim($this->search)) !== '') {
            $query->where(fn (Builder $search) => $search
                ->where('title', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%"));
        }

        if ($this->types !== []) {
            $query->whereIn('type', $this->types);
        }

        if ($since = $this->rangeStart()) {
            $query->whereDate('event_date', '>=', $since);
        }

        if ($this->zone !== '') {
            $query->where(fn (Builder $zone) => $zone
                ->whereJsonContains('details->body_areas', $this->zone)
                ->orWhere('details->body_area', $this->zone));
        }

        if ($this->status === 'active') {
            $query->whereIn('type', HealthEvent::EVOLUTION_TYPES)->whereNull('end_date');
        } elseif ($this->status === 'recovered') {
            $query->whereIn('type', HealthEvent::EVOLUTION_TYPES)->whereNotNull('end_date');
        }

        return $query;
    }

    private function rangeStart(): ?CarbonImmutable
    {
        $today = CarbonImmutable::today();

        return match ($this->range) {
            '30d' => $today->subDays(29),
            '60d' => $today->subDays(59),
            '6m' => $today->subMonths(6),
            '1y' => $today->subYear(),
            default => null,
        };
    }

    private function healthTasks(): Collection
    {
        $pending = Task::query()
            ->whereIn('category', \App\Models\TaskCategory::expandSelection(['salud'], auth()->id(), 'key'))
            ->where('completed', false)
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date')
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        $recentlyCompleted = Task::query()
            ->whereIn('category', \App\Models\TaskCategory::expandSelection(['salud'], auth()->id(), 'key'))
            ->where('completed', true)
            ->where('completed_at', '>=', now()->subDays(7))
            ->orderByDesc('completed_at')
            ->limit(3)
            ->get();

        return $pending->concat($recentlyCompleted);
    }

    private function healthTask(string $id): Task
    {
        return Task::query()->whereIn('category', \App\Models\TaskCategory::expandSelection(['salud'], auth()->id(), 'key'))->findOrFail($id);
    }

    private function eventRules(): array
    {
        $rules = ['type' => ['required', Rule::in(array_keys(HealthEvent::TYPES))], 'title' => ['required', 'string', 'max:160'], 'eventDate' => ['required', 'date'], 'endDate' => ['nullable', 'date', 'after_or_equal:eventDate'], 'notes' => ['nullable', 'string', 'max:4000']];
        if (in_array($this->type, HealthEvent::EVOLUTION_TYPES, true) && ! $this->editingId) {
            $rules['initialIntensity'] = [$this->type === 'procedure' ? 'nullable' : 'required', 'integer', 'between:1,10'];
        }
        if (in_array($this->type, HealthEvent::BODY_AREA_TYPES, true)) {
            $rules['bodyAreas'] = $this->type === 'symptom' ? ['required', 'array', 'min:1'] : ['nullable', 'array'];
            $rules['bodyAreas.*'] = ['string', Rule::in(array_keys(HealthEvent::BODY_AREAS))];
            $rules['customBodyArea'] = [in_array('other', $this->bodyAreas, true) ? 'required' : 'nullable', 'string', 'max:100'];
        }
        if ($this->type === 'illness') {
            $rules['illness'] = ['required', Rule::in(array_keys(HealthEvent::COMMON_ILLNESSES))];
            $rules['customIllness'] = ['required_if:illness,other', 'nullable', 'string', 'max:120'];
        }
        if (in_array($this->type, HealthEvent::SCHEDULED_TYPES, true)) {
            $rules['provider'] = ['nullable', 'string', 'max:120'];
            $rules['specialty'] = ['nullable', 'string', 'max:120'];
            $rules['facility'] = ['nullable', 'string', 'max:120'];
        }
        if ($this->type === 'vaccination') {
            $rules['vaccineName'] = ['nullable', 'string', 'max:120'];
            $rules['vaccineDose'] = ['nullable', 'string', 'max:80'];
        }

        return $rules;
    }

    private function detailsFor(string $type): ?array
    {
        $areas = in_array($type, HealthEvent::BODY_AREA_TYPES, true) ? array_values(array_unique($this->bodyAreas)) : [];
        $areaDetails = array_filter(['body_areas' => $areas ?: null, 'body_area_note' => in_array('other', $areas, true) ? trim($this->customBodyArea) : null]);
        $details = match ($type) {
            'symptom' => array_filter([...$areaDetails, 'severity' => $this->storedIntensity], fn ($value) => $value !== null && $value !== ''),
            'illness' => array_filter(['condition' => $this->illness, 'condition_note' => $this->illness === 'other' ? trim($this->customIllness) : null, ...$areaDetails]),
            'appointment', 'checkup', 'procedure' => array_filter(['provider' => trim($this->provider), 'specialty' => trim($this->specialty), 'facility' => trim($this->facility), ...$areaDetails]),
            'vaccination' => array_filter(['vaccine_name' => trim($this->vaccineName), 'dose' => trim($this->vaccineDose)]), default => [],
        };

        return $details === [] ? null : $details;
    }

    private function tracksEvolution(HealthEvent $event): bool
    {
        return in_array($event->type, HealthEvent::EVOLUTION_TYPES, true);
    }

    private function resetEventForm(): void
    {
        $this->editingId = null;
        $this->type = 'appointment';
        $this->title = '';
        $this->eventDate = '';
        $this->endDate = null;
        $this->notes = '';
        $this->bodyAreas = [];
        $this->customBodyArea = '';
        $this->initialIntensity = null;
        $this->storedIntensity = null;
        $this->illness = '';
        $this->customIllness = '';
        $this->provider = '';
        $this->specialty = '';
        $this->facility = '';
        $this->vaccineName = '';
        $this->vaccineDose = '';
    }

    private function normalizeFilters(): void
    {
        if (! array_key_exists($this->range, self::RANGES)) {
            $this->range = 'all';
        }
        if (! array_key_exists($this->status, self::STATUSES)) {
            $this->status = 'all';
        }
        if ($this->zone !== '' && ! array_key_exists($this->zone, HealthEvent::BODY_AREAS)) {
            $this->zone = '';
        }
        if (! array_key_exists($this->illnessPeriod, self::ILLNESS_PERIODS)) {
            $this->illnessPeriod = 'this_year';
        }
        $this->types = array_values(array_unique(array_filter(
            array_map('strval', (array) $this->types),
            fn (string $type) => array_key_exists($type, HealthEvent::TYPES),
        )));
    }
}
