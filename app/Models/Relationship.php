<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Support\Birthday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

class Relationship extends Model
{
    use \App\Models\Traits\ResolvesCategoryCatalog;

    use BelongsToUser, HasFactory, HasUuids;

    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(UserNotification::class, 'source');
    }

    protected $fillable = [
        'circle_id',
        'full_name',
        'nickname',
        'pronouns',
        'occupation',
        'organization',
        'address',
        'city',
        'document_type',
        'document_number',
        'photo_path',
        'general_notes',
        'category',
        'birthday_date',
        'birthday_year',
        'birthday_month',
        'birthday_day',
        'last_contact_at',
        'next_contact_suggested_at',
        'contact_frequency_days',
        'notes',
        'is_archived',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'birthday_date' => 'date',
            'birthday_year' => 'integer',
            'birthday_month' => 'integer',
            'birthday_day' => 'integer',
            'contact_frequency_days' => 'integer',
            'last_contact_at' => 'datetime',
            'next_contact_suggested_at' => 'datetime',
            'archived_at' => 'datetime',
            'notes' => 'array',
            'is_archived' => 'boolean',
            'document_number' => 'encrypted',
        ];
    }

    public const DOCUMENT_TYPES = [
        'cc' => 'Cédula de ciudadanía',
        'ce' => 'Cédula de extranjería',
        'ti' => 'Tarjeta de identidad',
        'passport' => 'Pasaporte',
        'ppt' => 'Permiso de protección temporal',
        'nit' => 'NIT',
        'other' => 'Otro documento',
    ];

    public function documentTypeLabel(): ?string
    {
        return $this->document_type ? (self::DOCUMENT_TYPES[$this->document_type] ?? self::DOCUMENT_TYPES['other']) : null;
    }

    /** The document for summaries that leave the app (MCP): type and last four characters only. */
    public function maskedDocument(): ?string
    {
        if (! $this->document_number) {
            return null;
        }

        return trim(($this->documentTypeLabel() ?? 'Documento').' terminado en '.mb_substr($this->document_number, -4));
    }

    protected static function booted(): void
    {
        static::deleting(function (self $relationship): void {
            $relationship->taskAssociations()->delete();
            $relationship->tags()->detach();
        });
    }

    public function circle()
    {
        return $this->belongsTo(Circle::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function relationshipEvents(): HasMany
    {
        return $this->hasMany(RelationshipEvent::class);
    }

    public function contactMethods(): HasMany
    {
        return $this->hasMany(RelationshipContactMethod::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(RelationshipAlias::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            RelationshipTag::class,
            'relationship_tag_assignments',
            'relationship_id',
            'relationship_tag_id'
        )->withTimestamps();
    }

    public function taskAssociations(): MorphMany
    {
        return $this->morphMany(TaskAssociation::class, 'target');
    }

    /** Emotional entries the user linked to this person; deleting the person keeps them. */
    public function moodEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            MoodEntry::class,
            'mood_entry_relationship',
            'relationship_id',
            'mood_entry_id'
        )->withTimestamps();
    }

    public function tasks(): MorphToMany
    {
        return $this->morphToMany(Task::class, 'target', 'task_associations', 'target_id', 'task_id')
            ->withTimestamps();
    }

    /** Replace the tag set, carrying the owner onto the pivot rows. */
    public function syncTags(array $tagIds): void
    {
        $owned = RelationshipTag::query()->whereKey($tagIds)->pluck('id');
        $payload = $owned->mapWithKeys(fn (string $id) => [$id => ['user_id' => $this->user_id]])->all();

        $this->tags()->sync($payload);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->where('is_archived', false)->orWhereNull('is_archived'));
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    public function scopeWithBirthday(Builder $query): Builder
    {
        return $query->whereNotNull('birthday_month')->whereNotNull('birthday_day');
    }

    public function birthday(): ?Birthday
    {
        return Birthday::make($this->birthday_month, $this->birthday_day, $this->birthday_year);
    }

    public function displayName(): string
    {
        return $this->nickname ?: $this->full_name;
    }

    /** The relationship's own cadence wins; the circle's is the fallback. */
    public function effectiveContactFrequencyDays(): ?int
    {
        return $this->contact_frequency_days ?: $this->circle?->contact_frequency_days ?: null;
    }

    public function followUpDueAt(): ?Carbon
    {
        $frequency = $this->effectiveContactFrequencyDays();

        if (! $frequency || ! $this->last_contact_at) {
            return null;
        }

        return $this->last_contact_at->copy()->addDays($frequency)->startOfDay();
    }

    public function isFollowUpDue(?Carbon $reference = null): bool
    {
        $dueAt = $this->followUpDueAt();

        return $dueAt !== null && $dueAt->lte(($reference ?? Carbon::today())->copy()->startOfDay());
    }

    public function daysSinceLastContact(?Carbon $reference = null): ?int
    {
        if (! $this->last_contact_at) {
            return null;
        }

        return (int) $this->last_contact_at->copy()->startOfDay()
            ->diffInDays(($reference ?? Carbon::today())->copy()->startOfDay());
    }
}
