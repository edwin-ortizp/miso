<?php

namespace App\Livewire\Goal;

use App\Models\Goal;
use App\Support\GoalProgress;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Layout('layouts.app')]
#[Title('Objetivos')]
class GoalIndex extends Component
{
    use \App\Livewire\Concerns\HasReminderDraft;
    use WithManagementCard;
    #[Url(as: 'status', history: true, keep: true)]
    public string $statusFilter = 'active'; // active, completed, abandoned, all

    public const SORTS = [
        'recent' => 'Más recientes',
        'due' => 'Fecha límite próxima',
        'title' => 'Título (A–Z)',
    ];

    #[Url(as: 'sort', history: true, except: 'recent')]
    public string $sort = 'recent';

    public bool $showForm = false;
    public ?string $editingId = null;

    // Form fields
    public string $title = '';
    public string $description = '';
    public string $formStatus = 'active';
    public ?string $startDate = null;
    public ?string $dueDate = null;
    public bool $kpiEnabled = false;
    public string $kpiName = '';
    public string $kpiUnit = '';
    public string $kpiDirection = 'increase';
    public ?float $kpiStartValue = null;
    public ?float $kpiTargetValue = null;

    public function mount(): void
    {
        $this->normalizeStatusFilter();
    }

    public function updatedSort(): void
    {
        $this->sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'recent';
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->normalizeStatusFilter();
    }

    public function openForm(?string $id = null)
    {
        $this->resetForm();

        if ($id) {
            $goal = Goal::find($id);
            if ($goal) {
                $this->editingId = $id;
                $this->loadReminders($goal);
                $this->title = $goal->title;
                $this->description = $goal->description ?? '';
                $this->formStatus = $goal->status;
                $this->startDate = $goal->start_date?->format('Y-m-d');
                $this->dueDate = $goal->due_date?->format('Y-m-d');
                $kpi = GoalProgress::configuration($goal->numeric_goal);
                $this->kpiEnabled = $kpi !== null;
                $this->kpiName = $kpi['name'] ?? '';
                $this->kpiUnit = $kpi['unit'] ?? '';
                $this->kpiDirection = $kpi['direction'] ?? 'increase';
                $this->kpiStartValue = $kpi['startValue'] ?? null;
                $this->kpiTargetValue = $kpi['targetValue'] ?? null;
            }
        }

        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    public function save()
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => $this->persistGoalWithReminders());
    }

    private function persistGoalWithReminders()
    {
        $data = $this->validatedGoalData();
        $this->validateReminders($this->dueDate);

        if ($this->editingId) {
            $goal = Goal::query()->findOrFail($this->editingId);
            if ($data['numeric_goal']) {
                $latestValue = $goal->goalNumericEntries()->orderByDesc('date')->orderByDesc('created_at')->value('value');
                $data['numeric_goal']['currentValue'] = $latestValue ?? $data['numeric_goal']['startValue'];
            }
            $goal->update($data);
        } else {
            $goal = Goal::create($data);
        }

        $this->saveReminders($goal);

        $this->closeForm();
    }

    public function updateStatus(string $id, string $status)
    {
        Goal::findOrFail($id)->update(['status' => $status]);
    }

    public function delete(string $id)
    {
        // Por modelo, no masivo: así se limpian los vínculos con tareas.
        Goal::query()->find($id)?->delete();
    }

    private function resetForm()
    {
        $this->reminders = [];
        $this->title = '';
        $this->description = '';
        $this->formStatus = 'active';
        $this->startDate = null;
        $this->dueDate = null;
        $this->kpiEnabled = false;
        $this->kpiName = '';
        $this->kpiUnit = '';
        $this->kpiDirection = 'increase';
        $this->kpiStartValue = null;
        $this->kpiTargetValue = null;
        $this->resetValidation();
    }

    private function normalizeStatusFilter(): void
    {
        if (!in_array($this->statusFilter, ['active', 'completed', 'abandoned', 'all'], true)) {
            $this->statusFilter = 'active';
        }
    }

    private function validatedGoalData(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'formStatus' => ['required', 'in:active,completed,abandoned'],
            'startDate' => [$this->kpiEnabled ? 'required' : 'nullable', 'date'],
            'dueDate' => [$this->kpiEnabled ? 'required' : 'nullable', 'date', 'after_or_equal:startDate'],
            'kpiEnabled' => ['boolean'],
        ];

        if ($this->kpiEnabled) {
            $rules += [
                'kpiName' => ['required', 'string', 'max:120'],
                'kpiUnit' => ['required', 'string', 'max:50'],
                'kpiDirection' => ['required', 'in:increase,decrease'],
                'kpiStartValue' => ['required', 'numeric'],
                'kpiTargetValue' => ['required', 'numeric', 'different:kpiStartValue'],
            ];
        }

        $validated = $this->validate($rules);

        return [
            'title' => trim($validated['title']),
            'description' => trim($validated['description'] ?? '') ?: null,
            'status' => $validated['formStatus'],
            'start_date' => $validated['startDate'] ?: null,
            'due_date' => $validated['dueDate'] ?: null,
            'numeric_goal' => $this->kpiEnabled ? [
                'enabled' => true,
                'name' => trim($validated['kpiName']),
                'unit' => trim($validated['kpiUnit']),
                'direction' => $validated['kpiDirection'],
                'startValue' => (float) $validated['kpiStartValue'],
                'targetValue' => (float) $validated['kpiTargetValue'],
                'currentValue' => (float) $validated['kpiStartValue'],
            ] : null,
        ];
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        if ($key === 'statusFilter') {
            $this->statusFilter = 'all';
        }
        $this->resetPage();
    }

    protected function filterProperties(): array
    {
        return ['statusFilter'];
    }

    public function render()
    {
        $query = Goal::query();

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        match ($this->sort) {
            // Los objetivos sin fecha límite van al final.
            'due' => $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date'),
            'title' => $query->orderBy('title'),
            default => null,
        };

        $goals = $query->orderByDesc('created_at')->paginate($this->perPage());
        $activeCount = Goal::where('status', 'active')->count();
        $completedCount = Goal::where('status', 'completed')->count();

        return view('livewire.goal.goal-index', [
            'goals' => $goals,
            'sorts' => self::SORTS,
            'activeCount' => $activeCount,
            'completedCount' => $completedCount,
        ]);
    }
}
