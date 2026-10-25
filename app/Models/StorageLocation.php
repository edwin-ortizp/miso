<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Services\Meal\CatalogNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Where a product is usually kept. Two-level, owner-scoped tree that is archived instead of deleted. */
class StorageLocation extends Model
{
    use BelongsToUser, HasUuids;

    /** Filter value for products without a location. */
    public const NONE = '__none';

    protected $fillable = ['parent_id', 'name'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $location) {
            $location->user_id ??= auth()->id();
            $location->name = trim($location->name);
            $location->normalized_name = CatalogNames::normalize($location->name);
            if ($location->normalized_name === '') {
                throw ValidationException::withMessages(['name' => 'Escribe un nombre válido.']);
            }
            $location->parent_id = $location->parent_id ?: null;
            if ($location->parent_id) {
                $parent = static::withoutGlobalScopes()->where('user_id', $location->user_id)->find($location->parent_id);
                if (! $parent || $parent->parent_id || $parent->is($location) || ($location->exists && $location->children()->exists())) {
                    throw ValidationException::withMessages(['parent_id' => 'Elige una ubicación principal de tu catálogo. Solo se permiten dos niveles.']);
                }
            }
            $duplicate = static::withoutGlobalScopes()->where('user_id', $location->user_id)->whereNull('archived_at')
                ->where('normalized_name', $location->normalized_name)
                ->where(fn ($q) => $location->parent_id ? $q->where('parent_id', $location->parent_id) : $q->whereNull('parent_id'))
                ->when($location->exists, fn ($q) => $q->whereKeyNot($location->getKey()))
                ->exists();
            if ($duplicate && ! $location->archived_at) {
                throw ValidationException::withMessages(['name' => 'Ya existe una ubicación con ese nombre en el mismo lugar.']);
            }
        });
    }

    public function parent()
    {
        // The composite foreign key already guarantees the same owner; no user filter so eager loading works.
        return $this->belongsTo(static::class, 'parent_id')->withoutGlobalScopes();
    }

    public function children()
    {
        return $this->hasMany(static::class, 'parent_id')->withoutGlobalScopes();
    }

    public function shoppingItems()
    {
        return $this->hasMany(ShoppingItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getPathLabelAttribute(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /** Archiving a parent archives its children too; products keep their assignment. */
    public function archive(): void
    {
        $now = now();
        $this->children()->whereNull('archived_at')->update(['archived_at' => $now]);
        $this->forceFill(['archived_at' => $now])->save();
    }

    /** Restoring a child restores its archived parent so it stays reachable. */
    public function restore(): void
    {
        if ($this->parent?->isArchived()) {
            $this->parent->restore();
        }
        $this->forceFill(['archived_at' => null])->save();
    }

    /** id => "Padre › Hija" for the user's active locations, plus any extra ids (e.g. the current archived one). */
    public static function options(?int $userId = null, array $include = []): array
    {
        $userId ??= auth()->id();
        if (! $userId) {
            return [];
        }
        $records = static::withoutGlobalScopes()->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhereIn('id', array_filter($include)))
            ->orderBy('name')->get();
        $options = [];
        foreach ($records->whereNull('parent_id') as $root) {
            $options[$root->id] = $root->name;
            foreach ($records->where('parent_id', $root->id) as $child) {
                $options[$child->id] = $root->name.' › '.$child->name;
            }
        }
        foreach ($records->whereNotNull('parent_id')->whereNotIn('parent_id', $records->pluck('id')) as $orphan) {
            $options[$orphan->id] = $orphan->path_label;
        }

        return $options;
    }

    /** Adds the children of every selected location. */
    public static function expandSelection(array $ids, int $userId): array
    {
        $children = static::withoutGlobalScopes()->where('user_id', $userId)->whereIn('parent_id', $ids)->pluck('id')->all();

        return array_values(array_unique([...$ids, ...$children]));
    }
}
