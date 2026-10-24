<?php

namespace App\Livewire\Goal;

use App\Models\Goal;
use App\Models\GoalEntry;
use App\Models\GoalNumericEntry;
use App\Models\Task;
use App\Models\TaskAssociation;
use App\Support\GoalProgress;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detalle del objetivo')]
class GoalDetail extends Component
{
    use \App\Livewire\Concerns\HasReminderDraft;
    public string $goalId;

    public bool $showGoalForm = false;
    public bool $showEntryForm = false;
    public bool $showNumericForm = false;
    public bool $showTaskForm = false;

    public string $title = '';
    public string $description = '';
    public string $status = 'active';
    public ?string $startDate = null;
    public ?string $dueDate = null;
    public bool $kpiEnabled = false;
    public string $kpiName = '';
    public string $kpiUnit = '';
    public string $kpiDirection = 'increase';
    public ?float $kpiStartValue = null;
    public ?float $kpiTargetValue = null;

    public ?string $editingEntryId = null;
    public string $entryText = '';
    public ?string $entryDate = null;
    public bool $entryIsMilestone = false;

    public ?string $editingNumericId = null;
    public ?float $numericValue = null;
    public ?string $numericDate = null;
    public string $numericNote = '';

    public string $taskTitle = '';
    public string $taskDescription = '';
    public string $taskCategory = '';
    public string $taskPriority = '';
    public string $taskSize = '';
    public ?string $taskDueDate = null;

    public array $categories = [
        'jikko' => 'Jikko', 'entreagiles' => 'EntreAgiles', 'gesthor' => 'Gesthor', 'certmind' => 'CertMind',
        'unicauca' => 'Unicauca', 'personal' => 'Personal', 'salud' => 'Salud', 'finanzas' => 'Finanzas',
        'educacion' => 'Educación', 'hogar' => 'Hogar', 'social' => 'Social', 'creatividad' => 'Creatividad',
        'tecnologia' => 'Tecnología', 'compras' => 'Compras', 'tramites' => 'Trámites', 'otros' => 'Otros',
    ];

    public array $priorities = [
        'urgent-important' => 'Urgente e importante',
        'not-urgent-important' => 'No urgente, importante',
        'urgent-not-important' => 'Urgente, no importante',
        'not-urgent-not-important' => 'No urgente, no importante',
    ];

    public array $sizes = ['XS' => 'XS', 'S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL'];

    public function mount(string $goal): void
    {
        $this->goalId = Goal::query()->findOrFail($goal)->id;
    }

    public function openGoalForm(): void
    {
        $goal = $this->goal();
        $this->loadReminders($goal);
        $kpi = GoalProgress::configuration($goal->numeric_goal);

        $this->title = $goal->title;
        $this->description = $goal->description ?? '';
        $this->status = $goal->status;
        $this->startDate = $goal->start_date?->format('Y-m-d');
        $this->dueDate = $goal->due_date?->format('Y-m-d');
        $this->kpiEnabled = $kpi !== null;
        $this->kpiName = $kpi['name'] ?? '';
        $this->kpiUnit = $kpi['unit'] ?? '';
        $this->kpiDirection = $kpi['direction'] ?? 'increase';
        $this->kpiStartValue = $kpi['startValue'] ?? null;
        $this->kpiTargetValue = $kpi['targetValue'] ?? null;
        $this->showGoalForm = true;
    }

    public function saveGoal(): void
    {
        DB::transaction(fn () => $this->persistGoalWithReminders());
    }

    private function persistGoalWithReminders(): void
    {
        $data = $this->validatedGoalData();
        $this->validateReminders($this->dueDate);
        $goal = $this->goal();

        if ($data['numeric_goal']) {
            $latestValue = $goal->goalNumericEntries()
                ->orderByDesc('date')->orderByDesc('created_at')->value('value');
            $data['numeric_goal']['currentValue'] = $latestValue ?? $data['numeric_goal']['startValue'];
        }

        $goal->update($data);
        $this->saveReminders($goal);
        $this->showGoalForm = false;
    }

    public function updateStatus(string $status): void
    {
        abort_unless(in_array($status, ['active', 'completed', 'abandoned'], true), 422);
        $this->goal()->update(['status' => $status]);
    }

    public function openEntryForm(?string $id = null): void
    {
        $this->resetEntryForm();

        if ($id) {
            $entry = $this->goalEntries()->findOrFail($id);
            $this->editingEntryId = $entry->id;
            $this->entryText = $entry->text;
            $this->entryDate = $entry->date->format('Y-m-d');
            $this->entryIsMilestone = $entry->is_milestone;
        }

        $this->showEntryForm = true;
    }

    public function saveEntry(): void
    {
        $validated = $this->validate([
            'entryText' => ['required', 'string', 'max:5000'],
            'entryDate' => ['required', 'date'],
            'entryIsMilestone' => ['boolean'],
        ]);

        $data = ['text' => trim($validated['entryText']), 'date' => $validated['entryDate'], 'is_milestone' => $validated['entryIsMilestone']];

        if ($this->editingEntryId) {
            $this->goalEntries()->findOrFail($this->editingEntryId)->update($data);
        } else {
            $this->goalEntries()->create($data);
        }

        $this->showEntryForm = false;
        $this->resetEntryForm();
    }

    public function deleteEntry(string $id): void
    {
        $this->goalEntries()->findOrFail($id)->delete();
    }

