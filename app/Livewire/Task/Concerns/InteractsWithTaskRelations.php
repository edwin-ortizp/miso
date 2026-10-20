<?php

namespace App\Livewire\Task\Concerns;

use App\Models\Goal;
use App\Models\HealthEvent;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\TaskAssociation;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Filas módulo + registro del editor; se sincronizan con TaskAssociation al guardar. */
trait InteractsWithTaskRelations
{
    /**
     * Siempre hay al menos una fila con ambas claves: el combobox se enlaza con
     * `$wire.entangle('relations.N.id')` y no puede escribir en rutas inexistentes.
     *
     * @var array<int, array{type: string, id: string}>
     */
    public array $relations = [['type' => '', 'id' => '']];

    public const RELATION_MODULES = [
        'relationship' => 'Personas',
        'goal' => 'Metas',
        'health-event' => 'Salud',
    ];

    public function addRelation(): void
    {
        $this->relations = [...$this->relationRows(), ['type' => '', 'id' => '']];
    }

    public function removeRelation(int $index): void
    {
        $rows = $this->relationRows();
        unset($rows[$index]);
        $this->relations = array_values($rows) ?: [['type' => '', 'id' => '']];
    }

    /** Cambiar de módulo invalida el registro elegido. */
    public function updatedRelations(mixed $value, ?string $key = null): void
    {
        if ($key !== null && str_ends_with($key, '.type')) {
            $this->relations[(int) $key]['id'] = '';
        }
    }

    /** @return array<string, array<string, string>> */
    public function relationOptions(): array
    {
        return [
            'relationship' => Relationship::query()->orderBy('full_name')->get()
                ->mapWithKeys(fn (Relationship $person) => [$person->id => $person->displayName()])->all(),
            'goal' => Goal::query()->orderBy('title')->pluck('title', 'id')->all(),
            'health-event' => HealthEvent::query()->latest('event_date')->get(['id', 'title', 'event_date'])
                ->mapWithKeys(fn (HealthEvent $event) => [$event->id => $event->title.' · '.$event->event_date?->format('d/m/Y')])->all(),
        ];
    }

    protected function loadTaskRelations(Task $task): void
    {
        $this->relations = $task->associations()
            ->whereIn('target_type', array_keys(self::RELATION_MODULES))
            ->get(['target_type', 'target_id'])
            ->map(fn (TaskAssociation $association) => ['type' => $association->target_type, 'id' => (string) $association->target_id])
            ->all() ?: [['type' => '', 'id' => '']];
    }

    /** Vincula las filas completas y quita los vínculos de módulos editables que ya no están. */
    protected function syncTaskRelations(Task $task): void
    {
        $targets = collect($this->relationRows())
            ->filter(fn (array $row) => isset(self::RELATION_MODULES[$row['type']]) && $row['id'] !== '')
            ->map(fn (array $row) => Relation::getMorphedModel($row['type'])::query()->find($row['id']))
            ->filter()
            ->unique(fn ($target) => $target::class.$target->getKey());

        $kept = $targets->map(fn ($target) => TaskAssociation::link($task, $target)->getKey());

        $task->associations()
            ->whereIn('target_type', array_keys(self::RELATION_MODULES))
            ->whereNotIn('id', $kept->all())
            ->delete();
    }

    /** @return array<int, array{type: string, id: string}> */
    private function relationRows(): array
    {
        return array_values(array_map(fn ($row) => [
            'type' => (string) ($row['type'] ?? ''),
            'id' => (string) ($row['id'] ?? ''),
        ], array_filter($this->relations, 'is_array')));
    }
}
