<?php

namespace App\Livewire\Home;

use App\Actions\LogDrink;
use App\Livewire\Concerns\HandlesHabitActionPrompt;
use App\Livewire\Concerns\HandlesRecurringTaskCompletion;
use App\Livewire\Concerns\LogsMoodProgressively;
use App\Models\DrinkType;
use App\Models\EnergyEntry;
use App\Models\HabitDefinition;
use App\Models\MealPlanEntry;
use App\Models\MoodEntry;
use App\Models\MoodState;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Task;
use App\Services\HabitGamificationService;
use App\Services\TaskGamificationService;
use App\Support\WallPanelData;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('Panel de pared')]
class WallPanel extends Component
{
    use HandlesHabitActionPrompt, HandlesRecurringTaskCompletion, LogsMoodProgressively;

    #[Locked]
    public string $selectedDate = '';

    #[Locked]
    public string $lastUpdatedAt = '';

    public ?string $editTask = null;

    public bool $showDetail = false;

    #[Locked]
    public ?string $detailKind = null;

    #[Locked]
    public ?string $detailId = null;

    public function mount(): void
    {
        $this->refreshPanel();
    }

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    #[On('panel-changed')]
    #[On('task-calendar-refresh')]
    public function refreshPanel(): void
    {
        $this->selectedDate = today()->toDateString();
    }

    public function quickAddWater(string $drinkTypeId, int $amount): void
    {
        $this->refreshPanel();
        validator(compact('amount'), ['amount' => ['required', 'integer', 'min:1', 'max:10000']])->validate();
        LogDrink::handle(DrinkType::findOrFail($drinkTypeId), $this->selectedDate, $amount);
    }

    public function saveMood(string $moodStateId): void
    {
        $this->refreshPanel();
        MoodState::active()->findOrFail($moodStateId);
        $this->logMood($moodStateId);
    }

    #[On('panel-mood-recorded')]
    public function moodRecorded(string $id): void
    {
        $this->lastMoodEntryId = MoodEntry::findOrFail($id)->id;
    }

    public function saveEnergy(int $level): void
    {
        validator(compact('level'), ['level' => ['required', 'integer', 'between:1,5']])->validate();
        EnergyEntry::create(['date' => today()->toDateString(), 'level' => $level, 'time' => now()->format('H:i'), 'timestamp' => now()->timestamp]);
    }

    public function toggleHabit(int $habitId, HabitGamificationService $gamification): void
    {
        $this->refreshPanel();
        $habit = HabitDefinition::findOrFail($habitId);
        $feedback = $gamification->toggle($habit->id, $this->selectedDate);
        $this->dispatch('habit-feedback', ...$feedback);
        $this->openHabitActionPrompt($habit->id, $habit->name, $feedback);
    }

    public function toggleTask(string $id, TaskGamificationService $gamification): void
    {
        $task = Task::findOrFail($id);
        if ($this->prepareRecurringCompletion($task)) {
            $this->showDetail = false;

            return;
        }
        $result = $gamification->toggle($task);
        if ($result['completed']) {
            $this->dispatch('task-completed', ...$result);
        }
    }

    public function openDetail(string $kind, string $id): void
    {
        match ($kind) {
            'task' => Task::findOrFail($id),
            'plan' => Plan::findOrFail($id),
            'person' => Relationship::findOrFail($id),
            'meal' => MealPlanEntry::findOrFail($id),
            default => abort(422),
        };
        $this->detailKind = $kind;
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->reset('detailKind', 'detailId');
        $this->showDetail = false;
    }

    public function render()
    {
        $data = app(WallPanelData::class)->forToday();
        $detail = match ($this->detailKind) {
            'task' => Task::find($this->detailId),
            'plan' => Plan::with(['relationships', 'visits'])->find($this->detailId),
            'person' => Relationship::with('circle')->find($this->detailId),
            'meal' => MealPlanEntry::with(['items.recipe', 'items.preparation'])->find($this->detailId),
            default => null,
        };

        $viewData = $data + ['detail' => $detail, 'moodStates' => $this->moodLogger()->prioritizedStates(), ...$this->moodProgressiveData()];
        // Only publish a new timestamp once all summary queries succeeded.
        $this->lastUpdatedAt = now()->format('Y-m-d\TH:i:s.uP');

        return view('livewire.home.wall-panel', $viewData);
    }
}
