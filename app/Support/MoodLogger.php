<?php

namespace App\Support;

use App\Models\MoodEntry;
use App\Models\MoodState;
use App\Models\Relationship;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The one place a mood entry is created, shared by Ánimo, Inicio and Diario so the
 * minimum path stays a single write with the same snapshot, date and source everywhere.
 */
class MoodLogger
{
    /** How many emotions the compact picker shows before "Más emociones". */
    public const PICKER_SIZE = 8;

    /** Estados que ofrece el chat del compañero (un subconjunto de los priorizados del selector). */
    public const CHAT_QUICK_SIZE = 5;

    public function record(MoodState $state, ?string $date = null): MoodEntry
    {
        $now = Carbon::now();

        return MoodEntry::create([
            'date' => $date ?: $now->toDateString(),
            'emoji' => $state->emoji,
            'text' => $state->text,
            'value' => $state->value,
            'time' => $now->format('H:i'),
            'timestamp' => $now->timestamp,
            'mood_state_id' => $state->id,
        ]);
    }

    /** Whether anything can be logged at all; the surfaces offer recovery when not. */
    public function hasActiveStates(): bool
    {
        return MoodState::query()->active()->exists();
    }

    /**
     * Pinned emotions first in the order the user chose, then recently used, then most
     * used, then the rest of the catalog. Everything the picker shows is reachable with
     * one tap; the full vocabulary stays one action away. Inactive emotions never appear.
     *
     * @return Collection<int, MoodState>
     */
    public function prioritizedStates(int $limit = self::PICKER_SIZE): Collection
    {
        $states = MoodState::query()->active()->prioritized()->get()->keyBy('id');

        $pinned = $states->filter(fn (MoodState $state) => $state->is_pinned)->keys()->values();

        $recent = MoodEntry::query()
            ->whereNotNull('mood_state_id')
            ->orderByDesc('timestamp')
            ->limit(60)
            ->pluck('mood_state_id')
            ->unique()
            ->values();

        $frequent = MoodEntry::query()
            ->whereNotNull('mood_state_id')
            ->selectRaw('mood_state_id, COUNT(*) as total')
            ->groupBy('mood_state_id')
            ->orderByDesc('total')
            ->pluck('mood_state_id');

        return $pinned->concat($recent)
            ->concat($frequent)
            ->concat($states->keys())
            ->unique()
            ->map(fn (string $id) => $states->get($id))
            ->filter()
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, MoodState> */
    public function catalog(?string $search = null): Collection
    {
        $term = trim((string) $search);

        return MoodState::query()
            ->active()
            ->when($term !== '', fn ($query) => $query->where('text', 'like', "%{$term}%"))
            ->prioritized()
            ->get();
    }

    /**
     * Candidates for linking: people already present in recent entries, then the rest of
     * the active list. These are suggestions only — nothing is linked without confirmation.
     *
     * @return Collection<int, Relationship>
     */
    public function suggestedRelationships(?string $search = null, int $limit = 6): Collection
    {
        $term = trim((string) $search);

        if ($term !== '') {
            return Relationship::active()
                ->where(fn ($query) => $query->where('full_name', 'like', "%{$term}%")
                    ->orWhere('nickname', 'like', "%{$term}%"))
                ->orderBy('full_name')
                ->limit($limit)
                ->get();
        }

        $recentIds = MoodEntry::query()
            ->chronological()
            ->limit(20)
            ->with('relationships:id')
            ->get()
            ->flatMap(fn (MoodEntry $entry) => $entry->relationships->pluck('id'))
            ->unique()
            ->values();

        $recent = $recentIds->isEmpty()
            ? collect()
            : Relationship::active()->whereKey($recentIds)->get()->sortBy(
                fn (Relationship $relationship) => $recentIds->search($relationship->id)
            )->values();

        if ($recent->count() >= $limit) {
            return $recent->take($limit);
        }

        $fill = Relationship::active()
            ->whereKeyNot($recent->pluck('id')->all())
            ->orderByDesc('last_contact_at')
            ->orderBy('full_name')
            ->limit($limit - $recent->count())
            ->get();

        return $recent->concat($fill)->values();
    }
}
