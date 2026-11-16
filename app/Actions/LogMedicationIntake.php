<?php

namespace App\Actions;

use App\Models\Medication;
use App\Models\MedicationIntake;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Registra una toma de medicamento: tomada u omitida, programada o puntual (CHG-129).
 * Compartida por la pantalla, las herramientas MCP y, más adelante, la acción «Tomado» del aviso.
 *
 * Es idempotente: una toma programada (medicamento + hora esperada) se registra una sola vez y
 * una operation_key repetida devuelve la toma ya creada.
 */
class LogMedicationIntake
{
    /**
     * Valida los datos de una toma puntual o de la nota/motivo de una programada.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'dose_amount' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'dose_unit' => ['nullable', 'string', 'max:20'],
            'skip_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
            'operation_key' => ['nullable', 'string', 'max:64'],
        ], [
            'name.required' => 'Escribe el nombre del medicamento.',
            'dose_amount.gt' => 'La dosis debe ser mayor que cero.',
        ])->validate();
    }

    /** Marca como tomada (o actualiza) la toma esperada de un medicamento. */
    public static function take(Medication $medication, CarbonInterface $scheduledFor, ?CarbonInterface $takenAt = null, array $extra = []): MedicationIntake
    {
        return self::record($medication, 'taken', $scheduledFor, $takenAt ?? now(), $extra);
    }

    /** Marca como omitida la toma esperada; el motivo es opcional. */
    public static function skip(Medication $medication, CarbonInterface $scheduledFor, ?string $reason = null, array $extra = []): MedicationIntake
    {
        return self::record($medication, 'skipped', $scheduledFor, null, [...$extra, 'skip_reason' => $reason]);
    }

    /**
     * Toma puntual: sin programación, con o sin medicamento registrado.
     *
     * @param  array<string, mixed>  $data  Datos validados con validate() (name, dose_amount, dose_unit, notes…).
     */
    public static function adHoc(array $data, ?CarbonInterface $takenAt = null, ?string $source = null): MedicationIntake
    {
        if (filled($data['operation_key'] ?? null) && ($existing = self::byOperationKey($data['operation_key']))) {
            return $existing;
        }

        return MedicationIntake::create([
            'medication_id' => null,
            'name' => trim($data['name']),
            'dose_amount' => filled($data['dose_amount'] ?? null) ? (float) $data['dose_amount'] : null,
            'dose_unit' => filled($data['dose_unit'] ?? null) ? $data['dose_unit'] : null,
            'scheduled_for' => null,
            'taken_at' => $takenAt ?? now(),
            'status' => 'taken',
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
            'source' => $source,
            'operation_key' => $data['operation_key'] ?? null,
        ]);
    }

    /** Deshace una toma registrada: vuelve a quedar pendiente (si era programada) o desaparece. */
    public static function undo(MedicationIntake $intake): void
    {
        $intake->delete();
    }

    /** @param  array<string, mixed>  $extra  skip_reason, notes, source, operation_key */
    private static function record(Medication $medication, string $status, CarbonInterface $scheduledFor, ?CarbonInterface $takenAt, array $extra): MedicationIntake
    {
        if (filled($extra['operation_key'] ?? null) && ($existing = self::byOperationKey($extra['operation_key']))) {
            return $existing;
        }

        $scheduled = Carbon::instance($scheduledFor)->setTimezone(config('app.timezone'))->startOfMinute();

        $attributes = [
            'name' => $medication->name,
            'dose_amount' => $medication->dose_amount,
            'dose_unit' => $medication->dose_unit,
            'taken_at' => $takenAt,
            'status' => $status,
            'skip_reason' => $status === 'skipped' && filled($extra['skip_reason'] ?? null) ? $extra['skip_reason'] : null,
            'notes' => filled($extra['notes'] ?? null) ? $extra['notes'] : null,
            'is_sensitive' => (bool) $medication->is_sensitive,
            'source' => $extra['source'] ?? null,
            'operation_key' => $extra['operation_key'] ?? null,
        ];

        // Una sola fila por medicamento y hora esperada: si el usuario cambia de opinión, se actualiza.
        $intake = MedicationIntake::where('medication_id', $medication->id)->where('scheduled_for', $scheduled)->first();
        if ($intake) {
            $intake->update($attributes);

            return $intake;
        }

        return MedicationIntake::create([...$attributes, 'medication_id' => $medication->id, 'scheduled_for' => $scheduled]);
    }

    private static function byOperationKey(string $key): ?MedicationIntake
    {
        return MedicationIntake::where('operation_key', $key)->first();
    }
}
