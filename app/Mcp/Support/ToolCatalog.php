<?php

namespace App\Mcp\Support;

use App\Mcp\Servers\LifeTrackerServer;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/** Describe lo que expone el servidor MCP (herramientas, prompts y recursos) para mostrarlo en Ajustes. */
class ToolCatalog
{
    public const MODULES = [
        'Task' => ['Tareas', 'bi-list-task'],
        'Habit' => ['Hábitos', 'bi-check2-square'],
        'NegativeHabit' => ['Hábitos a evitar', 'bi-shield-check'],
        'Health' => ['Salud', 'bi-heart-pulse'],
        'Context' => ['Contexto', 'bi-diagram-3'],
        'Relationship' => ['Relaciones', 'bi-people'],
        'Plan' => ['Planes', 'bi-map'],
        'Vehicle' => ['Vehículos', 'bi-car-front'],
        'Shopping' => ['Compras', 'bi-cart'],
        'Meal' => ['Comidas', 'bi-egg-fried'],
        'Mood' => ['Ánimo y energía', 'bi-emoji-smile'],
        'Exercise' => ['Ejercicio', 'bi-activity'],
        'Water' => ['Hidratación', 'bi-droplet'],
        'Goal' => ['Objetivos', 'bi-flag'],
        'Journal' => ['Diario', 'bi-journal-text'],
        'Focus' => ['Pomodoro', 'bi-stopwatch'],
        'Game' => ['Colección', 'bi-stars'],
        'Common' => ['Común', 'bi-tools'],
    ];

    private const READ_PREFIXES = ['list-', 'get-', 'compare-', 'suggest-'];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $tools = null;

    /**
     * @return array<int, array{name: string, title: string, description: string, class: string, module: string,
     *     moduleLabel: string, moduleIcon: string, readOnly: bool, parameters: array<int, array<string, mixed>>}>
     */
    public function tools(): array
    {
        return $this->tools ??= array_map(fn (string $class) => $this->describe(app($class)), $this->property('tools'));
    }

    public function find(string $name): ?array
    {
        return collect($this->tools())->firstWhere('name', $name);
    }

    public function promptCount(): int
    {
        return count($this->property('prompts'));
    }

    public function resourceCount(): int
    {
        return count($this->property('resources'));
    }

    public function instructions(): string
    {
        $attribute = (new ReflectionClass(LifeTrackerServer::class))->getAttributes(Instructions::class)[0] ?? null;

        return $attribute ? $attribute->newInstance()->value : '';
    }

    private function property(string $name): array
    {
        return (new ReflectionClass(LifeTrackerServer::class))->getDefaultProperties()[$name] ?? [];
    }

    private function describe(Tool $tool): array
    {
        $definition = $tool->toArray();
        $module = explode('\\', get_class($tool))[3] ?? 'Common';
        [$label, $icon] = self::MODULES[$module] ?? [$module, 'bi-tools'];
        $schema = $definition['inputSchema'];
        $required = $schema['required'] ?? [];

        $parameters = [];
        foreach ((array) ($schema['properties'] ?? []) as $param => $spec) {
            $spec = (array) $spec;
            $type = $spec['type'] ?? 'mixed';
            $parameters[] = [
                'name' => $param,
                'type' => is_array($type) ? implode(' | ', $type) : $type,
                'required' => in_array($param, $required, true),
                'description' => $spec['description'] ?? '',
                'enum' => $spec['enum'] ?? null,
            ];
        }

        return [
            'name' => $definition['name'],
            'title' => $definition['title'],
            'description' => $definition['description'],
            'class' => get_class($tool),
            'module' => $module,
            'moduleLabel' => $label,
            'moduleIcon' => $icon,
            'readOnly' => str($definition['name'])->startsWith(self::READ_PREFIXES),
            'parameters' => $parameters,
        ];
    }
}
