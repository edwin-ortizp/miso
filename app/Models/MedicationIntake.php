<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Toma real de un medicamento (CHG-133): tomada u omitida, programada o puntual. */
class MedicationIntake extends Model
{
    use BelongsToUser, HasUuids;

    public const STATUSES = ['taken' => 'Tomada', 'skipped' => 'Omitida'];

    protected $fillable = [
        'medication_id', 'name', 'dose_amount', 'dose_unit', 'scheduled_for', 'taken_at', 'status',
        'skip_reason', 'notes', 'is_sensitive', 'source', 'operation_key',
    ];

    protected function casts(): array
    {
        return [
            'dose_amount' => 'float',
            'scheduled_for' => 'datetime',
            'taken_at' => 'datetime',
            'is_sensitive' => 'boolean',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function doseText(): ?string
    {
        return Medication::formatDose($this->dose_amount, $this->dose_unit);
    }

    /** Momento que ordena el historial: cuándo se tomó o, si se omitió, la hora esperada. */
    public function occurredAt()
    {
        return $this->taken_at ?? $this->scheduled_for ?? $this->created_at;
    }
}
