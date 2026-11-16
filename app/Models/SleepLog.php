<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Registro de sueño (CHG-125): una noche o una siesta, con inicio y fin. */
class SleepLog extends Model
{
    use BelongsToUser, HasUuids;

    public const QUALITIES = [1 => 'Muy mala', 2 => 'Mala', 3 => 'Regular', 4 => 'Buena', 5 => 'Muy buena'];

    protected $fillable = ['slept_at', 'woke_at', 'quality', 'notes', 'is_sensitive', 'source', 'source_key'];

    protected function casts(): array
    {
        return [
            'slept_at' => 'datetime',
            'woke_at' => 'datetime',
            'quality' => 'integer',
            'is_sensitive' => 'boolean',
        ];
    }

    public function durationMinutes(): int
    {
        return (int) $this->slept_at->diffInMinutes($this->woke_at, true);
    }

    public function durationText(): string
    {
        return self::formatMinutes($this->durationMinutes());
    }

    /** «7 h 30 min», «8 h» o «45 min». */
    public static function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$rest} min",
            $rest === 0 => "{$hours} h",
            default => "{$hours} h {$rest} min",
        };
    }
}
