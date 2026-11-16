<?php

namespace App\Mcp\Tools\Health;

use App\Mcp\Support\DateWindow;
use App\Mcp\Support\McpOutput;
use App\Models\HealthMeasurement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Mediciones de salud con unidad registradas por el usuario (presión arterial, glucosa…) en un periodo (por defecto los últimos 30 días), con resumen, promedio y cuántas salen del rango de referencia general. Úsala para "¿cómo ha estado mi presión?" o para cruzar mediciones con síntomas, energía o ejercicio. No diagnostica. Las mediciones marcadas como sensibles se omiten salvo include_sensitive=true; hidden_sensitive dice cuántas se ocultaron.')]
class ListHealthMeasurementsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            ...DateWindow::rules(),
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'type' => ['nullable', 'string'],
            'include_sensitive' => ['nullable', 'boolean'],
            'summary_only' => ['nullable', 'boolean'],
        ]);

        $window = DateWindow::fromInput($data, $data['days'] ?? 30);

        $query = Auth::user()->healthMeasurements()
            ->whereDate('measured_at', '>=', $window->fromDate())
            ->whereDate('measured_at', '<=', $window->toDate())
            ->when(filled($data['type'] ?? null), fn ($q) => $q->where('type', $data['type']));

        $includeSensitive = (bool) ($data['include_sensitive'] ?? false);
        $hidden = $includeSensitive ? 0 : (clone $query)->where('is_sensitive', true)->count();
        if (! $includeSensitive) {
            $query->where('is_sensitive', false);
        }

        $items = $query->orderByDesc('measured_at')->get();

        return Response::structured(McpOutput::compact([
            'period' => $window->toArray(),
            'hidden_sensitive' => $hidden ?: null,
            'summary' => [
                'measurements' => $items->count(),
                'out_of_range' => $items->filter(fn (HealthMeasurement $m) => $m->isOutOfRange())->count(),
                'by_type' => $items->groupBy('type')->map(fn ($group, $type) => [
                    'type' => $type,
                    'label' => HealthMeasurement::TYPES[$type]['label'] ?? $type,
                    'unit' => $group->first()->unit,
                    'count' => $group->count(),
                    'average_value' => round($group->avg('value'), 1),
                    'average_value2' => $group->whereNotNull('value2')->isEmpty() ? null : round($group->avg('value2'), 1),
                    'reference_range' => $group->first()->referenceText(),
                ])->values()->all(),
            ],
            'measurements' => ! empty($data['summary_only']) ? null : $items->take(60)->map(fn (HealthMeasurement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'measured_at' => $m->measured_at->format('Y-m-d H:i'),
                'value' => $m->value,
                'value2' => $m->value2,
                'pulse' => $m->pulse,
                'context' => $m->context,
                'unit' => $m->unit,
                'out_of_range' => $m->isOutOfRange(),
                'notes' => $m->notes,
            ])->values()->all(),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Desde esta fecha (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Hasta esta fecha (YYYY-MM-DD). Por defecto hoy.'),
            'days' => $schema->integer()->description('Días hacia atrás si no envías since (por defecto 30).'),
            'type' => $schema->string()->description('Filtra por tipo (blood_pressure, glucose).'),
            'include_sensitive' => $schema->boolean()->description('Incluye las mediciones marcadas como sensibles. Úsalo solo si el tema actual lo necesita.'),
            'summary_only' => $schema->boolean()->description('Devuelve solo el resumen, sin la lista.'),
        ];
    }
}
