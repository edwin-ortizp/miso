<?php

namespace App\Actions;

use App\Models\Medication;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Crea, edita y finaliza medicamentos programados (CHG-133). Compartida por la pantalla
 * de Medicamentos y las herramientas MCP para que ambas validen y guarden igual.
 */
class SaveMedication
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> Datos validados.
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $frequency = $data['frequency_type'] ?? null;

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'dose_amount' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'dose_unit' => ['nullable', 'string', 'max:20'],
            'form' => ['nullable', 'string', 'max:30'],
            'frequency_type' => ['required', 'in:'.implode(',', array_keys(Medication::FREQUENCIES))],
            'times' => ['required', 'array', 'min:1', 'max:12'],
            'times.*' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'interval_hours' => [$frequency === 'every_hours' ? 'required' : 'nullable', 'integer', 'between:1,24'],
            'weekdays' => [$frequency === 'weekdays' ? 'required' : 'nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'instructions' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Escribe el nombre del medicamento.',
            'dose_amount.gt' => 'La dosis debe ser mayor que cero.',
            'frequency_type.required' => 'Elige cada cuánto lo tomas.',
            'frequency_type.in' => 'Elige una frecuencia válida.',
            'times.required' => 'Indica al menos un horario.',
            'times.min' => 'Indica al menos un horario.',
            'times.*.regex' => 'Escribe los horarios como HH:MM, por ejemplo 08:00.',
            'interval_hours.required' => 'Indica cada cuántas horas lo tomas.',
            'interval_hours.between' => 'Las horas entre tomas deben estar entre 1 y 24.',
            'weekdays.required' => 'Elige al menos un día de la semana.',
            'starts_on.required' => 'Indica desde cuándo lo tomas.',
            'ends_on.after_or_equal' => 'La fecha de fin no puede ser anterior al inicio.',
        ]);

        return $validator->validate();
    }

    /** @param  array<string, mixed>  $data Datos ya validados con validate(). */
    public static function handle(array $data, ?Medication $medication = null): Medication
    {
        $times = collect($data['times'])->unique()->sort()->values()->all();
        $attributes = [
            'name' => trim($data['name']),
            'dose_amount' => filled($data['dose_amount'] ?? null) ? (float) $data['dose_amount'] : null,
            'dose_unit' => filled($data['dose_unit'] ?? null) ? $data['dose_unit'] : null,
            'form' => filled($data['form'] ?? null) ? $data['form'] : null,
            'frequency_type' => $data['frequency_type'],
            'times' => $times,
            'interval_hours' => $data['frequency_type'] === 'every_hours' ? (int) $data['interval_hours'] : null,
            'weekdays' => $data['frequency_type'] === 'weekdays'
                ? collect($data['weekdays'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all() : null,
            'starts_on' => $data['starts_on'],
            'ends_on' => filled($data['ends_on'] ?? null) ? $data['ends_on'] : null,
            'instructions' => filled($data['instructions'] ?? null) ? $data['instructions'] : null,
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
        ];

        if ($medication) {
            $medication->update($attributes);

            return $medication;
        }

        return Medication::create([...$attributes, 'is_active' => true]);
    }

    /** Finaliza el tratamiento sin perder el historial: ya no genera tomas esperadas. */
    public static function finish(Medication $medication): Medication
    {
        $today = now()->toDateString();
        $medication->update([
            'is_active' => false,
            'ends_on' => $medication->ends_on && $medication->ends_on->toDateString() < $today ? $medication->ends_on : $today,
        ]);

        return $medication;
    }

    /** Vuelve a activar un medicamento finalizado. */
    public static function resume(Medication $medication): Medication
    {
        $medication->update(['is_active' => true, 'ends_on' => null]);

        return $medication;
    }
}
