<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Registro de un día del ciclo (CHG-130): flujo y síntomas. Íntimo: síntomas y notas van cifrados. */
class CycleDayLog extends Model
{
    use BelongsToUser, HasUuids;

    public const FLOWS = ['none' => 'Sin flujo', 'spotting' => 'Manchado', 'light' => 'Ligero', 'medium' => 'Medio', 'heavy' => 'Abundante'];

    public const SYMPTOMS = [
        'cramps' => 'Cólicos', 'headache' => 'Dolor de cabeza', 'bloating' => 'Hinchazón', 'mood_swings' => 'Cambios de ánimo',
        'fatigue' => 'Cansancio', 'breast_tenderness' => 'Sensibilidad en los senos', 'acne' => 'Acné', 'nausea' => 'Náuseas',
        'back_pain' => 'Dolor de espalda', 'other' => 'Otro',
    ];

    protected $fillable = ['date', 'flow', 'symptoms', 'notes', 'is_sensitive', 'source'];

    protected $attributes = ['is_sensitive' => true];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'symptoms' => 'encrypted:array',
            'notes' => 'encrypted',
            'is_sensitive' => 'boolean',
        ];
    }

    /** @return list<string> Etiquetas en español de los síntomas registrados. */
    public function symptomLabels(): array
    {
        return array_values(array_filter(array_map(fn ($key) => self::SYMPTOMS[$key] ?? null, $this->symptoms ?? [])));
    }
}
