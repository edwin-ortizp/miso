<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Periodo menstrual (CHG-126): inicio y fin. Íntimo: sensible por defecto y con notas cifradas. */
class MenstrualCycle extends Model
{
    use BelongsToUser, HasUuids;

    /** Un periodo no dura más de esto (días contando el de inicio y el de fin). */
    public const MAX_PERIOD_DAYS = 14;

    protected $fillable = ['started_on', 'ended_on', 'notes', 'is_sensitive', 'source'];

    protected $attributes = ['is_sensitive' => true];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'notes' => 'encrypted',
            'is_sensitive' => 'boolean',
        ];
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    /** Días del periodo (inicio y fin incluidos) o null si sigue abierto. */
    public function periodDays(): ?int
    {
        return $this->ended_on ? (int) $this->started_on->diffInDays($this->ended_on) + 1 : null;
    }
}
