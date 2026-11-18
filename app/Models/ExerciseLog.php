<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExerciseLog extends Model
{
    use BelongsToUser, HasUuids;

    protected $fillable = [
        'date',
        'exercise_id',
        'exercise_type_id',
        'total_reps',
        'sets',
        'reps',
        'duration',
        'distance',
        'weight',
        'calories',
        'calories_source',
        'steps',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function totalRepetitions(): ?int
    {
        return $this->total_reps ?? ($this->sets !== null && $this->reps !== null ? $this->sets * $this->reps : null);
    }

    public function paceSecondsPerKm(): ?int
    {
        return $this->exerciseType
            ? \App\Support\ExerciseMetrics::pace(\App\Support\ExerciseMetrics::profile($this->exerciseType), $this->duration, $this->distance)
            : null;
    }

    public function exerciseType()
    {
        return $this->belongsTo(ExerciseType::class);
    }
}
