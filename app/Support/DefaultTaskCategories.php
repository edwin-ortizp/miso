<?php

namespace App\Support;

use App\Models\User;

/**
 * Categorías base de las tareas. Cada cuenta las recibe al registrarse y puede
 * renombrarlas, reordenarlas, eliminarlas o agregar las suyas en Tareas › Ajustes.
 * La tarea guarda el `key`, que no cambia al renombrar.
 */
class DefaultTaskCategories
{
    /** Nombre para un key sin categoría en el catálogo: el predeterminado o el key capitalizado. */
    public static function labelFor(string $key): string
    {
        $defaults = array_column(self::all(), 'name', 'key');

        return $defaults[$key] ?? mb_substr(\Illuminate\Support\Str::ucfirst($key), 0, 60);
    }

    /** @return list<array{key: string, name: string, icon: string}> */
    public static function all(): array
    {
        return [
            ['key' => 'personal', 'name' => 'Personal', 'icon' => 'bi-person'],
            ['key' => 'salud', 'name' => 'Salud', 'icon' => 'bi-heart-pulse'],
            ['key' => 'finanzas', 'name' => 'Finanzas', 'icon' => 'bi-cash-coin'],
            ['key' => 'educacion', 'name' => 'Educación', 'icon' => 'bi-mortarboard'],
            ['key' => 'hogar', 'name' => 'Hogar', 'icon' => 'bi-house'],
            ['key' => 'social', 'name' => 'Social', 'icon' => 'bi-people'],
            ['key' => 'creatividad', 'name' => 'Creatividad', 'icon' => 'bi-palette'],
            ['key' => 'tecnologia', 'name' => 'Tecnología', 'icon' => 'bi-cpu'],
            ['key' => 'compras', 'name' => 'Compras', 'icon' => 'bi-bag'],
            ['key' => 'tramites', 'name' => 'Trámites', 'icon' => 'bi-file-earmark-text'],
            ['key' => 'otros', 'name' => 'Otros', 'icon' => 'bi-three-dots'],
        ];
    }

    /** Crea las categorías base que falten. Nunca modifica ni borra las existentes. */
    public static function createFor(User $user): int
    {
        $query = fn () => $user->taskCategories()->withoutGlobalScopes();
        $keys = $query()->pluck('key')->all();
        $names = $query()->pluck('name')->map(fn (string $name) => mb_strtolower($name))->all();
        $order = (int) $query()->max('sort_order');
        $created = 0;

        foreach (self::all() as $definition) {
            if (in_array($definition['key'], $keys, true) || in_array(mb_strtolower($definition['name']), $names, true)) {
                continue;
            }

            $user->taskCategories()->create([...$definition, 'sort_order' => ++$order]);
            $keys[] = $definition['key'];
            $created++;
        }

        return $created;
    }
}
