<?php

namespace App\Livewire\Plan;

use App\Livewire\Plan\Concerns\ManagesPlanDialogs;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskAssociation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detalle del plan')]
class PlanShow extends Component
{
    use ManagesPlanDialogs;

    #[Locked]
    public string $planId;

    #[Locked]
    public string $section = 'details';

    public ?string $surpriseMessage = null;

    public bool $showTaskLinks = false;

    public array $selectedTaskIds = [];

    public function mount(string $plan, string $section = 'details'): void
    {
        $this->planId = Plan::query()->findOrFail($plan)->id;
        $this->section = $section;
    }

    public function deletePlan(string $id): void
    {
        DB::transaction(fn () => $this->ownedPlanForUpdate($id)->delete());

        $this->redirectRoute('plans', navigate: true);
    }

    public function openTaskLinks(): void
    {
        $this->resetValidation('selectedTaskIds');
        $this->selectedTaskIds = [];
        $this->showTaskLinks = true;
    }

    public function closeTaskLinks(): void
    {
        $this->showTaskLinks = false;
    }

    public function saveTaskLinks(): void
    {
        $validated = $this->validate([
            'selectedTaskIds' => ['required', 'array', 'min:1', 'max:100'],
            'selectedTaskIds.*' => ['required', 'uuid', 'distinct'],
        ]);

        DB::transaction(function () use ($validated): void {
            $plan = $this->ownedPlanForUpdate($this->planId);
            $tasks = Task::where('user_id', $plan->user_id)->whereKey($validated['selectedTaskIds'])->get();
            if ($tasks->count() !== count($validated['selectedTaskIds'])) {
                throw ValidationException::withMessages(['selectedTaskIds' => 'Alguna tarea ya no está disponible. Revisa tu selección.']);
            }

            foreach ($tasks as $task) {
                TaskAssociation::link($task, $plan);
            }
        }, 3);

        $this->closeTaskLinks();
    }

    public function unlinkTask(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $plan = $this->ownedPlanForUpdate($this->planId);
            TaskAssociation::unlink($plan->tasks()->findOrFail($id), $plan);
        }, 3);
    }

    #[On('task-editor-saved')]
    public function refreshTasks(): void {}

    private function ownedPlanForUpdate(string $id): Plan
    {
        abort_unless(auth()->check(), 403);

        return Plan::where('user_id', auth()->id())->lockForUpdate()->findOrFail($id);
    }

    public function render()
    {
        $plan = Plan::query()
            ->with(['circles', 'relationships', 'tags', 'links', 'images', 'planCategory.parent', 'country', 'catalogCity'])
            ->withVisitStats()
            ->findOrFail($this->planId);

        $visits = $plan->visits()
            ->with('relationships')
            ->orderByDesc('visited_on')
            ->orderByDesc('created_at')
            ->get();

        // Everyone associated with the plan or who went at least once, with their own count.
        $people = $visits
            ->flatMap(fn ($visit) => $visit->relationships->map(fn ($person) => ['person' => $person, 'date' => $visit->visited_on]))
            ->groupBy(fn (array $row) => $row['person']->id)
            ->map(fn ($rows) => ['person' => $rows->first()['person'], 'count' => $rows->count(), 'last' => $rows->max('date')]);

        foreach ($plan->relationships as $person) {
            $people->put($person->id, $people->get($person->id, ['person' => $person, 'count' => 0, 'last' => null]));
        }

        $tasks = $this->section === 'tasks'
            ? $plan->tasks()->with('status')->orderBy('completed')->orderBy('title')->get()
            : null;

        $tabs = [
            ['label' => 'Detalles', 'route' => 'plans.show', 'icon' => 'bi-card-text'],
            ['label' => 'Historial ('.$visits->count().')', 'route' => 'plans.show.history', 'icon' => 'bi-clock-history'],
            ['label' => 'Tareas ('.($tasks?->count() ?? $plan->tasks()->count()).')', 'route' => 'plans.show.tasks', 'icon' => 'bi-list-check'],
            ['label' => 'Personas ('.$people->count().')', 'route' => 'plans.show.people', 'icon' => 'bi-people'],
            ['label' => 'Enlaces ('.$plan->links->count().')', 'route' => 'plans.show.links', 'icon' => 'bi-link-45deg'],
        ];
        foreach ($tabs as &$tab) {
            $tab['params'] = ['plan' => $plan->id];
        }
        unset($tab);

        $taskOptions = $this->showTaskLinks
            ? Task::where('user_id', $plan->user_id)
                ->whereNotIn('id', $plan->tasks()->select('tasks.id')->getQuery())
                ->orderBy('title')->get(['id', 'title', 'task_code'])
                ->mapWithKeys(fn (Task $task) => [$task->id => $task->title.' · '.$task->task_code])->all()
            : [];

        return view('livewire.plan.plan-show', [
            'plan' => $plan,
            'activeRoute' => $this->section === 'details' ? 'plans.show' : 'plans.show.'.$this->section,
            'detailTabs' => $tabs,
            'visits' => $visits,
            'people' => $people->sortByDesc('count')->values(),
            'tasks' => $tasks,
            'taskOptions' => $taskOptions,
        ] + $this->planDialogOptions());
    }
}
