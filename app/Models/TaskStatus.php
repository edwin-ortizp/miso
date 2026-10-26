<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Estado personalizable de una tarea. El nombre lo elige el usuario; el tipo fija su
 * comportamiento: todo, ready e in_progress admiten varios estados, done solo uno y es el
 * único que marca la tarea como completada.
 */
class TaskStatus extends Model
{
    use BelongsToUser, HasUuids;

    public const TODO = 'todo';

    public const READY = 'ready';

    public const IN_PROGRESS = 'in_progress';

    public const DONE = 'done';

    /** Tipos en el orden del flujo, con su etiqueta e icono. */
    public const TYPES = [
        self::TODO => ['label' => 'Por hacer', 'icon' => 'bi-circle'],
        self::READY => ['label' => 'Listo para ejecutar', 'icon' => 'bi-check2-circle'],
        self::IN_PROGRESS => ['label' => 'En curso', 'icon' => 'bi-play-circle'],
        self::DONE => ['label' => 'Terminado', 'icon' => 'bi-check-circle-fill'],
    ];

    /** Catálogo inicial de cada cuenta. */
    public const DEFAULTS = [
        ['name' => 'Borrador', 'type' => self::TODO],
        ['name' => 'Ready', 'type' => self::READY],
        ['name' => 'WIP', 'type' => self::IN_PROGRESS],
        ['name' => 'Done', 'type' => self::DONE],
    ];

    protected $fillable = ['color', 'name', 'type', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'status_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function getIconAttribute(): string
    {
        return self::TYPES[$this->type]['icon'] ?? 'bi-circle';
    }

    /** Orden del flujo: primero por tipo y dentro de cada tipo por sort_order. */
    public function scopeOrdered(Builder $query): Builder
    {
        $cases = collect(array_keys(self::TYPES))
            ->map(fn ($type, $index) => "WHEN '{$type}' THEN {$index}")
            ->implode(' ');

        return $query->orderByRaw("CASE type {$cases} ELSE 99 END")->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Estados del usuario en orden; crea el catálogo inicial si aún no tiene ninguno y
     * garantiza que siempre exista un estado por hacer y uno terminado.
     *
     * @return Collection<int, TaskStatus>
     */
    public static function ensureFor(int $userId): Collection
    {
        $statuses = static::withoutGlobalScopes()->where('user_id', $userId)->ordered()->get();

        $missing = $statuses->isEmpty()
            ? self::DEFAULTS
            : array_values(array_filter(self::DEFAULTS, fn ($default) => in_array($default['type'], [self::TODO, self::DONE], true)
                && ! $statuses->contains('type', $default['type'])));

        if ($missing === []) {
            return $statuses;
        }

        foreach ($missing as $index => $default) {
            $status = new static(['name' => static::uniqueName($default['name'], $userId), 'type' => $default['type'], 'sort_order' => $index]);
            $status->user_id = $userId;
            $status->save();
        }

        return static::withoutGlobalScopes()->where('user_id', $userId)->ordered()->get();
    }

    /** Primer estado del tipo dado (el predeterminado al entrar en ese tipo). */
    public static function firstOfType(int $userId, string $type): self
    {
        return static::ensureFor($userId)->firstWhere('type', $type)
            ?? static::ensureFor($userId)->firstWhere('type', self::TODO);
    }

    /** Busca por id o por nombre (sin distinguir mayúsculas) dentro de los estados del usuario. */
    public static function resolve(int $userId, string $value): ?self
    {
        $value = trim($value);
        $statuses = static::ensureFor($userId);

        return $statuses->firstWhere('id', $value)
            ?? $statuses->first(fn (self $status) => mb_strtolower($status->name) === mb_strtolower($value));
    }

    public static function uniqueName(string $name, int $userId, ?string $ignoreId = null): string
    {
        $base = Str::limit(trim($name), 55, '');
        $candidate = $base;
        $suffix = 2;

        while (static::withoutGlobalScopes()->where('user_id', $userId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($candidate)])->exists()) {
            $candidate = $base.' '.$suffix++;
        }

        return $candidate;
    }
}
