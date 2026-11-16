<?php

namespace App\Mcp\Tools\Common;

use App\Models\DrinkLog;
use App\Models\EnergyEntry;
use App\Models\ExerciseLog;
use App\Models\Goal;
use App\Models\GoalEntry;
use App\Models\GoalNumericEntry;
use App\Models\HealthEvent;
use App\Models\HealthLog;
use App\Models\JournalEntry;
use App\Models\MealPlanEntry;
use App\Models\MoodEntry;
use App\Models\NegativeHabitLog;
use App\Models\Plan;
use App\Models\PlanVisit;
use App\Models\PomodoroSession;
use App\Models\Recipe;
use App\Models\RelationshipEvent;
use App\Models\Task;
use App\Models\VehicleEnergyLog;
use App\Models\VehicleExpense;
use App\Models\VehicleMaintenanceLog;
use App\Support\GoalProgress;
use App\Support\VehicleUsageTimeline;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Borra un registro de cualquier módulo para corregir errores (un ejercicio o una bebida mal registrados, una visita duplicada, una tarea o receta que ya no sirve…). Primero llámala sin confirm: devuelve qué se borraría sin borrar nada. Después, solo si el usuario lo confirma, repítela con confirm=true. Los ids salen de las herramientas list-*/get-*. No la uses por iniciativa propia: solo cuando el usuario pida borrar.')]
class DeleteEntryTool extends Tool
{
    /** Tipo => [modelo, descripción en español]. */
    public const TYPES = [
        'task' => [Task::class, 'tarea'],
        'goal' => [Goal::class, 'meta (con su bitácora e historial)'],
        'goal_entry' => [GoalEntry::class, 'nota o hito de una meta'],
        'goal_value' => [GoalNumericEntry::class, 'valor del indicador de una meta'],
        'health_event' => [HealthEvent::class, 'evento de salud (con su evolución)'],
        'health_follow_up' => [HealthLog::class, 'registro de evolución de salud'],
        'health_measurement' => [\App\Models\HealthMeasurement::class, 'medición de salud (presión arterial, etc.)'],
        'sleep' => [\App\Models\SleepLog::class, 'registro de sueño'],
        'medication' => [\App\Models\Medication::class, 'medicamento programado (su historial de tomas se conserva)'],
        'medication_intake' => [\App\Models\MedicationIntake::class, 'toma de medicamento'],
        'relationship_event' => [RelationshipEvent::class, 'evento de una persona'],
        'plan' => [Plan::class, 'plan o lugar (con sus visitas)'],
        'plan_visit' => [PlanVisit::class, 'visita a un plan'],
        'exercise' => [ExerciseLog::class, 'sesión de ejercicio'],
        'water' => [DrinkLog::class, 'bebida registrada'],
        'mood' => [MoodEntry::class, 'registro de ánimo'],
        'energy' => [EnergyEntry::class, 'registro de energía'],
        'negative_habit' => [NegativeHabitLog::class, 'registro de hábito negativo'],
        'focus' => [PomodoroSession::class, 'sesión de foco'],
        'meal' => [MealPlanEntry::class, 'comida del plan'],
        'recipe' => [Recipe::class, 'receta'],
        'journal' => [JournalEntry::class, 'entrada del diario'],
        'vehicle_fillup' => [VehicleEnergyLog::class, 'repostaje'],
        'vehicle_expense' => [VehicleExpense::class, 'gasto de vehículo'],
        'vehicle_maintenance' => [VehicleMaintenanceLog::class, 'mantenimiento realizado'],
    ];

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'id' => ['required'],
            'confirm' => ['nullable', 'boolean'],
        ]);

        [$class, $label] = self::TYPES[$data['type']];

        // El scope BelongsToUser limita la búsqueda a los datos del usuario autenticado.
        $record = $class::query()->find($data['id']);

        if (! $record) {
            return Response::error("No se encontró ese {$label} (id: {$data['id']}) o no te pertenece.");
        }

        $summary = $this->describe($data['type'], $record);

        if ($blocker = $this->blocker($data['type'], $record)) {
            return Response::error($blocker);
        }

        if (empty($data['confirm'])) {
            return Response::text("Se borraría {$label}: {$summary}. No se ha borrado nada; para hacerlo, confirma con el usuario y repite con confirm=true.");
        }

        DB::transaction(fn () => $this->delete($data['type'], $record));

        return Response::text("Borrado {$label}: {$summary}.");
    }

    private function describe(string $type, Model $record): string
    {
        $date = fn ($value) => $value instanceof Carbon ? $value->toDateString() : (string) $value;

        return match ($type) {
            'task' => "\"{$record->title}\"".($record->completed ? ' (completada)' : ''),
            'goal' => "\"{$record->title}\"",
            'goal_entry' => "\"{$record->goal?->title}\" del {$date($record->date)}: ".mb_strimwidth($record->text, 0, 80, '…'),
            'goal_value' => "\"{$record->goal?->title}\" = ".(float) $record->value." el {$date($record->date)}",
            'health_event' => "\"{$record->title}\" del {$date($record->event_date)}",
            'health_follow_up' => "\"{$record->healthEvent?->title}\" intensidad {$record->intensity}/10 el {$date($record->date)}",
            'sleep' => "{$record->durationText()} del {$date($record->slept_at)} al {$date($record->woke_at)}",
            'medication' => "\"{$record->name}\" ({$record->frequencyText()})",
            'medication_intake' => "\"{$record->name}\" ".($record->status === 'skipped' ? 'omitida' : 'tomada').' el '.$record->occurredAt()->format('Y-m-d H:i'),
            'health_measurement' => "{$record->typeLabel()} {$record->displayValue()} {$record->unit} del {$date($record->measured_at)}",
            'relationship_event' => "\"{$record->title}\" de {$record->relationship?->displayName()}",
            'plan' => "\"{$record->title}\" (".$record->visits()->count().' visitas)',
            'plan_visit' => "\"{$record->plan?->title}\" el {$date($record->visited_on)}",
            'exercise' => ($record->exerciseType?->name ?? 'Ejercicio')." del {$date($record->date)}".($record->duration ? " ({$record->duration} min)" : ''),
            'water' => ($record->drinkType?->name ?? $record->drink_type)." {$record->amount} ml del {$date($record->date)}".($record->time ? " a las {$record->time}" : ''),
            'mood' => trim("{$record->emoji} {$record->text}")." del {$date($record->date)}".($record->time ? " a las {$record->time}" : ''),
            'energy' => "nivel {$record->level}/5 del {$date($record->date)}".($record->time ? " a las {$record->time}" : ''),
            'negative_habit' => ($record->negativeHabitDefinition?->name ?? 'Hábito').' el '.Carbon::createFromTimestamp((int) $record->timestamp, config('app.timezone'))->format('Y-m-d H:i'),
            'focus' => intdiv((int) $record->duration, 60)." min del {$date($record->date)}".($record->description ? ": {$record->description}" : ''),
            'meal' => "{$record->meal_type} del {$date($record->date)} (".$record->items()->count().' elementos)',
            'recipe' => "\"{$record->name}\"",
            'journal' => "entrada del {$date($record->date)}",
            'vehicle_fillup' => (float) $record->quantity." {$record->unit} del {$date($record->recorded_on)} en {$record->vehicle?->name}",
            'vehicle_expense' => ($record->category?->name ?? 'Gasto').' $'.number_format((float) $record->amount, 0, ',', '.')." del {$date($record->spent_on)}",
            'vehicle_maintenance' => ($record->plan?->template?->name ?? 'Mantenimiento')." del {$date($record->performed_on)}",
        };
    }

    /** Casos que la base de datos o la app no permiten borrar sin decidir algo antes. */
    private function blocker(string $type, Model $record): ?string
    {
        if ($type === 'meal' && $record->status === 'consumed') {
            return 'Revierte el consumo con consume-meal-tool antes de borrar la comida.';
        }
        if ($type === 'recipe' && \App\Models\MealPreparation::where('user_id', auth()->id())->where('recipe_id', $record->id)->exists()) {
            return 'La receta tiene preparaciones registradas y debe conservarse para su historial.';
        }
        if ($type === 'recipe' && ($uses = $record->mealPlanItems()->count()) > 0) {
            return "La receta \"{$record->name}\" está en {$uses} comidas del plan. Quítala de esas comidas (plan-meal-tool con mode=replace, o borra la comida) antes de borrarla.";
        }

        return null;
    }

    private function delete(string $type, Model $record): void
    {
        if ($type === 'meal') {
            app(\App\Services\Meal\MealInventory::class)->rearrange((int) auth()->id(), $record->id, 'delete');

            return;
        }
        $vehicle = in_array($type, ['vehicle_fillup', 'vehicle_maintenance'], true) ? $record->vehicle : null;
        $goal = $type === 'goal_value' ? $record->goal : null;

        if ($type === 'plan') {
            // Igual que la app: las visitas se van con el plan.
            $record->visits()->get()->each->delete();
        }

        // Borrado por modelo (no masivo) para que corran los eventos: CalDAV de tareas,
        // vínculos de tareas, personas asociadas al ánimo, etc.
        $record->delete();

        if ($vehicle) {
            VehicleUsageTimeline::recalculateCurrentUsage($vehicle);
        }

        if ($goal && ($kpi = GoalProgress::configuration($goal->numeric_goal))) {
            $latest = $goal->goalNumericEntries()->orderByDesc('date')->orderByDesc('created_at')->first();
            $numeric = $goal->numeric_goal;
            $numeric['currentValue'] = $latest ? (float) $latest->value : $kpi['startValue'];
            $goal->update(['numeric_goal' => $numeric]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_keys(self::TYPES))->description('Qué se borra.')->required(),
            'id' => $schema->string()->description('Id del registro (de las herramientas list-*/get-*).')->required(),
            'confirm' => $schema->boolean()->description('true para borrar de verdad. Sin él solo se muestra qué se borraría.'),
        ];
    }
}
