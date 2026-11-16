<?php

namespace App\Mcp\Tools\Health;

use App\Actions\LogCycle;
use App\Models\CycleDayLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Registra algo del ciclo menstrual del usuario. action: start (empezó el periodo), end (terminó el periodo en curso) o day (flujo y síntomas de un día). date (YYYY-MM-DD) por defecto hoy. Para day envía flow (none, spotting, light, medium o heavy) y/o symptoms (cramps, headache, bloating, mood_swings, fatigue, breast_tenderness, acne, nausea, back_pain, other) y/o notes. Son datos íntimos: se guardan como sensibles (is_sensitive=true por defecto) y nunca los menciones fuera del tema. Aplica las mismas reglas que la pantalla (sin fechas futuras, un solo periodo abierto, máximo 14 días). No es un método anticonceptivo ni un diagnóstico.')]
class LogCycleTool extends Tool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'action' => ['required', 'in:start,end,day'],
            'date' => ['nullable', 'date'],
            'flow' => ['nullable', 'string'],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
        ]);
        $sensitive = (bool) ($input['is_sensitive'] ?? true);
        $date = $input['date'] ?? null;

        return match ($input['action']) {
            'start' => Response::text('Periodo registrado: empezó el '.LogCycle::start($date, $input['notes'] ?? null, $sensitive, 'mcp')->started_on->toDateString().'.'),
            'end' => $this->ended(LogCycle::end($date)),
            default => $this->day(LogCycle::day($date, $input['flow'] ?? null, $input['symptoms'] ?? [], $input['notes'] ?? null, $sensitive, 'mcp')),
        };
    }

    private function ended($cycle): Response
    {
        return Response::text("Periodo terminado: del {$cycle->started_on->toDateString()} al {$cycle->ended_on->toDateString()} ({$cycle->periodDays()} días).");
    }

    private function day(CycleDayLog $log): Response
    {
        $parts = array_filter([
            $log->flow ? 'flujo '.mb_strtolower(CycleDayLog::FLOWS[$log->flow]) : null,
            $log->symptoms ? 'síntomas: '.implode(', ', array_map('mb_strtolower', $log->symptomLabels())) : null,
        ]);

        return Response::text("Registro del {$log->date->toDateString()} guardado".($parts ? ' ('.implode('; ', $parts).')' : '').'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('start, end o day.')->required(),
            'date' => $schema->string()->description('Fecha (YYYY-MM-DD). Por defecto hoy; no puede ser futura.'),
            'flow' => $schema->string()->description('Solo day: none, spotting, light, medium o heavy.'),
            'symptoms' => $schema->array()->items($schema->string())->description('Solo day: claves de síntomas.'),
            'notes' => $schema->string()->description('Notas adicionales.'),
            'is_sensitive' => $schema->boolean()->description('Oculta el registro de consultas amplias. Por defecto true.'),
        ];
    }
}
