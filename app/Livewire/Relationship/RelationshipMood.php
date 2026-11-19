<?php

namespace App\Livewire\Relationship;

use App\Livewire\Concerns\WithManagementCard;
use App\Livewire\Relationship\Concerns\ResolvesRelationship;
use App\Support\MoodLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mood del contacto')]
class RelationshipMood extends Component
{
    use ResolvesRelationship, WithManagementCard;

    public string $search = '';
    public string $emotionFilter = '';
    public string $from = '';
    public string $to = '';

    protected function filterProperties(): array
    {
        return ['search', 'emotionFilter', 'from', 'to'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, $this->filterProperties(), true)) {
            $this->resetPage();
        }
    }

    #[On('mood-editor-saved')]
    public function refreshMood(): void
    {
        $this->resetPage();
    }

    public function render(MoodLogger $logger)
    {
        $relationship = $this->relationship();
        $term = trim($this->search);
        $entries = $relationship->moodEntries()->with('relationships')
            ->when($term !== '', fn ($query) => $query->where(fn ($fields) => $fields
                ->where('text', 'like', "%{$term}%")->orWhere('situation', 'like', "%{$term}%")))
            ->when($this->emotionFilter !== '', fn ($query) => $query->where('mood_state_id', $this->emotionFilter))
            ->when($this->from !== '', fn ($query) => $query->where('date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->where('date', '<=', $this->to))
            ->chronological()->orderBy('mood_entries.id')->paginate($this->perPage());

        return view('livewire.relationship.relationship-mood', [
            'relationship' => $relationship,
            'entries' => $entries,
            'emotions' => $logger->catalog()->mapWithKeys(fn ($state) => [$state->id => $state->emoji.' '.$state->text])->all(),
        ]);
    }
}
