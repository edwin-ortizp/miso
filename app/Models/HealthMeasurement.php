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
            'article' => 'La',
            'icon' => 'bi-heart-pulse',
        ],
        // CHG-123: referencias generales y aproximadas por momento; no son un diagnóstico.
        'glucose' => [
            'label' => 'Glucosa en sangre',
            'unit' => 'mg/dL',
            'value_label' => 'Glucosa',
            'limits' => ['value' => [20, 600]],
            'reference' => ['value' => [70, 140]],
            'contexts' => [
                'fasting' => ['label' => 'En ayunas', 'reference' => ['value' => [70, 99]]],
                'before_meal' => ['label' => 'Antes de comer', 'reference' => ['value' => [70, 130]]],
                'after_meal' => ['label' => 'Después de comer', 'reference' => ['value' => [70, 140]]],
                'bedtime' => ['label' => 'Al acostarse', 'reference' => ['value' => [90, 150]]],
                'other' => ['label' => 'Otro momento', 'reference' => ['value' => [70, 140]]],
            ],
            'article' => 'La',
            'icon' => 'bi-droplet',
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

    /** Rango de referencia que aplica a esta medición: el del momento si el tipo lo define; si no, el general. */
    public function reference(): ?array
    {
        $type = self::TYPES[$this->type] ?? null;

        return ($this->context ? ($type['contexts'][$this->context]['reference'] ?? null) : null) ?? ($type['reference'] ?? null);
    }

    /** Etiqueta del momento («En ayunas») o null. */
    public function contextLabel(): ?string
    {
        return $this->context ? (self::TYPES[$this->type]['contexts'][$this->context]['label'] ?? null) : null;
    }

    /** True si algún valor sale del rango de referencia general del tipo. */
    public function isOutOfRange(): bool
    {
        $reference = $this->reference();
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
        $reference = $this->reference();
        if (! $type || $reference === null) {
            return null;
        }

        $parts = array_map(fn (array $range) => $range[0].'–'.$range[1], array_values($reference));

        return implode(' / ', $parts).' '.$type['unit'];
    }
}
