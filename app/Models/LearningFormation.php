<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningFormation extends Model
{
    use BelongsToUser, HasUuids;

    public const TYPES = ['degree' => 'Pregrado', 'specialization' => 'Especialización', 'masters' => 'Maestría', 'doctorate' => 'Doctorado', 'course' => 'Curso', 'personal' => 'Ruta personal'];

    public const STATES = ['pending' => 'Pendiente', 'in_progress' => 'En curso', 'completed' => 'Completado'];

    protected $fillable = ['type', 'title', 'description', 'category', 'status', 'institution', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function units(): HasMany
    {
        return $this->hasMany(LearningUnit::class, 'formation_id');
    }

    public function getKindAttribute(): string
    {
        return in_array($this->type, ['course', 'personal'], true) ? $this->type : 'academic';
    }
}
