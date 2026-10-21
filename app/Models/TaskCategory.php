<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Services\TaskCodeService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskCategory extends Model
{
    use \App\Models\Traits\HasCategoryHierarchy, BelongsToUser, HasUuids;

    public function save(array $options = [])
    {
        $this->user_id ??= auth()->id();
        if (! $this->user_id) {
            throw new \InvalidArgumentException('La categoría necesita un propietario.');
        }

        return $this->getConnection()->transaction(function () use ($options) {
            $codes = app(TaskCodeService::class);
            $codes->lock($this->user_id);
            if (! $this->exists || $this->isDirty('code_prefix')) {
                $this->code_prefix = $codes->validatePrefix($this->user_id,
                    $this->code_prefix ?: $codes->suggest($this->user_id, $this->name), $this->exists ? $this->id : null);
            }

            return parent::save($options);
        }, $this->getConnection()->transactionLevel() === 0 ? 3 : 1);
    }

    protected $fillable = ['code_prefix', 'parent_id', 'key', 'name', 'icon', 'sort_order'];

    /** Catálogo del usuario autenticado como key => nombre, en su orden. */
    public static function options(): array
    {
        return auth()->id() ? static::hierarchyOptions(auth()->id(), 'key') : [];
    }

    /** Genera un key único para el usuario a partir del nombre. */
    public static function uniqueKey(string $name, int $userId): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $key = $base;
        $suffix = 2;

        while (static::withoutGlobalScopes()->where('user_id', $userId)->where('key', $key)->exists()) {
            $key = $base.'-'.$suffix++;
        }

        return $key;
    }

    public function tasks()
    {
        // El scope de usuario de Task limita las tareas a las del dueño autenticado.
        return $this->hasMany(Task::class, 'category', 'key');
    }
}
