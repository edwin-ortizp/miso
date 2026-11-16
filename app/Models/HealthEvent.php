<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class HealthEvent extends Model
{
    use BelongsToUser, HasUuids;

    public const TYPES = [
        'appointment' => 'Cita médica',
        'checkup' => 'Chequeo o examen',
        'procedure' => 'Procedimiento o cirugía',
        'symptom' => 'Síntoma',
        'illness' => 'Enfermedad',
        'vaccination' => 'Vacuna',
    ];

    public const SCHEDULED_TYPES = ['appointment', 'checkup', 'procedure'];

    /** Tipos con seguimiento diario de intensidad y recuperación. */
    public const EVOLUTION_TYPES = ['symptom', 'illness', 'procedure'];

    /** Tipos que se relacionan con zonas del cuerpo (y aparecen en el mapa corporal). */
    public const BODY_AREA_TYPES = ['symptom', 'illness', 'procedure'];

    /** Zonas del cuerpo. Las que no están en `OFF_MAP_BODY_AREAS` se dibujan en el mapa corporal. */
    public const BODY_AREAS = [
        'head' => 'Cabeza',
        'neck' => 'Cuello',
        'chest' => 'Pecho',
        'abdomen' => 'Abdomen',
        'upper_back' => 'Espalda alta',
        'lower_back' => 'Espalda baja',
        'glutes' => 'Glúteos',
        'genitals' => 'Zona genital',
        'shoulder_right' => 'Hombro derecho',
        'shoulder_left' => 'Hombro izquierdo',
        'arm_right' => 'Brazo derecho',
        'arm_left' => 'Brazo izquierdo',
        'forearm_right' => 'Antebrazo derecho',
        'forearm_left' => 'Antebrazo izquierdo',
        'hand_right' => 'Mano derecha',
        'hand_left' => 'Mano izquierda',
        'thigh_right' => 'Muslo derecho',
        'thigh_left' => 'Muslo izquierdo',
        'hamstring_right' => 'Isquiotibial derecho',
        'hamstring_left' => 'Isquiotibial izquierdo',
        'knee_right' => 'Rodilla derecha',
        'knee_left' => 'Rodilla izquierda',
        'leg_right' => 'Pierna derecha',
        'leg_left' => 'Pierna izquierda',
        'calf_right' => 'Pantorrilla derecha',
        'calf_left' => 'Pantorrilla izquierda',
        'ankle_right' => 'Tobillo derecho',
        'ankle_left' => 'Tobillo izquierdo',
        'foot_right' => 'Pie derecho',
        'foot_left' => 'Pie izquierdo',
        'eyes_face' => 'Ojos y cara',
        'mouth_throat' => 'Boca y garganta',
        'skin' => 'Piel',
        'whole_body' => 'Todo el cuerpo',
        'other' => 'Otra zona',
    ];

    public const BODY_AREA_GROUPS = [
        'Cabeza y tronco' => ['head', 'neck', 'chest', 'abdomen', 'upper_back', 'lower_back', 'glutes', 'genitals'],
        'Brazos' => ['shoulder_right', 'shoulder_left', 'arm_right', 'arm_left', 'forearm_right', 'forearm_left', 'hand_right', 'hand_left'],
        'Piernas' => ['thigh_right', 'thigh_left', 'hamstring_right', 'hamstring_left', 'knee_right', 'knee_left', 'leg_right', 'leg_left', 'calf_right', 'calf_left', 'ankle_right', 'ankle_left', 'foot_right', 'foot_left'],
        'Otras' => ['eyes_face', 'mouth_throat', 'skin', 'whole_body', 'other'],
    ];

    /** Zonas sin trazado en el mapa corporal: solo aparecen en la lista. */
    public const OFF_MAP_BODY_AREAS = ['skin', 'whole_body', 'other'];

    public const COMMON_ILLNESSES = [
        'common_cold' => 'Resfriado común',
        'flu' => 'Gripe',
        'covid_19' => 'COVID-19',
        'allergy' => 'Alergia',
        'migraine' => 'Migraña',
        'gastroenteritis' => 'Gastroenteritis',
        'food_poisoning' => 'Intoxicación alimentaria',
        'urinary_infection' => 'Infección urinaria',
        'other' => 'Otra enfermedad',
    ];

    protected $fillable = ['type', 'title', 'event_date', 'end_date', 'notes', 'details', 'is_sensitive'];

    protected function casts(): array
    {
        return ['event_date' => 'date', 'end_date' => 'date', 'details' => 'array', 'is_sensitive' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $event) {
            $event->taskAssociations()->delete();
            // CHG-124: las mediciones enlazadas a este examen conservan su valor y pierden el enlace.
            HealthMeasurement::withoutGlobalScopes()->where('user_id', $event->user_id)
                ->where('health_event_id', $event->id)->update(['health_event_id' => null]);
        });
    }

    public function taskAssociations(): MorphMany
    {
        return $this->morphMany(TaskAssociation::class, 'target');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HealthLog::class)->orderBy('date');
    }

    public function tasks(): MorphToMany
    {
        return $this->morphToMany(Task::class, 'target', 'task_associations', 'target_id', 'task_id')
            ->withTimestamps();
    }

    public function scheduledTask(): ?Task
    {
        return $this->tasks()->orderBy('tasks.created_at')->first();
    }

    /** Opciones agrupadas para un select con <optgroup>. */
    public static function groupedBodyAreas(): array
    {
        return array_map(
            fn (array $keys) => array_intersect_key(self::BODY_AREAS, array_flip($keys)),
            self::BODY_AREA_GROUPS,
        );
    }

    public static function bodyAreaIcon(string $area): string
    {
        return match (true) {
            $area === 'head' => 'bi-emoji-dizzy',
            $area === 'eyes_face' => 'bi-eye',
            $area === 'mouth_throat' => 'bi-chat-square',
            $area === 'chest' => 'bi-lungs',
            in_array($area, ['upper_back', 'lower_back', 'whole_body'], true) => 'bi-person-standing',
            $area === 'skin' => 'bi-droplet',
            $area === 'genitals' => 'bi-gender-ambiguous',
            str_starts_with($area, 'hand_') || str_starts_with($area, 'arm_') || str_starts_with($area, 'forearm_') => 'bi-hand-index',
            str_starts_with($area, 'knee_') || str_starts_with($area, 'leg_') => 'bi-activity',
            default => 'bi-bandaid',
        };
    }

    /**
     * Zonas del cuerpo del evento. Acepta el formato antiguo de una sola zona.
     *
     * @return list<string>
     */
    public function bodyAreas(): array
    {
        $details = $this->details ?? [];
        $areas = $details['body_areas'] ?? (isset($details['body_area']) ? [$details['body_area']] : []);

        return array_values(array_unique(array_map(
            fn (string $area) => array_key_exists($area, self::BODY_AREAS) ? $area : 'other',
            array_filter(array_map('strval', (array) $areas), 'filled'),
        )));
    }

    public static function bodyAreasLabel(array $areas, ?string $customArea = null): ?string
    {
        return $areas === [] ? null : implode(', ', array_map(fn (string $area) => self::bodyAreaLabel($area, $customArea), $areas));
    }

    public static function bodyAreaLabel(?string $area, ?string $customArea = null): ?string
    {
        return $area === 'other' ? ($customArea ?: self::BODY_AREAS['other']) : (self::BODY_AREAS[$area] ?? $area);
    }

    public static function illnessLabel(?string $condition, ?string $customCondition = null): ?string
    {
        return $condition === 'other' ? ($customCondition ?: self::COMMON_ILLNESSES['other']) : (self::COMMON_ILLNESSES[$condition] ?? $condition);
    }
}
