<?php

namespace App\Support\Relationships;

use App\Models\PlanVisit;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Support\EventDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo vivido con una persona: acontecimientos pasados y visitas a planes compartidos,
 * del más reciente al más antiguo. Incluye los acontecimientos sensibles porque es su vista privada.
 */
final class RelationshipHistoryEntries
{
    public const TYPES = ['event' => 'Acontecimientos', 'plan' => 'Planes', 'mood' => 'Mood'];

    /**
     * @param  array{q?: string, type?: string, category?: string, archived?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public static function for(Relationship $relationship, array $filters = [], ?Carbon $reference = null): Collection
    {
        $today = ($reference ?? Carbon::today())->copy()->startOfDay();
        $type = $filters['type'] ?? '';
        $category = $filters['category'] ?? '';
        $archived = (bool) ($filters['archived'] ?? false);
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $rows = collect();

        if ($type === '' || $type === 'event') {
            $relationship->relationshipEvents()
                ->where(function (Builder $query) use ($today, $archived): void {
                    $query->where(fn (Builder $past) => $past->where('is_archived', false)->whereDate('ends_on', '<', $today->toDateString()));

                    if ($archived) {
                        $query->orWhere('is_archived', true);
                    }
                })
                ->when($category !== '', fn (Builder $query) => $query->whereIn('category', \App\Support\CategoryCatalog::expand('relationship-events', [$category])))
                ->get()
                ->each(fn (RelationshipEvent $event) => $rows->push([
                    'kind' => 'event',
                    'id' => $event->id,
                    'date' => $event->starts_on,
                    'dateLabel' => $event->dateLabel(),
                    'title' => $event->title,
                    'type' => 'Acontecimiento',
                    'typeTone' => 'primary',
                    'icon' => 'bi-calendar-event',
                    'category' => $event->categoryLabel(),
                    'notes' => $event->notes,
                    'archived' => $event->is_archived,
                    'sensitive' => $event->is_sensitive,
                    'url' => null,
                ]));
        }

        // Las categorías pertenecen a los acontecimientos; con una categoría elegida no hay visitas.
        if (($type === '' || $type === 'plan') && $category === '') {
            PlanVisit::query()
                ->whereHas('relationships', fn (Builder $people) => $people->whereKey($relationship->id))
                ->with('plan')
                ->get()
                ->filter(fn (PlanVisit $visit) => $visit->plan !== null)
                ->each(fn (PlanVisit $visit) => $rows->push([
                    'kind' => 'visit',
                    'id' => $visit->id,
                    'date' => $visit->visited_on,
                    'dateLabel' => EventDate::day($visit->visited_on)->label(),
                    'title' => $visit->plan->title,
                    'type' => 'Plan',
                    'typeTone' => 'success',
                    'icon' => $visit->plan->icon(),
                    'category' => $visit->plan->typeLabel(),
                    'notes' => $visit->comment,
                    'archived' => false,
                    'sensitive' => false,
                    'url' => route('plans.show', $visit->plan_id),
                ]));
        }

        if (($type === '' || $type === 'mood') && $category === '') {
            $relationship->moodEntries()->chronological()->get()
                ->each(fn ($entry) => $rows->push([
                    'kind' => 'mood',
                    'id' => $entry->id,
                    'date' => $entry->date,
                    'dateLabel' => EventDate::day($entry->date)->label(),
                    'title' => $entry->emoji.' '.$entry->text,
                    'type' => 'Mood',
                    'typeTone' => 'neutral',
                    'icon' => 'bi-emoji-smile',
                    'category' => '',
                    'notes' => $entry->situation,
                    'archived' => false,
                    'sensitive' => false,
                    'url' => null,
                ]));
        }

        if ($search !== '') {
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['title'].' '.($row['notes'] ?? '')), $search));
        }

        return $rows->sortByDesc(fn (array $row) => $row['date']?->format('Ymd') ?? '')->values();
    }
}
