<?php

namespace App\Actions;

use App\Models\HealthMeasurement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Registra o actualiza una medición de salud. Compartida por la pantalla de Mediciones
 * y las herramientas MCP (CHG-122), para que ambas validen y guarden igual.
 *
 * El dueño del registro lo asigna el trait BelongsToUser a partir del usuario autenticado.
 */
class LogHealthMeasurement
{
    /**
     * Valida los campos de una medición. `value2` solo aplica a tipos que lo usan.
     *
     * @param  array<string, mixed>  $data  type, value, value2, pulse, notes, is_sensitive
     * @return array<string, mixed>  Datos validados.
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $type = $data['type'] ?? null;
        $definition = HealthMeasurement::TYPES[$type] ?? null;

        $validator = Validator::make($data, [
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(HealthMeasurement::TYPES))],
            'value' => ['required', 'numeric'],
            'value2' => ['nullable', 'numeric'],
            'pulse' => ['nullable', 'integer'],
            'context' => ['nullable', 'string', isset($definition['contexts']) ? 'in:'.implode(',', array_keys($definition['contexts'])) : 'prohibited'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
        ], [
            'type.in' => 'Elige un tipo de medición válido.',
            'value.required' => 'Indica el valor de la medición.',
            'value.numeric' => 'El valor debe ser un número.',
            'context.in' => 'Elige un momento válido.',
            'context.prohibited' => 'Esta medición no usa un momento.',
            'value2.numeric' => 'El valor debe ser un número.',
            'pulse.integer' => 'El pulso debe ser un número entero.',
        ]);

        $validator->after(function ($validator) use ($data, $definition) {
            if ($definition === null) {
                return;
            }

            $unit = $definition['unit'];
            foreach (['value' => 'value_label', 'value2' => 'value2_label'] as $field => $labelKey) {
                $number = $data[$field] ?? null;
                if ($number === null || $number === '' || ! is_numeric($number) || ! isset($definition['limits'][$field])) {
                    continue;
                }
                [$min, $max] = $definition['limits'][$field];
                if ($number < $min || $number > $max) {
                    $label = mb_strtolower($definition[$labelKey] ?? 'valor');
                    $article = $definition['article'] ?? 'El';
                    $validator->errors()->add($field, "{$article} {$label} debe estar entre {$min} y {$max} {$unit}.");
                }
            }

            if (isset($definition['value2_label']) && ($data['value2'] ?? null) === null) {
                $validator->errors()->add('value2', 'Indica la '.mb_strtolower($definition['value2_label']).'.');
            }

            if (isset($definition['value2_label'], $data['value'], $data['value2']) && is_numeric($data['value']) && is_numeric($data['value2'])
                && (float) $data['value2'] >= (float) $data['value']) {
                $validator->errors()->add('value2', 'La diastólica debe ser menor que la sistólica.');
            }

            $pulse = $data['pulse'] ?? null;
            if ($pulse !== null && $pulse !== '' && is_numeric($pulse) && isset($definition['limits']['pulse'])) {
                [$min, $max] = $definition['limits']['pulse'];
                if ($pulse < $min || $pulse > $max) {
                    $validator->errors()->add('pulse', "El pulso debe estar entre {$min} y {$max} lpm.");
                }
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $data  Datos ya validados con validate().
     */
    public static function handle(array $data, ?CarbonInterface $measuredAt = null, ?string $source = null, ?string $sourceKey = null): HealthMeasurement
    {
        return HealthMeasurement::create([
            ...self::attributes($data),
            'measured_at' => $measuredAt ?? now(),
            'source' => $source,
            'source_key' => $sourceKey,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public static function update(HealthMeasurement $measurement, array $data, ?CarbonInterface $measuredAt = null): HealthMeasurement
    {
        $measurement->update([
            ...self::attributes($data),
            ...($measuredAt ? ['measured_at' => $measuredAt] : []),
        ]);

        return $measurement;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function attributes(array $data): array
    {
        $definition = HealthMeasurement::TYPES[$data['type']];

        return [
            'type' => $data['type'],
            'value' => $data['value'],
            'value2' => isset($definition['value2_label']) ? ($data['value2'] ?? null) : null,
            'pulse' => isset($definition['limits']['pulse']) && filled($data['pulse'] ?? null) ? (int) $data['pulse'] : null,
            'context' => isset($definition['contexts']) && filled($data['context'] ?? null) ? $data['context'] : null,
            'unit' => $definition['unit'],
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
        ];
    }
}
