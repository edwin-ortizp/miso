<?php

namespace App\Support;

use App\Models\User;

/** Bebidas iniciales. Cada cuenta puede renombrarlas, eliminarlas o agregar las suyas. */
class DefaultDrinkTypes
{
    /** @return list<array{name: string, hydration_factor: float, color: string, icon: string, category: string}> */
    public static function all(): array
    {
        return [
            ['name' => 'Agua', 'hydration_factor' => 1.00, 'color' => '#3b82f6', 'icon' => 'Droplet', 'category' => 'water'],
            ['name' => 'Café', 'hydration_factor' => 0.70, 'color' => '#78350f', 'icon' => 'Coffee', 'category' => 'coffee'],
            ['name' => 'Té', 'hydration_factor' => 0.85, 'color' => '#84cc16', 'icon' => 'Coffee', 'category' => 'tea'],
            ['name' => 'Jugo', 'hydration_factor' => 0.80, 'color' => '#f97316', 'icon' => 'Apple', 'category' => 'juice'],
            ['name' => 'Gaseosa', 'hydration_factor' => 0.50, 'color' => '#a3a3a3', 'icon' => 'Wine', 'category' => 'soda'],
            ['name' => 'Leche', 'hydration_factor' => 0.85, 'color' => '#e5e5e5', 'icon' => 'Milk', 'category' => 'milk'],
            ['name' => 'Bebida Deportiva', 'hydration_factor' => 0.90, 'color' => '#06b6d4', 'icon' => 'Zap', 'category' => 'sports'],
            ['name' => 'Cerveza', 'hydration_factor' => 0.60, 'color' => '#fbbf24', 'icon' => 'Beer', 'category' => 'beer'],
            ['name' => 'Vino', 'hydration_factor' => 0.50, 'color' => '#dc2626', 'icon' => 'Wine', 'category' => 'wine'],
            ['name' => 'Batido', 'hydration_factor' => 0.80, 'color' => '#ec4899', 'icon' => 'Cup', 'category' => 'smoothie'],
        ];
    }

    /** Crea las bebidas base que falten. Nunca modifica ni borra las existentes. */
    public static function createFor(User $user): int
    {
        $existing = $user->drinkTypes()->withoutGlobalScopes()->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))->all();
        $created = 0;

        foreach (self::all() as $definition) {
            if (in_array(mb_strtolower($definition['name']), $existing, true)) {
                continue;
            }
            $user->drinkTypes()->create($definition);
            $existing[] = mb_strtolower($definition['name']);
            $created++;
        }

        return $created;
    }
}
