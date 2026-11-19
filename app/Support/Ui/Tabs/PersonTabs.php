<?php

namespace App\Support\Ui\Tabs;

use App\Models\Relationship;

/**
 * Pestañas del detalle de una persona y su regreso integrado en la misma fila.
 */
final class PersonTabs
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function for(Relationship $relationship): array
    {
        $params = ['relationship' => $relationship->id];

        return [
            ['label' => 'Inicio', 'route' => 'relationships.show', 'params' => $params, 'icon' => 'bi-house', 'active' => ['relationships.show']],
            ['label' => 'Planes', 'route' => 'relationships.plans', 'params' => $params, 'icon' => 'bi-map', 'active' => ['relationships.plans']],
            ['label' => 'Historial', 'route' => 'relationships.history', 'params' => $params, 'icon' => 'bi-clock-history', 'active' => ['relationships.history']],
            ['label' => 'Tareas relacionadas', 'route' => 'relationships.tasks', 'params' => $params, 'icon' => 'bi-list-check', 'active' => ['relationships.tasks']],
            ['label' => 'Mood ('.$relationship->moodEntries()->count().')', 'route' => 'relationships.mood', 'params' => $params, 'icon' => 'bi-emoji-smile', 'active' => ['relationships.mood']],
        ];
    }

    /**
     * @return array{href: string, label: string}
     */
    public static function back(): array
    {
        return ['href' => route('relationships'), 'label' => 'Volver a Relaciones'];
    }
}
