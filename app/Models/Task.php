<?php

namespace App\Models;

use App\Exceptions\TaskCreationCancelled;
use App\Models\Traits\BelongsToUser;
use App\Services\TaskCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Task extends Model
{
    use BelongsToUser, HasUuids;

    public function save(array $options = [])
    {
        if ($this->exists) {
            if ($this->isDirty('code')) {
                throw new \InvalidArgumentException('El código de la tarea no es editable.');
            }

            return parent::save($options);
        }
        $this->user_id ??= auth()->id();
        if (! $this->user_id) {
            throw new \InvalidArgumentException('La tarea necesita un propietario.');
        }
        $attributes = $this->getAttributes();
        $original = $this->getRawOriginal();
        try {
            return $this->getConnection()->transaction(function () use ($options, $attributes, $original) {
                // A failed attempt may have fired created/saved before rollback.
                $this->exists = false;
                $this->wasRecentlyCreated = false;
                $this->setRawAttributes($original, true);
                $this->setRawAttributes($attributes);
                $codes = app(TaskCodeService::class);
                $codes->lock($this->user_id);
                $this->code = $codes->next($this->user_id, $this->category);
                if (! parent::save($options)) {
                    throw new TaskCreationCancelled;
                }

                return true;
            }, $this->getConnection()->transactionLevel() === 0 ? 3 : 1);
        } catch (\Throwable $e) {
            $this->exists = false;
            $this->wasRecentlyCreated = false;
            $this->setRawAttributes($original, true);
            $this->setRawAttributes($attributes);
            if ($e instanceof TaskCreationCancelled) {
                return false;
            }
            throw $e;
        }
    }

    protected $fillable = [
        'task_code',
        'caldav_uid',
        'caldav_uri',
        'caldav_data',
        'caldav_revision',
        'title',
        'description',
        'external_refs',
        'completed',
        'status_id',
        'completed_at',
        'completion_xp',
        'category',
        'priority',
        'size',
        'start_date',
        'start_is_date',
        'end_date',
        'end_is_date',
        'is_recurrent',
        'is_private',
        'recurrence',
        'recurrence_series_id',
        'is_recurrence_history',
        'progress',
        'elapsed_seconds',
        'timer_start_time',
        'timer_paused',
        'paused_duration',
        'timer_active',
        'estimated_time',
        'flow_position',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'completed_at' => 'datetime',
            'completion_xp' => 'integer',
            'is_recurrent' => 'boolean',
            'is_private' => 'boolean',
            'timer_paused' => 'boolean',
            'timer_active' => 'boolean',
            'estimated_time' => 'integer',
            'recurrence' => 'array',
            'external_refs' => 'array',
            'caldav_revision' => 'integer',
            'start_is_date' => 'boolean',
            'end_is_date' => 'boolean',
            'is_recurrence_history' => 'boolean',
            'timer_start_time' => 'array',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public function getSubtaskProgressAttribute(): ?array
    {
        if (! $this->description) {
            return null;
        }

        preg_match_all('/^[\s]*-\s*\[([ xX])\]/m', $this->description, $matches);

        if (empty($matches[0])) {
            return null;
        }

        $total = count($matches[1]);
        $completed = count(array_filter($matches[1], fn ($m) => strtolower($m) === 'x'));

        return ['completed' => $completed, 'total' => $total];
    }

    /**
     * Normaliza una referencia externa: provider/type en minúsculas y, para Jira, la clave en mayúsculas.
     *
     * @return array{provider: string, type: string, id: string, url?: string, label?: string}
     */
    public static function normalizeExternalRef(array $ref): array
    {
        $provider = mb_strtolower(trim((string) ($ref['provider'] ?? '')));
        $id = trim((string) ($ref['id'] ?? ''));

        return array_filter([
            'provider' => $provider,
            'type' => mb_strtolower(trim((string) ($ref['type'] ?? ''))),
            'id' => $provider === 'jira' ? mb_strtoupper($id) : $id,
            'url' => filled($ref['url'] ?? null) ? trim($ref['url']) : null,
            'label' => filled($ref['label'] ?? null) ? trim($ref['label']) : null,
        ], fn ($value) => $value !== null);
    }

    /** Añade y quita referencias externas sin duplicar la combinación provider+type+id. */
    public function mergeExternalRefs(array $add = [], array $remove = []): ?array
    {
        $key = fn (array $ref) => $ref['provider'].'|'.$ref['type'].'|'.mb_strtolower($ref['id']);
        $refs = collect($this->external_refs ?? [])->keyBy($key);

        foreach ($remove as $ref) {
            $refs->forget($key(static::normalizeExternalRef($ref)));
        }

        foreach ($add as $ref) {
            $ref = static::normalizeExternalRef($ref);
            $refs->put($key($ref), [...($refs->get($key($ref)) ?? []), ...$ref]);
        }

        return $refs->isEmpty() ? null : $refs->values()->all();
    }

    /** Filtra tareas que tengan una referencia externa con ese id (y opcionalmente proveedor). */
    public function scopeWithExternalRef(Builder $query, string $id, ?string $provider = null): Builder
    {
        $id = trim($id);
        // Prefiltro portable (MySQL y SQLite) por texto; la coincidencia exacta se valida después.
        $query->where('external_refs', 'like', '%'.addcslashes($id, '%_').'%');

        if ($provider) {
            $query->where('external_refs', 'like', '%'.addcslashes(mb_strtolower($provider), '%_').'%');
        }

        return $query;
    }

    public function hasExternalRef(string $id, ?string $provider = null): bool
    {
        return collect($this->external_refs ?? [])->contains(fn ($ref) => mb_strtolower($ref['id'] ?? '') === mb_strtolower(trim($id))
            && (! $provider || ($ref['provider'] ?? null) === mb_strtolower($provider)));
    }

    public function getEstimatedTimeLabelAttribute(): ?string
    {
        if (! $this->estimated_time) {
            return null;
        }

        $hours = intdiv($this->estimated_time, 60);
        $minutes = $this->estimated_time % 60;

        return $hours ? $hours.' h'.($minutes ? ' '.$minutes.' min' : '') : $minutes.' min';
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN COALESCE(start_date, end_date) IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(start_date, end_date) ASC')
            ->orderByRaw("CASE WHEN priority = 'urgent-important' THEN 1 WHEN priority = 'not-urgent-important' THEN 2 WHEN priority = 'urgent-not-important' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    /** Tareas cuyo estado es de alguno de los tipos dados (todo, ready, in_progress, done). */
    public function scopeWithStatusType(Builder $query, string|array $types): Builder
    {
        return $query->whereIn('status_id', TaskStatus::query()->select('id')->whereIn('type', (array) $types));
    }

    /**
     * Mantiene status_id y completed coherentes desde cualquier punto de escritura
     * (web, MCP, CalDAV, recurrencias): un estado done completa la tarea, salir de él la
     * reabre, y completar o reabrir por el booleano mueve la tarea al estado correspondiente.
     */
    private static function syncStatus(self $task): void
    {
        if (! $task->user_id) {
            return;
        }

        $statuses = TaskStatus::ensureFor($task->user_id);
        $status = $task->status_id ? $statuses->firstWhere('id', $task->status_id) : null;

        if ($task->status_id && ! $status) {
            $task->status_id = $task->getOriginal('status_id');
            $status = $task->status_id ? $statuses->firstWhere('id', $task->status_id) : null;
        }

        if ($status && $task->isDirty('status_id')) {
            $done = $status->type === TaskStatus::DONE;
            if ($done !== (bool) $task->completed) {
                $task->completed = $done;
                $task->completed_at = $done ? ($task->completed_at ?? now()) : null;
                $task->progress = $done ? 100 : min((int) $task->progress, 99);
                if (! $done) {
                    $task->completion_xp = null;
                }
            }

            return;
        }

        $done = (bool) $task->completed;
        if (! $status || ($status->type === TaskStatus::DONE) !== $done) {
            $task->status_id = ($statuses->firstWhere('type', $done ? TaskStatus::DONE : TaskStatus::TODO)
                ?? $statuses->first())->id;
        }
    }

    protected static function booted(): void
    {
        static::creating(fn (self $task) => static::syncStatus($task));

        static::updating(function (self $task): void {
            if ($task->isDirty(['status_id', 'completed']) || ! $task->status_id) {
                static::syncStatus($task);
            }
        });

        static::creating(function (self $task): void {
            if ($task->flow_position !== null) {
                return;
            }

            $task->flow_position = static::nextFlowPosition($task->category, $task->user_id);
        });

        static::updating(function (self $task): void {
            if (! $task->isDirty('category')) {
                return;
            }

            $task->flow_position = static::nextFlowPosition($task->category, $task->user_id);
        });

        static::deleting(fn (self $task) => $task->associations()->delete());

        static::updated(function (self $task): void {
            if (! $task->wasChanged(['start_date', 'end_date'])) {
                return;
            }

            $date = $task->start_date ?? $task->end_date;

            if (! $date) {
                return;
            }

            $task->healthEvents()->each(fn (HealthEvent $event) => $event->update(['event_date' => $date->toDateString()]));
        });
    }

    private static function nextFlowPosition(?string $category, ?int $userId): int
    {
        $query = static::query()->where('user_id', $userId);

        if ($category === null) {
            $query->whereNull('category');
        } else {
            $query->where('category', $category);
        }

        return ((int) $query->max('flow_position')) + 1;
    }

    public function associations(): HasMany
    {
        return $this->hasMany(TaskAssociation::class);
    }

    public function goals(): MorphToMany
    {
        return $this->morphedByMany(Goal::class, 'target', 'task_associations', 'task_id', 'target_id')
            ->withTimestamps();
    }

    public function relationships(): MorphToMany
    {
        return $this->morphedByMany(Relationship::class, 'target', 'task_associations', 'task_id', 'target_id')
            ->withTimestamps();
    }

    public function healthEvents(): MorphToMany
    {
        return $this->morphedByMany(HealthEvent::class, 'target', 'task_associations', 'task_id', 'target_id')
            ->withTimestamps();
    }
}
