<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Medición de salud con unidad (CHG-122). MiSo registra y muestra tendencias; no diagnostica.
 * Agregar una métrica nueva es agregar una entrada a TYPES, no una tabla.
 */
class HealthMeasurement extends Model
{
    use BelongsToUser, HasUuids;

    /**
     * Catálogo de tipos. `limits` son los topes fisiológicos aceptados al guardar y
     * `reference` el rango de referencia general que marca «fuera de rango» (informativo).
     *
     * @var array<string, array<string, mixed>>
     */
    public const TYPES = [
        'blood_pressure' => [
            'label' => 'Presión arterial',
            'unit' => 'mmHg',
            'value_label' => 'Sistólica',
            'value2_label' => 'Diastólica',
            'limits' => ['value' => [60, 260], 'value2' => [30, 160], 'pulse' => [30, 220]],
            'reference' => ['value' => [90, 120], 'value2' => [60, 80]],
            'icon' => 'bi-heart-pulse',
        ],
    ];

    protected $fillable = [
        'type', 'value', 'value2', 'pulse', 'unit', 'measured_at', 'context', 'notes',
        'is_sensitive', 'source', 'source_key',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'value2' => 'float',
            'pulse' => 'integer',
            'measured_at' => 'datetime',
            'is_sensitive' => 'boolean',
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    /** «128/84» o «5,4»: valores sin ceros decimales sobrantes. */
    public function displayValue(): string
    {
        $format = fn (float $number) => rtrim(rtrim(number_format($number, 2, ',', ''), '0'), ',');

        return $this->value2 !== null
            ? $format($this->value).'/'.$format($this->value2)
            : $format($this->value);
    }

    /** True si algún valor sale del rango de referencia general del tipo. */
    public function isOutOfRange(): bool
    {
        $reference = self::TYPES[$this->type]['reference'] ?? null;
        if ($reference === null) {
            return false;
        }

        foreach (['value', 'value2'] as $field) {
            if (! isset($reference[$field]) || $this->{$field} === null) {
                continue;
            }
            [$min, $max] = $reference[$field];
            if ($this->{$field} < $min || $this->{$field} > $max) {
                return true;
            }
        }

        return false;
    }

    /** «90–119 / 60–79 mmHg» para el aviso de fuera de rango. */
    public function referenceText(): ?string
    {
        $type = self::TYPES[$this->type] ?? null;
        if (! $type || ! isset($type['reference'])) {
            return null;
        }

        $parts = array_map(fn (array $range) => $range[0].'–'.$range[1], array_values($type['reference']));

        return implode(' / ', $parts).' '.$type['unit'];
    }
}
