<?php

namespace App\Livewire\Home\Panel;

use App\Actions\LogDrink;
use App\Models\DrinkType;
use App\Models\EnergyEntry;
use App\Models\JournalEntry;
use App\Models\MoodState;
use App\Models\NegativeHabitDefinition;
use App\Models\NegativeHabitLog;
use App\Models\Task;
use App\Support\MoodLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class QuickEditor extends Component
{
    #[Locked]
    public string $kind = '';

    #[Locked]
    public string $recordId = '';

    #[Locked]
    public string $selectedDate = '';

    #[Locked]
    public string $journalVersion = '';

    public string $text = '';

    public string $summary = '';

    public string $drinkId = '';

    public $amount = 250;

    public string $moodId = '';

    public $energy = null;

    public string $scheduledDate = '';

    public string $scheduledTime = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    #[On('panel-quick')]
    public function open(string $kind, string $id = ''): void
    {
        abort_unless(in_array($kind, ['water', 'mood', 'journal', 'avoid', 'snooze']), 422);
        $this->resetValidation();
        $this->reset('text', 'summary', 'drinkId', 'amount', 'moodId', 'energy', 'scheduledTime');
        $this->selectedDate = today()->toDateString();
        $this->scheduledDate = $this->selectedDate;
        $this->recordId = $id;
        if ($kind === 'journal') {
            $entry = JournalEntry::whereDate('date', $this->selectedDate)->first();
            $this->text = $entry?->text ?? '';
            $this->summary = $entry?->summary ?? '';
            $this->journalVersion = $this->version($entry);
        }
        if ($kind === 'avoid') {
            NegativeHabitDefinition::findOrFail($id);
        }
        if ($kind === 'snooze') {
            $task = Task::findOrFail($id);
            $date = $task->start_date ?? $task->end_date ?? now();
            $this->scheduledDate = $date->toDateString();
            $this->scheduledTime = ($task->start_date ? $task->start_is_date : $task->end_is_date) ? '' : $date->format('H:i');
        }
        $this->kind = $kind;
    }

    public function close(): void
    {
        $this->kind = '';
        $this->resetValidation();
    }

    public function save(): void
    {
        switch ($this->kind) {
            case 'water':
                $this->validate(['drinkId' => ['required', 'string'], 'amount' => ['required', 'integer', 'between:1,10000']]);
                LogDrink::handle(DrinkType::findOrFail($this->drinkId), $this->selectedDate, (int) $this->amount);
                break;
            case 'mood':
                $this->validate(['moodId' => ['required', 'string'], 'energy' => ['nullable', 'integer', 'between:1,5']]);
                MoodState::active()->findOrFail($this->moodId);
                $entry = app(MoodLogger::class)->record(MoodState::active()->findOrFail($this->moodId), $this->selectedDate);
                $this->dispatch('panel-mood-recorded', id: $entry->id);
                if ($this->energy !== null && $this->energy !== '') {
                    EnergyEntry::create(['date' => $this->selectedDate, 'level' => (int) $this->energy, 'time' => now()->format('H:i'), 'timestamp' => now()->timestamp]);
                }
                break;
            case 'journal':
                $this->validate(['text' => ['required', 'string'], 'summary' => ['nullable', 'string', 'max:255']]);
                $saved = DB::transaction(function () {
                    $entry = JournalEntry::whereDate('date', $this->selectedDate)->lockForUpdate()->first();
                    if ($this->version($entry) !== $this->journalVersion) {
                        $this->addError('text', 'El diario cambió en otra pantalla. Conserva tu borrador y vuelve a abrirlo para revisar los cambios.');

                        return false;
                    }
                    $data = ['text' => $this->text, 'summary' => trim($this->summary) ?: null, 'display_time' => now()->format('H:i')];
                    $entry ? $entry->update($data) : JournalEntry::create(['date' => $this->selectedDate] + $data);

                    return true;
                });
                if (! $saved) {
                    return;
                }
                break;
            case 'avoid':
                $habit = NegativeHabitDefinition::findOrFail($this->recordId);
                $this->validate(['text' => ['nullable', 'string', 'max:5000']]);
                NegativeHabitLog::create(['habit_id' => $habit->id, 'timestamp' => now()->timestamp, 'note' => trim($this->text) ?: null]);
                break;
            case 'snooze':
                $this->validate(['scheduledDate' => ['required', 'date_format:Y-m-d'], 'scheduledTime' => ['nullable', 'date_format:H:i']]);
                $task = Task::findOrFail($this->recordId);
                $new = Carbon::parse($this->scheduledDate.' '.($this->scheduledTime ?: '00:00'), config('app.timezone'));
                $duration = $task->start_date && $task->end_date ? $task->start_date->diffInSeconds($task->end_date, false) : null;
                $task->update($task->start_date ? [
                    'start_date' => $new, 'start_is_date' => $this->scheduledTime === '',
                    'end_date' => $duration !== null ? $new->copy()->addSeconds($duration) : null,
                    'end_is_date' => $this->scheduledTime === '',
                ] : ['end_date' => $new, 'end_is_date' => $this->scheduledTime === '']);
                break;
            default: abort(422);
        }
        $this->close();
        $this->dispatch('panel-changed');
    }

    private function version(?JournalEntry $entry): string
    {
        return hash('sha256', json_encode([$entry?->id, $entry?->text, $entry?->summary]));
    }

    public function render()
    {
        return view('livewire.home.panel.quick-editor', [
            'drinks' => $this->kind === 'water' ? DrinkType::orderBy('name')->pluck('name', 'id')->all() : [],
            'moods' => $this->kind === 'mood' ? MoodState::active()->orderBy('sort_order')->get()->mapWithKeys(fn ($s) => [$s->id => $s->emoji.' '.$s->text])->all() : [],
        ]);
    }
}
