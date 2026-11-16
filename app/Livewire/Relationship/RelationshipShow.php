<?php

namespace App\Livewire\Relationship;

use App\Livewire\Relationship\Concerns\ManagesRelationshipEvents;
use App\Livewire\Relationship\Concerns\ManagesRelationshipTasks;
use App\Livewire\Relationship\Concerns\ResolvesRelationship;
use App\Models\Relationship;
use App\Services\Social\Friends;
use App\Support\Relationships\RelationshipAgenda;
use App\Support\Relationships\RelationshipHistoryEntries;
use App\Support\Relationships\RelationshipQuickStats;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inicio del detalle de una persona: primero lo que hay que gestionar (pendientes y próximos),
 * después lo vivido (historial reciente); los datos y estadísticas quedan en el panel derecho.
 */
#[Layout('layouts.app')]
#[Title('Detalle de la relación')]
class RelationshipShow extends Component
{
    use ManagesRelationshipEvents;
    use ManagesRelationshipTasks;
    use ResolvesRelationship;

    public bool $showFriendLink = false;
    public string $friendChoice = '';

    public function openFriendLink(): void
    {
        $this->friendChoice = (string) ($this->relationship()->friend_user_id ?? '');
        $this->showFriendLink = true;
    }

    public function closeFriendLink(): void
    {
        $this->showFriendLink = false;
        $this->resetValidation();
    }

    /** Vincula la ficha con un amigo aceptado; solo se guarda su id, la ficha nunca se comparte. */
    public function saveFriendLink(Friends $friends): void
    {
        $allowed = $friends->friends(auth()->user())->pluck('id')->map(fn ($id) => (string) $id);
        if ($this->friendChoice !== '' && ! $allowed->contains($this->friendChoice)) {
            $this->addError('friendChoice', 'Elige a uno de tus amigos.');

            return;
        }
        $this->relationship()->update(['friend_user_id' => $this->friendChoice === '' ? null : (int) $this->friendChoice]);
        $this->closeFriendLink();
    }

    public function unlinkFriend(): void
    {
        $this->relationship()->update(['friend_user_id' => null]);
    }

    public function markContact(): void
    {
        $this->relationship()->update(['last_contact_at' => now()]);
    }

    public function toggleArchive(): void
    {
        $relationship = $this->relationship();

        $relationship->update([
            'is_archived' => ! $relationship->is_archived,
            'archived_at' => $relationship->is_archived ? null : now(),
        ]);
    }

    public function render()
    {
        $relationship = Relationship::query()
            ->with(['circle', 'tags', 'contactMethods', 'aliases'])
            ->findOrFail($this->relationshipId);

        return view('livewire.relationship.relationship-show', [
            'relationship' => $relationship,
            'birthday' => $relationship->birthday(),
            'agenda' => RelationshipAgenda::upcoming($relationship),
            'recentHistory' => RelationshipHistoryEntries::for($relationship)->take(5),
            'quickStats' => RelationshipQuickStats::for($relationship),
            'friendOptions' => $this->showFriendLink ? app(Friends::class)->friends(auth()->user())->pluck('name', 'id')->all() : [],
            'linkedFriend' => $relationship->friend_user_id ? \App\Models\User::find($relationship->friend_user_id) : null,
        ]);
    }
}
