<?php

namespace App\Actions;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Support\Ui\CatalogColors;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reglas del catálogo de estados, compartidas por Ajustes y el MCP:
 * - todo, ready e in_progress admiten varios estados; done es único, fijo y no se elimina.
 * - Siempre queda al menos un estado por hacer.
 * - Eliminar un estado con tareas exige pasarlas a otro estado que no sea done.
 */
class ManageTaskStatuses
{
    public function create(User $user, string $name, string $type, ?string $color = null): TaskStatus
    {
        CatalogColors::validate($color);
        $this->assertType($type);
        if ($type === TaskStatus::DONE) {
            throw new InvalidArgumentException('Solo puede haber un estado de tipo terminado; renombra el que ya existe.');
        }

        $statuses = TaskStatus::ensureFor($user->id);
        $name = $this->cleanName($name);
        $this->assertNameAvailable($user->id, $name);

        $status = new TaskStatus([
            'color' => $color,
            'name' => $name,
            'type' => $type,
            'sort_order' => (int) $statuses->where('type', $type)->max('sort_order') + 1,
        ]);
        $status->user_id = $user->id;
        $status->save();

        return $status;
    }

    public function update(TaskStatus $status, string $name, ?string $type = null, ?string $color = null): TaskStatus
    {
        $name = $this->cleanName($name);
        $this->assertNameAvailable($status->user_id, $name, $status->id);

        if ($type !== null && $type !== $status->type) {
            $this->assertType($type);
            if ($status->type === TaskStatus::DONE || $type === TaskStatus::DONE) {
                throw new InvalidArgumentException('El estado terminado es único: no puede cambiar de tipo ni otro estado convertirse en terminado.');
            }
            if ($status->type === TaskStatus::TODO && $this->countOfType($status->user_id, TaskStatus::TODO) === 1) {
                throw new InvalidArgumentException('Debe quedar al menos un estado de tipo por hacer.');
            }
            $status->type = $type;
            $status->sort_order = (int) TaskStatus::withoutGlobalScopes()->where('user_id', $status->user_id)->where('type', $type)->max('sort_order') + 1;
        }

        if (func_num_args() >= 4) {
            $status->color = CatalogColors::validate($color);
        }

        $status->name = $name;
        $status->save();

        return $status;
    }

    /** Sube o baja el estado dentro de su tipo. */
    public function move(TaskStatus $status, int $direction): void
    {
        $siblings = TaskStatus::withoutGlobalScopes()->where('user_id', $status->user_id)
            ->where('type', $status->type)->ordered()->get()->values();
        $index = $siblings->search(fn (TaskStatus $sibling) => $sibling->is($status));
        $target = $index + ($direction < 0 ? -1 : 1);

        if ($index === false || ! isset($siblings[$target])) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $sibling) {
                $sibling->forceFill(['sort_order' => $position])->save();
            }
        });
    }

    /** Elimina el estado y devuelve cuántas tareas pasaron a $target. */
    public function delete(TaskStatus $status, ?TaskStatus $target = null): int
    {
        if ($status->type === TaskStatus::DONE) {
            throw new InvalidArgumentException('El estado terminado no se puede eliminar; puedes renombrarlo.');
        }
        if ($status->type === TaskStatus::TODO && $this->countOfType($status->user_id, TaskStatus::TODO) === 1) {
            throw new InvalidArgumentException('Debe quedar al menos un estado de tipo por hacer.');
        }

        $tasks = Task::withoutGlobalScopes()->where('user_id', $status->user_id)->where('status_id', $status->id);
        $count = (clone $tasks)->count();

        if ($count > 0) {
            if (! $target || $target->is($status) || $target->user_id !== $status->user_id) {
                throw new InvalidArgumentException('Elige a qué estado pasar sus tareas.');
            }
            if ($target->type === TaskStatus::DONE) {
                throw new InvalidArgumentException('Las tareas no se pueden pasar al estado terminado al eliminar; elige otro estado.');
            }
        }

        DB::transaction(function () use ($status, $target, $tasks, $count) {
            if ($count > 0) {
                // Ambos estados son no terminados: no cambia completed, basta con reasignar.
                (clone $tasks)->update(['status_id' => $target->id, 'updated_at' => now()]);
            }
            $status->delete();
        });

        return $count;
    }

    private function countOfType(int $userId, string $type): int
    {
        return TaskStatus::withoutGlobalScopes()->where('user_id', $userId)->where('type', $type)->count();
    }

    private function assertType(string $type): void
    {
        if (! array_key_exists($type, TaskStatus::TYPES)) {
            throw new InvalidArgumentException('Tipo de estado no válido. Usa: '.implode(', ', array_keys(TaskStatus::TYPES)).'.');
        }
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            throw new InvalidArgumentException('El estado necesita un nombre.');
        }
        if (mb_strlen($name) > 60) {
            throw new InvalidArgumentException('El nombre admite máximo 60 caracteres.');
        }

        return $name;
    }

    private function assertNameAvailable(int $userId, string $name, ?string $ignoreId = null): void
    {
        $taken = TaskStatus::withoutGlobalScopes()->where('user_id', $userId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();

        if ($taken) {
            throw new InvalidArgumentException("Ya tienes un estado llamado “{$name}”.");
        }
    }
}
