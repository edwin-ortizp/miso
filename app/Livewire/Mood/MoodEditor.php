<?php

namespace App\Livewire\Mood;

use App\Models\MoodEntry;
use App\Models\MoodState;
use App\Models\Relationship;
use App\Support\MoodLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MoodEditor extends Component
{
    #[Locked]
    public ?string $contactId = null;

    #[Locked]
    public ?string $entryId = null;

    #[Locked]
    public string $mode = '';

    public string $emotion = '';
    public string $date = '';
    public string $situation = '';
    public array $contacts = [];

    public function open(string $mode, ?string $id = null): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(in_array($mode, ['create', 'edit', 'view'], true), 404);
        abort_unless($mode === 'create' ? $id === null && $this->contactId !== null : $id !== null, 404);

        $entry = $id ? $this->entry($id) : null;
        $this->mode = $mode;
        $this->entryId = $entry?->id;
        $this->emotion = $entry?->mood_state_id ?? '';
        $this->date = $entry?->date->toDateString() ?? today()->toDateString();
        $this->situation = $entry?->situation ?? '';
        $this->contacts = $entry?->relationships->modelKeys() ?? [];
        $this->resetValidation();
    }

    public function close(): void
    {
        $this->reset('mode', 'entryId', 'emotion', 'date', 'situation', 'contacts');
        $this->resetValidation();
    }

    public function save(MoodLogger $logger): void
    {
        abort_unless(auth()->check() && in_array($this->mode, ['create', 'edit'], true), 403);

        DB::transaction(function () use ($logger): void {
            $entry = $this->entryId ? $this->entry($this->entryId, true) : null;
            $this->validate([
                'emotion' => $entry && $entry->mood_state_id === ($this->emotion ?: null) ? ['string'] : [
                    'required', Rule::exists('mood_states', 'id')->where('user_id', auth()->id())->where('is_active', true),
                ],
                'date' => ['required', 'date_format:Y-m-d'],
                'situation' => ['nullable', 'string', 'max:500'],
                'contacts' => ['array'],
                'contacts.*' => ['required', 'uuid', 'distinct', Rule::exists('relationships', 'id')->where('user_id', auth()->id())],
            ]);

            if (! $entry) {
                $entry = $logger->record(MoodState::active()->findOrFail($this->emotion), $this->date);
            } elseif ($entry->mood_state_id !== ($this->emotion ?: null)) {
                $entry->applyMoodState(MoodState::active()->findOrFail($this->emotion));
            }

            $entry->update(['date' => $this->date, 'situation' => trim($this->situation) ?: null]);
            $contactIds = $this->contacts;
            if ($this->mode === 'create') {
                $contactIds[] = Relationship::where('user_id', auth()->id())->findOrFail($this->contactId)->id;
            }
            $entry->syncRelationships(array_unique($contactIds));
        }, 3);

        $this->close();
        $this->dispatch('mood-editor-saved');
    }

    public function delete(string $id): void
    {
        abort_unless(auth()->check(), 403);
        DB::transaction(fn () => $this->entry($id, true)->delete());
        $this->close();
        $this->dispatch('mood-editor-saved');
    }

    private function entry(string $id, bool $lock = false): MoodEntry
    {
        return MoodEntry::where('user_id', auth()->id())
            ->when($this->contactId, fn ($query) => $query->whereHas('relationships', fn ($people) => $people->whereKey($this->contactId)))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with('relationships')
            ->findOrFail($id);
    }

    public function render(MoodLogger $logger)
    {
        $entry = $this->entryId ? $this->entry($this->entryId) : null;
        $options = in_array($this->mode, ['create', 'edit'], true)
            ? Relationship::where('user_id', auth()->id())
                ->where(fn ($query) => $query->active()->orWhereIn('id', $entry?->relationships->modelKeys() ?? []))
                ->when($this->mode === 'create', fn ($query) => $query->whereKeyNot($this->contactId))
                ->orderBy('full_name')->get()->mapWithKeys(fn ($person) => [$person->id => $person->displayName()])->all()
            : [];

        return view('livewire.mood.mood-editor', [
            'entry' => $entry,
            'options' => $options,
            'origin' => $this->mode === 'create' ? Relationship::findOrFail($this->contactId) : null,
            'states' => in_array($this->mode, ['create', 'edit'], true) ? $logger->catalog() : collect(),
        ]);
    }
}
