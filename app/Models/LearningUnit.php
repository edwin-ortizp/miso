<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningUnit extends Model
{
    use BelongsToUser, HasUuids;

    public const KINDS = ['period' => 'Periodo', 'subject' => 'Materia', 'module' => 'Módulo', 'topic' => 'Tema', 'stage' => 'Etapa', 'course' => 'Curso'];

    protected $fillable = ['title', 'description', 'status', 'start_date', 'end_date', 'credits'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'credits' => 'decimal:1'];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(LearningFormation::class, 'formation_id');
    }
}
