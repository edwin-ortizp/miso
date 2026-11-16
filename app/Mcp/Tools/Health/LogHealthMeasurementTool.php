<?php

namespace App\Mcp\Tools\Health;

use App\Actions\LogHealthMeasurement;
use App\Models\HealthMeasurement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Registra una medición de salud con unidad del usuario autenticado. Tipos: blood_pressure (presión arterial: value = sistólica, value2 = diastólica, pulse opcional, en mmHg y lpm) creatinine (creatinina en mg/dL) y glucose (glucosa en mg/dL; context opcional: fasting, before_meal, after_meal, bedtime u other). Úsala cuando cuente una medición concreta ("me tomé la presión: 120/80"). No diagnostica: si el valor sale del rango de referencia general, el resultado lo indica con neutralidad y conviene sugerir consultar al profesional de salud. is_sensitive oculta la medición de las consultas amplias.')]
class LogHealthMeasurementTool extends Tool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'type' => ['required', 'string'],
            'value' => ['required', 'numeric'],
            'value2' => ['nullable', 'numeric'],
            'pulse' => ['nullable', 'integer'],
            'context' => ['nullable', 'string'],
            'health_event_id' => ['nullable', 'string'],
            'measured_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
        ]);

        $data = LogHealthMeasurement::validate($input);

        $measuredAt = filled($input['measured_at'] ?? null)
            ? Carbon::parse($input['measured_at'], config('app.timezone'))
            : now();

        $measurement = LogHealthMeasurement::handle($data, $measuredAt, 'mcp');

        $text = "Medición registrada: {$measurement->typeLabel()} {$measurement->displayValue()} {$measurement->unit}"
            .($measurement->contextLabel() ? " ({$measurement->contextLabel()})" : '').' '
            .'el '.$measurement->measured_at->format('Y-m-d H:i').'.';

        if ($measurement->isOutOfRange()) {
            $text .= " Fuera del rango de referencia general ({$measurement->referenceText()}). "
                .'Es solo informativo: si te preocupa, conviene consultarlo con tu profesional de salud.';
        }

        return Response::text($text);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Tipo de medición. Disponible: '.implode(', ', array_keys(HealthMeasurement::TYPES)).'.')
                ->required(),
            'value' => $schema->number()
                ->description('Valor principal (en la presión arterial, la sistólica; en la glucosa, mg/dL).')
                ->required(),
            'value2' => $schema->number()
                ->description('Segundo valor (en la presión arterial, la diastólica; obligatorio para blood_pressure).'),
            'pulse' => $schema->integer()
                ->description('Pulso en latidos por minuto (opcional).'),
            'context' => $schema->string()
                ->description('Momento de la medición, si el tipo lo usa. Glucosa: fasting (en ayunas), before_meal, after_meal, bedtime u other.'),
            'health_event_id' => $schema->string()
                ->description('Id de un evento de salud de tipo chequeo o examen al que pertenece la medición (opcional; búscalo con list-health-events-tool).'),
            'measured_at' => $schema->string()
                ->description('Fecha y hora de la medición (YYYY-MM-DD HH:MM). Por defecto ahora.'),
            'notes' => $schema->string()
                ->description('Notas adicionales.'),
            'is_sensitive' => $schema->boolean()
                ->description('Marca la medición como sensible para ocultarla de consultas amplias.'),
        ];
    }
}