    public function openNumericForm(?string $id = null): void
    {
        abort_unless(GoalProgress::configuration($this->goal()->numeric_goal), 422);
        $this->resetNumericForm();

        if ($id) {
            $entry = $this->numericEntries()->findOrFail($id);
            $this->editingNumericId = $entry->id;
            $this->numericValue = (float) $entry->value;
            $this->numericDate = $entry->date->format('Y-m-d');
            $this->numericNote = $entry->note ?? '';
        }

        $this->showNumericForm = true;
    }

    public function saveNumericEntry(): void
    {
        abort_unless(GoalProgress::configuration($this->goal()->numeric_goal), 422);
        $validated = $this->validate([
            'numericValue' => ['required', 'numeric'],
            'numericDate' => ['required', 'date'],
            'numericNote' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($validated): void {
            $data = ['value' => $validated['numericValue'], 'date' => $validated['numericDate'], 'note' => trim($validated['numericNote']) ?: null];

            if ($this->editingNumericId) {
                $this->numericEntries()->findOrFail($this->editingNumericId)->update($data);
            } else {
                $this->numericEntries()->create($data);
            }

            $this->syncCurrentValue();
        });

        $this->showNumericForm = false;
        $this->resetNumericForm();
    }

    public function deleteNumericEntry(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $this->numericEntries()->findOrFail($id)->delete();
            $this->syncCurrentValue();
        });
    }

    public function incrementMotivation(string $kind): void
    {
        abort_unless(in_array($kind, ['positive', 'negative'], true), 422);
        $this->goal()->increment($kind.'_count');
    }

    public function openTaskForm(): void
    {
        $this->resetTaskForm();
        $this->showTaskForm = true;
    }

    public function saveTask(): void
    {
        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskDescription' => ['nullable', 'string', 'max:5000'],
            'taskCategory' => ['nullable', 'in:'.implode(',', array_keys($this->categories))],
            'taskPriority' => ['nullable', 'in:'.implode(',', array_keys($this->priorities))],
            'taskSize' => ['nullable', 'in:'.implode(',', array_keys($this->sizes))],
            'taskDueDate' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($validated): void {
            $task = Task::create([
                'task_code' => rand(10000, 99999),
                'title' => trim($validated['taskTitle']),
                'description' => trim($validated['taskDescription']) ?: null,
                'category' => $validated['taskCategory'] ?: null,
                'priority' => $validated['taskPriority'] ?: null,
                'size' => $validated['taskSize'] ?: null,
                'end_date' => $validated['taskDueDate'] ?: null,
            ]);

            TaskAssociation::link($task, $this->goal());
        });

        $this->showTaskForm = false;
        $this->resetTaskForm();
    }

    public function render()
    {
        $goal = Goal::query()->with([
            'goalEntries' => fn ($query) => $query->orderByDesc('date')->orderByDesc('created_at'),
            'goalNumericEntries' => fn ($query) => $query->orderByDesc('date')->orderByDesc('created_at'),
            'tasks' => fn ($query) => $query->orderBy('completed')->orderByDesc('created_at'),
        ])->findOrFail($this->goalId);

        return view('livewire.goal.goal-detail', [
            'goal' => $goal,
            'kpi' => GoalProgress::configuration($goal->numeric_goal),
            'progress' => GoalProgress::calculate($goal->numeric_goal, $goal->start_date, $goal->due_date),
        ]);
    }

    private function goal(): Goal
    {
        return Goal::query()->findOrFail($this->goalId);
    }

    private function goalEntries()
    {
        return $this->goal()->goalEntries();
    }

    private function numericEntries()
    {
        return $this->goal()->goalNumericEntries();
    }

    private function syncCurrentValue(): void
    {
        $goal = Goal::query()->lockForUpdate()->findOrFail($this->goalId);
        $kpi = GoalProgress::configuration($goal->numeric_goal);

        if (! $kpi) {
            return;
        }

        $latest = $goal->goalNumericEntries()->orderByDesc('date')->orderByDesc('created_at')->first();
        $numericGoal = $goal->numeric_goal;
        $numericGoal['currentValue'] = $latest ? (float) $latest->value : $kpi['startValue'];
        $goal->update(['numeric_goal' => $numericGoal]);
    }

    private function validatedGoalData(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,completed,abandoned'],
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
            'status' => $validated['status'],
            'start_date' => $validated['startDate'] ?: null,
            'due_date' => $validated['dueDate'] ?: null,
            'numeric_goal' => $this->kpiEnabled ? [
                'enabled' => true,
                'name' => trim($validated['kpiName']),
                'unit' => trim($validated['kpiUnit']),
                'direction' => $validated['kpiDirection'],
                'startValue' => (float) $validated['kpiStartValue'],
                'targetValue' => (float) $validated['kpiTargetValue'],
            ] : null,
        ];
    }

    private function resetEntryForm(): void
    {
        $this->editingEntryId = null;
        $this->entryText = '';
        $this->entryDate = today()->format('Y-m-d');
        $this->entryIsMilestone = false;
        $this->resetValidation(['entryText', 'entryDate', 'entryIsMilestone']);
    }

    private function resetNumericForm(): void
    {
        $this->editingNumericId = null;
        $this->numericValue = null;
        $this->numericDate = today()->format('Y-m-d');
        $this->numericNote = '';
        $this->resetValidation(['numericValue', 'numericDate', 'numericNote']);
    }

    private function resetTaskForm(): void
    {
        $this->taskTitle = '';
        $this->taskDescription = '';
        $this->taskCategory = '';
        $this->taskPriority = '';
        $this->taskSize = '';
        $this->taskDueDate = null;
        $this->resetValidation(['taskTitle', 'taskDescription', 'taskCategory', 'taskPriority', 'taskSize', 'taskDueDate']);
    }
}
