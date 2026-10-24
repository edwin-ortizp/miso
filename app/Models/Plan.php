<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Plan extends Model
{
    use BelongsToUser, HasFactory, HasUuids;

    public const TYPES = [
        'restaurant' => 'Restaurante',
        'dessert' => 'Postres',
        'place' => 'Lugar',
        'museum' => 'Museo',
        'activity' => 'Actividad',
        'cinema' => 'Cine',
        'concert' => 'Concierto',
        'event' => 'Evento',
        'trip' => 'Viaje',
        'nature' => 'Naturaleza',
        'experience' => 'Experiencia',
    ];

    public const TYPE_ICONS = [
        'restaurant' => 'bi-egg-fried',
        'dessert' => 'bi-cup-straw',
        'place' => 'bi-geo-alt',
        'museum' => 'bi-bank',
        'activity' => 'bi-stars',
        'cinema' => 'bi-film',
        'concert' => 'bi-music-note-beamed',
        'event' => 'bi-calendar-event',
        'trip' => 'bi-airplane',
        'nature' => 'bi-tree',
        'experience' => 'bi-balloon-heart',
    ];

    /** Quick filters of the list; every type belongs to exactly one group. */
    public const GROUPS = [
        'places' => ['label' => 'Lugares', 'icon' => 'bi-geo-alt', 'types' => ['restaurant', 'dessert', 'place', 'museum']],
        'activities' => ['label' => 'Actividades', 'icon' => 'bi-stars', 'types' => ['activity', 'cinema']],
        'events' => ['label' => 'Eventos', 'icon' => 'bi-calendar-event', 'types' => ['concert', 'event']],
        'trips' => ['label' => 'Viajes', 'icon' => 'bi-airplane', 'types' => ['trip', 'nature']],
        'others' => ['label' => 'Otros', 'icon' => 'bi-three-dots', 'types' => ['experience']],
    ];

    public const STATUSES = [
        'pending' => 'Pendiente',
        'scheduled' => 'Programado',
        'done' => 'Realizado',
        'archived' => 'Archivado',
    ];

    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(UserNotification::class, 'source');
    }

    public function reminders(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Reminder::class, 'source');
    }

    protected $fillable = [
        'title',
        'type',
        'category',
        'plan_category_id',
        'city',
        'address',
        'status',
        'scheduled_on',
        'ends_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class);
    }

    public function relationships(): BelongsToMany
    {
        return $this->belongsToMany(Relationship::class);
    }

    public function planCategory(): BelongsTo
    {
        return $this->belongsTo(PlanCategory::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PlanTag::class, 'plan_tag_assignments', 'plan_id', 'plan_tag_id')
            ->withTimestamps();
    }

    /** Replace the tag set using only tags owned by this plan's user. */
    public function syncTags(array $tagIds): void
    {
        $owned = PlanTag::query()->whereKey($tagIds)->pluck('id');
        $payload = $owned->mapWithKeys(fn (string $id) => [$id => ['user_id' => $this->user_id]])->all();

        $this->tags()->sync($payload);
    }

    public function links(): HasMany
    {
        return $this->hasMany(PlanLink::class)->orderBy('created_at');
    }

    /** Images by URL, main one first. */
    public function images(): HasMany
    {
        return $this->hasMany(PlanImage::class)->orderBy('position');
    }

    public function mainImageUrl(): ?string
    {
        return $this->relationLoaded('images') ? $this->images->first()?->url : $this->images()->value('url');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(PlanVisit::class);
    }

    /** Adds `visits_count` and `last_visited_on`, optionally counting only visits with one person. */
    public function scopeWithVisitStats(Builder $query, ?string $relationshipId = null): Builder
    {
        $constraint = fn (Builder $visits) => $relationshipId
            ? $visits->whereHas('relationships', fn (Builder $people) => $people->whereKey($relationshipId))
            : $visits;

        return $query
            ->withCount(['visits as visits_count' => $constraint])
            ->withMax(['visits as last_visited_on' => $constraint], 'visited_on');
    }

    /** Plans shared with a person directly or through their circle. */
    public function scopeForRelationship(Builder $query, Relationship $relationship): Builder
    {
        return $query->where(function (Builder $scope) use ($relationship): void {
            $scope->whereHas('relationships', fn (Builder $people) => $people->whereKey($relationship->id));

            if ($relationship->circle_id) {
                $scope->orWhereHas('circles', fn (Builder $circles) => $circles->whereKey($relationship->circle_id));
            }
        });
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Plan';
    }

    public function icon(): string
    {
        return self::TYPE_ICONS[$this->type] ?? 'bi-map';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? self::STATUSES['pending'];
    }

    /** Days left until the scheduled date; null when the plan has no upcoming date. */
    public function daysUntil(): ?int
    {
        if (! $this->scheduled_on || $this->scheduled_on->isPast() && ! $this->scheduled_on->isToday()) {
            return null;
        }

        return (int) today()->diffInDays($this->scheduled_on);
    }

    public function lastVisitLabel(): string
    {
        $last = $this->getAttribute('last_visited_on');

        if (! $last) {
            return 'nunca';
        }

        $date = Carbon::parse($last)->startOfDay();

        return $date->isToday() ? 'hoy' : $date->locale('es')->diffForHumans(today(), ['parts' => 1, 'syntax' => Carbon::DIFF_RELATIVE_TO_NOW]);
    }

    public static function visitsLabel(int $count): string
    {
        return $count.' '.($count === 1 ? 'visita' : 'visitas');
    }
}
