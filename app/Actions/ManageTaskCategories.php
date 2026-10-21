<?php

namespace App\Actions;

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Altas, cambios y bajas del catálogo de categorías de tareas, compartidas por Tareas › Ajustes y MCP.
 * La tarea guarda el key, que no cambia al renombrar; al eliminar, sus tareas pasan a otra categoría o quedan sin ella.
 */
class ManageTaskCategories
{
    public function create(User $user, string $name, ?string $icon = null, ?string $parentId = null, ?string $codePrefix = null): TaskCategory
    {
        $name = $this->validName($user, $name);

        return $user->taskCategories()->create([
            'parent_id' => $parentId,
            'code_prefix' => $codePrefix,
            'key' => TaskCategory::uniqueKey($name, $user->id),
            'name' => $name,
            'icon' => $icon ?: 'bi-tag',
            'sort_order' => (int) $user->taskCategories()->withoutGlobalScopes()->max('sort_order') + 1,
        ]);
    }

    public function update(TaskCategory $category, string $name, ?string $icon = null, ?string $parentId = null, ?string $codePrefix = null): TaskCategory
    {
        $category->update([
            'code_prefix' => $codePrefix ?? $category->code_prefix,
            'parent_id' => func_num_args() >= 4 ? $parentId : $category->parent_id,
            'name' => $this->validName($category->user, $name, $category),
            'icon' => $icon ?: $category->icon,
        ]);

        return $category;
    }

    /** @return int Tareas reasignadas. */
    public function delete(TaskCategory $category, ?TaskCategory $reassignTo = null): int
    {
        $category->ensureHasNoChildren();
        if ($reassignTo && ($reassignTo->is($category) || $reassignTo->user_id !== $category->user_id)) {
            throw new InvalidArgumentException('Elige otra categoría para reasignar las tareas.');
        }

        return DB::transaction(function () use ($category, $reassignTo) {
            $moved = Task::withoutGlobalScopes()
                ->where('user_id', $category->user_id)
                ->where('category', $category->key)
                ->get()
                // Uno a uno para que Task recalcule su posición en el flujo de la nueva categoría.
                ->each(fn (Task $task) => $task->update(['category' => $reassignTo?->key]))
                ->count();

            $category->delete();

            return $moved;
        });
    }

    /** Mueve la categoría una posición arriba (-1) o abajo (+1). */
    public function move(TaskCategory $category, int $direction): void
    {
        $ordered = $category->user->taskCategories()->withoutGlobalScopes()->where('parent_id', $category->parent_id)->orderBy('sort_order')->orderBy('name')->get()->values();
        $index = $ordered->search(fn (TaskCategory $item) => $item->is($category));
        $target = $index + ($direction < 0 ? -1 : 1);

        if ($index === false || ! isset($ordered[$target])) {
            return;
        }

        $swapped = $ordered->all();
        [$swapped[$index], $swapped[$target]] = [$swapped[$target], $swapped[$index]];

        DB::transaction(function () use ($swapped) {
            foreach ($swapped as $position => $item) {
                if ($item->sort_order !== $position + 1) {
                    $item->update(['sort_order' => $position + 1]);
                }
            }
        });
    }

    private function validName(User $user, string $name, ?TaskCategory $ignore = null): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 60) {
            throw new InvalidArgumentException('El nombre de la categoría debe tener entre 1 y 60 caracteres.');
        }

        $taken = $user->taskCategories()->withoutGlobalScopes()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('Ya tienes una categoría con ese nombre.');
        }

        return $name;
    }
}
