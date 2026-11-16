<?php

namespace App\Mcp\Tools\Health;

use App\Actions\SaveMedication;
use App\Mcp\Tools\Health\Concerns\ResolvesMedication;
use App\Models\Medication;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Crea, edita, finaliza o reanuda un medicamento programado (lo que el usuario debe tomar y con qué frecuencia). action: create (por defecto), update, finish o resume; para update/finish/resume envía medication (nombre o id). frequency_type: daily (times = horarios HH:MM cada día), every_hours (interval_hours desde el primer horario de times) o weekdays (weekdays 0=domingo…6=sábado y times). Finalizar conserva el historial. No recomienda dosis ni detecta interacciones: registra lo que el usuario indica. Para registrar que ya tomó algo usa log-medication-intake-tool. is_sensitive lo oculta de consultas amplias.')]
class LogMedicationTool extends Tool
{
    use ResolvesMedication;

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'action' => ['nullable', 'in:create,update,finish,resume'],
            'medication' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'dose_amount' => ['nullable', 'numeric'],
            'dose_unit' => ['nullable', 'string'],
            'form' => ['nullable', 'string'],
            'frequency_type' => ['nullable', 'string'],
            'times' => ['nullable', 'array'],
            'times.*' => ['string'],
            'interval_hours' => ['nullable', 'integer'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_sensitive' => ['nullable', 'boolean'],
        ]);

        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $data = SaveMedication::validate([
                ...$input,
                'starts_on' => $input['starts_on'] ?? now()->toDateString(),
            ]);
            $medication = SaveMedication::handle($data);

            return Response::text($this->summary('Medicamento registrado', $medication));
        }

        if (blank($input['medication'] ?? null)) {
            return Response::error('Indica el medicamento (nombre o id) en medication.');
        }

        $medication = $this->findMedication($input['medication']);
        if (is_string($medication)) {
            return Response::error($medication);
        }

        if ($action === 'finish') {
            return Response::text($this->summary('Medicamento finalizado (su historial se conserva)', SaveMedication::finish($medication)));
        }

        if ($action === 'resume') {
            return Response::text($this->summary('Medicamento reanudado', SaveMedication::resume($medication)));
        }

        // update: lo enviado reemplaza lo actual; lo omitido se conserva.
        $current = [
            'name' => $medication->name,
            'dose_amount' => $medication->dose_amount,
            'dose_unit' => $medication->dose_unit,
            'form' => $medication->form,
            'frequency_type' => $medication->frequency_type,
            'times' => $medication->times,
            'interval_hours' => $medication->interval_hours,
            'weekdays' => $medication->weekdays,
            'starts_on' => $medication->starts_on->toDateString(),
            'ends_on' => $medication->ends_on?->toDateString(),
            'instructions' => $medication->instructions,
            'notes' => $medication->notes,
            'is_sensitive' => $medication->is_sensitive,
        ];
        $changes = array_filter($input, fn ($value, $key) => ! in_array($key, ['action', 'medication'], true) && $value !== null, ARRAY_FILTER_USE_BOTH);

        $data = SaveMedication::validate([...$current, ...$changes]);

        return Response::text($this->summary('Medicamento actualizado', SaveMedication::handle($data, $medication)));
    }

    private function summary(string $prefix, Medication $medication): string
    {
        $dose = $medication->doseText();

        return "{$prefix}: {$medication->name}".($dose ? " {$dose}" : '').", {$medication->frequencyText()}"
            .' (desde '.$medication->starts_on->toDateString().($medication->ends_on ? ' hasta '.$medication->ends_on->toDateString() : '').'). id: '.$medication->id.'.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('create (por defecto), update, finish o resume.'),
            'medication' => $schema->string()->description('Nombre o id del medicamento (obligatorio en update, finish y resume).'),
            'name' => $schema->string()->description('Nombre del medicamento (obligatorio al crear).'),
            'dose_amount' => $schema->number()->description('Cantidad por toma (p. ej. 50).'),
            'dose_unit' => $schema->string()->description('Unidad (mg, ml, tabletas…).'),
            'form' => $schema->string()->description('Presentación (tableta, jarabe, inyección…).'),
            'frequency_type' => $schema->string()->description('daily, every_hours o weekdays (obligatorio al crear).'),
            'times' => $schema->array()->items($schema->string())->description('Horarios HH:MM. En every_hours, el primero marca el inicio del día.'),
            'interval_hours' => $schema->integer()->description('Cada cuántas horas (solo every_hours).'),
            'weekdays' => $schema->array()->items($schema->integer())->description('Días de la semana, 0=domingo … 6=sábado (solo weekdays).'),
            'starts_on' => $schema->string()->description('Desde cuándo (YYYY-MM-DD). Por defecto hoy.'),
            'ends_on' => $schema->string()->description('Hasta cuándo (YYYY-MM-DD), si el tratamiento termina.'),
            'instructions' => $schema->string()->description('Indicaciones (p. ej. «con comida»).'),
            'notes' => $schema->string()->description('Notas adicionales.'),
            'is_sensitive' => $schema->boolean()->description('Oculta el medicamento de consultas amplias.'),
        ];
    }
}
