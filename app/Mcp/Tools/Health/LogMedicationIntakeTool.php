<?php

namespace App\Mcp\Tools\Health;

use App\Actions\LogMedicationIntake;
use App\Mcp\Tools\Health\Concerns\ResolvesMedication;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Support\MedicationSchedule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Carbon\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Registra una toma de medicamento del usuario: tomada (status=taken, por defecto) u omitida (status=skipped, con skip_reason opcional). Con medication (nombre o id de un medicamento programado) marca su toma esperada: scheduled_for (YYYY-MM-DD HH:MM) o, si se omite, la toma pendiente o atrasada de hoy más cercana. Sin medication, registra una toma puntual con name (y dose_amount/dose_unit). Es idempotente: repetir la misma toma no la duplica; envía operation_key estable en los reintentos. Responde con lo que aún queda pendiente hoy. Para ver pendientes usa list-medications-tool.')]
class LogMedicationIntakeTool extends Tool
{
    use ResolvesMedication;

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'medication' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'status' => ['nullable', 'in:taken,skipped'],
            'scheduled_for' => ['nullable', 'date'],
            'taken_at' => ['nullable', 'date'],
            'dose_amount' => ['nullable', 'numeric'],
            'dose_unit' => ['nullable', 'string'],
            'skip_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_sensitive' => ['nullable', 'boolean'],
            'operation_key' => ['nullable', 'string'],
        ]);

        $status = $input['status'] ?? 'taken';
        $takenAt = filled($input['taken_at'] ?? null) ? Carbon::parse($input['taken_at'], config('app.timezone')) : now();
        $extra = [
            'source' => 'mcp',
            'operation_key' => $input['operation_key'] ?? null,
            'notes' => $input['notes'] ?? null,
        ];

        if (blank($input['medication'] ?? null)) {
            if ($status === 'skipped') {
                return Response::error('Para omitir una toma indica el medicamento programado en medication.');
            }
            $data = LogMedicationIntake::validate([
                'name' => $input['name'] ?? null,
                'dose_amount' => $input['dose_amount'] ?? null,
                'dose_unit' => $input['dose_unit'] ?? null,
                'notes' => $input['notes'] ?? null,
                'is_sensitive' => $input['is_sensitive'] ?? false,
                'operation_key' => $input['operation_key'] ?? null,
            ]);
            $intake = LogMedicationIntake::adHoc($data, $takenAt, 'mcp');

            return Response::text($this->describe('Toma puntual registrada', $intake, null));
        }

        $medication = $this->findMedication($input['medication'], onlyActive: true);
        if (is_string($medication)) {
            return Response::error($medication);
        }

        $scheduled = $this->scheduledFor($medication, $input['scheduled_for'] ?? null);
        if ($scheduled === null) {
            return Response::error("«{$medication->name}» no tiene tomas pendientes hoy. Indica scheduled_for (YYYY-MM-DD HH:MM) o registra una toma puntual con name.");
        }

        $intake = $status === 'skipped'
            ? LogMedicationIntake::skip($medication, $scheduled, $input['skip_reason'] ?? null, $extra)
            : LogMedicationIntake::take($medication, $scheduled, $takenAt, $extra);

        return Response::text($this->describe($status === 'skipped' ? 'Toma omitida' : 'Toma registrada', $intake, $medication));
    }

    private function scheduledFor(Medication $medication, ?string $given): ?Carbon
    {
        if (filled($given)) {
            return Carbon::parse($given, config('app.timezone'));
        }

        $slots = MedicationSchedule::slots($medication, now());
        $registered = MedicationIntake::where('medication_id', $medication->id)
            ->whereIn('scheduled_for', $slots->all())->pluck('scheduled_for')
            ->map(fn ($d) => $d->format('Y-m-d H:i'))->all();
        $open = $slots->reject(fn (Carbon $slot) => in_array($slot->format('Y-m-d H:i'), $registered, true));

        // La más cercana a ahora entre las que faltan por registrar.
        return $open->sortBy(fn (Carbon $slot) => abs($slot->diffInMinutes(now(), false)))->first();
    }

    private function describe(string $prefix, MedicationIntake $intake, ?Medication $medication): string
    {
        $dose = $intake->doseText();
        $text = "{$prefix}: {$intake->name}".($dose ? " {$dose}" : '').' ('.($intake->status === 'skipped' ? 'omitida' : 'tomada').')'
            .($intake->scheduled_for ? ' · toma de las '.$intake->scheduled_for->format('H:i') : '').'.';

        $pending = MedicationSchedule::forDay(now())->filter(fn (array $row) => in_array($row['state'], ['pending', 'overdue'], true));
        $text .= $pending->isEmpty()
            ? ' No quedan tomas pendientes hoy.'
            : ' Pendientes hoy: '.$pending->map(fn (array $row) => $row['medication']->name.' '.$row['scheduled_for']->format('H:i'))->implode(', ').'.';

        return $text;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'medication' => $schema->string()->description('Nombre o id del medicamento programado. Omítelo para una toma puntual.'),
            'name' => $schema->string()->description('Nombre del medicamento en una toma puntual (sin medication).'),
            'status' => $schema->string()->description('taken (por defecto) o skipped.'),
            'scheduled_for' => $schema->string()->description('Hora esperada de la toma (YYYY-MM-DD HH:MM). Por defecto la pendiente de hoy más cercana.'),
            'taken_at' => $schema->string()->description('Cuándo la tomó (YYYY-MM-DD HH:MM). Por defecto ahora.'),
            'dose_amount' => $schema->number()->description('Dosis en una toma puntual.'),
            'dose_unit' => $schema->string()->description('Unidad de la dosis en una toma puntual.'),
            'skip_reason' => $schema->string()->description('Motivo, si la omitió (opcional).'),
            'notes' => $schema->string()->description('Notas adicionales.'),
            'is_sensitive' => $schema->boolean()->description('Oculta una toma puntual de consultas amplias.'),
            'operation_key' => $schema->string()->description('Clave estable para reintentos: repetirla no duplica la toma.'),
        ];
    }
}
