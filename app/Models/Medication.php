<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medicamento programado (CHG-129): qué debo tomar y con qué frecuencia. Las tomas reales
 * viven en MedicationIntake. MiSo registra; no recomienda dosis ni detecta interacciones.
 */
class Medication extends Model
{
    use BelongsToUser, HasUuids;

    public const FREQUENCIES = [
        'daily' => 'Cada día, a horas fijas',
        'every_hours' => 'Cada cierto número de horas',
        'weekdays' => 'Solo algunos días de la semana',
    ];

    /** Carbon dayOfWeek: 0 = domingo. */
    public const WEEKDAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'];

    protected $fillable = [
        'name', 'dose_amount', 'dose_unit', 'form', 'frequency_type', 'times', 'interval_hours', 'weekdays',
        'starts_on', 'ends_on', 'instructions', 'notes', 'health_event_id', 'is_active', 'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'dose_amount' => 'float',
            'times' => 'array',
            'weekdays' => 'array',
            'interval_hours' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Borrar un medicamento no pierde el historial: las tomas conservan su nombre y dosis.
        static::deleting(function (Medication $medication) {
            MedicationIntake::withoutGlobalScopes()
                ->where('user_id', $medication->user_id)
                ->where('medication_id', $medication->id)
                ->update(['medication_id' => null]);
        });
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(MedicationIntake::class);
    }

    /** «50 mg», «2 tabletas» o null si no se indicó dosis. */
    public function doseText(): ?string
    {
        return self::formatDose($this->dose_amount, $this->dose_unit);
    }

    public static function formatDose(?float $amount, ?string $unit): ?string
    {
        if ($amount === null && blank($unit)) {
            return null;
        }
        $number = $amount === null ? '' : rtrim(rtrim(number_format($amount, 2, ',', ''), '0'), ',');

        return trim($number.' '.$unit);
    }

    /** «1 vez al día · 08:00», «Cada 8 horas desde 08:00», «Lun, Mié · 20:00». */
    public function frequencyText(): string
    {
        $times = implode(' y ', $this->times ?? []);

        return match ($this->frequency_type) {
            'every_hours' => "Cada {$this->interval_hours} horas desde ".($this->times[0] ?? '08:00'),
            'weekdays' => implode(', ', array_map(fn ($d) => self::WEEKDAYS[$d] ?? $d, $this->weekdays ?? [])).($times ? " · {$times}" : ''),
            default => count($this->times ?? []) > 1 ? count($this->times).' veces al día · '.$times : '1 vez al día'.($times ? " · {$times}" : ''),
        };
    }
}
