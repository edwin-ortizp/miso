<?php

namespace App\Livewire\Mood;

use App\Livewire\Concerns\HasUrlDate;
use App\Livewire\Concerns\LogsMoodProgressively;
use App\Models\EnergyEntry;
use App\Models\MoodEntry;
use App\Models\MoodState;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithManagementCard;

#[Layout('layouts.app')]
#[Title('Estado de Ánimo')]
class MoodTracker extends Component
{
    use HasUrlDate, LogsMoodProgressively;
    use WithManagementCard;

    // Editing an entry's primary emotion from the history
    public bool $showEditForm = false;

    public ?string $editingEntryId = null;

    public string $editMoodStateId = '';

    // Reflection wizard
    public ?string $reflectionEntryId = null;

    // Energy form
    public int $energyLevel = 3;

    public string $energyComment = '';

    public bool $showEnergyForm = false;

    public const MOOD_SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
        'intensity' => 'Mayor intensidad',
    ];

    public const ENERGY_SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
        'level' => 'Mayor nivel',
    ];

    #[Url(as: 'msort', history: true, except: 'recent')]
    public string $moodSort = 'recent';

    #[Url(as: 'esort', history: true, except: 'recent')]
    public string $energySort = 'recent';

    public function mount()
    {
        $this->initializeSelectedDate();
    }

    public function updatedMoodSort(): void
    {
        $this->moodSort = array_key_exists($this->moodSort, self::MOOD_SORTS) ? $this->moodSort : 'recent';
        $this->resetPage('moodPage');
    }

    public function updatedEnergySort(): void
    {
        $this->energySort = array_key_exists($this->energySort, self::ENERGY_SORTS) ? $this->energySort : 'recent';
        $this->resetPage('energyPage');
    }

    public function previousDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
    }

    public function nextDay()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
    }

    public function today()
    {
        $this->selectedDate = now()->toDateString();
    }

    /** One tap saves; context and reflection stay optional afterwards. */
    public function saveMood(string $moodStateId)
    {
        $this->logMood($moodStateId);
    }

    public function openEditForm(string $entryId): void
    {
        $entry = MoodEntry::findOrFail($entryId);

        $this->editingEntryId = $entry->id;
        $this->editMoodStateId = $entry->mood_state_id ?? '';
        $this->showEditForm = true;
    }

    public function closeEditForm(): void
    {
        $this->showEditForm = false;
        $this->editingEntryId = null;
    }

    /** Replaces the single primary emotion, leaving intensity, situation and links intact. */
    public function updateMoodState(string $moodStateId): void
    {
        $entry = MoodEntry::findOrFail($this->editingEntryId);
        $state = MoodState::findOrFail($moodStateId);

        $entry->applyMoodState($state);
        $this->closeEditForm();
    }

    public function deleteMood(string $id)
    {
        MoodEntry::whereKey($id)->first()?->delete();

        if ($this->lastMoodEntryId === $id) {
            $this->lastMoodEntryId = null;
        }

        if ($this->reflectionEntryId === $id) {
            $this->reflectionEntryId = null;
        }
    }

    public function openReflection(string $entryId): void
    {
        $this->reflectionEntryId = MoodEntry::findOrFail($entryId)->id;
    }

    #[On('reflection-closed')]
    public function closeReflection(): void
    {
        $this->reflectionEntryId = null;
    }

    public function openEnergyForm()
    {
        $this->energyLevel = 3;
        $this->energyComment = '';
        $this->showEnergyForm = true;
    }

    public function closeEnergyForm()
    {
        $this->showEnergyForm = false;
    }

    public function saveEnergy()
    {
        $now = now();

        EnergyEntry::create([
            'date' => $this->selectedDate,
            'level' => $this->energyLevel,
            'time' => $now->format('H:i'),
            'timestamp' => $now->timestamp,
            'comment' => $this->energyComment ?: null,
        ]);

        $this->showEnergyForm = false;
        $this->energyLevel = 3;
        $this->energyComment = '';
    }

    /** Chat del compañero (CHG-100): energía de un toque, 1 a 5. */
    public function quickEnergy(int $level): array
    {
        if ($level < 1 || $level > 5) {
            return ['ok' => false, 'message' => 'Elige un nivel del 1 al 5.'];
        }
        $this->energyLevel = $level;
        $this->energyComment = '';
        $this->saveEnergy();

        return ['ok' => true];
    }

    public function deleteEnergy(string $id)
    {
        EnergyEntry::where('id', $id)->delete();
    }

    public function render()
    {
        $moodEntries = MoodEntry::whereDate('date', $this->selectedDate)
            ->with(['reflection', 'relationships'])
            ->when($this->moodSort === 'intensity', fn ($q) => $q->orderByDesc('intensity'))
            ->orderBy('timestamp', $this->moodSort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage(), ['*'], 'moodPage');

        $energyEntries = EnergyEntry::whereDate('date', $this->selectedDate)
            ->when($this->energySort === 'level', fn ($q) => $q->orderByDesc('level'))
            ->orderBy('timestamp', $this->energySort === 'oldest' ? 'asc' : 'desc')
            ->paginate($this->perPage(), ['*'], 'energyPage');

        // El promedio del día usa todos los registros, no solo la página visible.
        $avgEnergy = EnergyEntry::whereDate('date', $this->selectedDate)->avg('level');

        return view('livewire.mood.mood-tracker', [
            'moodEntries' => $moodEntries,
            'energyEntries' => $energyEntries,
            'moodSorts' => self::MOOD_SORTS,
            'energySorts' => self::ENERGY_SORTS,
            'moodStates' => $this->moodLogger()->catalog(),
            'prioritizedStates' => $this->moodLogger()->prioritizedStates(),
            'avgEnergy' => $avgEnergy,
            ...$this->moodProgressiveData(),
        ]);
    }
}
