<?php

namespace App\Mcp\Tools\Exercise;

use App\Actions\LogExercise;
use App\Mcp\Tools\Exercise\Concerns\ResolvesExerciseType;
use App\Support\ExerciseMetrics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Registra una sesión de ejercicio del usuario autenticado. Si no se indican calorías o pasos y hay duración, se calculan a partir del tipo de ejercicio.')]
class LogExerciseTool extends Tool
{
    use ResolvesExerciseType;

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'exercise_type_id' => ['nullable', 'string'],
            'exercise_type_name' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            ...ExerciseMetrics::RULES,
        ]);

        $type = $this->resolveExerciseType($data['exercise_type_id'] ?? null, $data['exercise_type_name'] ?? null);
        if ($type instanceof Response) {
            return $type;
        }

        $log = LogExercise::handle($type, $data['date'] ?? today()->toDateString(), $data);

        $message = "Ejercicio registrado: \"{$type->name}\" el {$log->date->toDateString()}.";
        if ($log->calories !== null) {
            $label = $log->calories_source === 'manual' ? 'kcal' : 'kcal estimadas';
            $message .= " {$log->calories} {$label}.";
        }
        if ($log->steps !== null) {
            $message .= " {$log->steps} pasos.";
        }

        return Response::text($message);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'exercise_type_id' => $schema->string()
                ->description('Identificador (UUID) del tipo de ejercicio en el catálogo del usuario. Alternativa a "exercise_type_name".'),
            'exercise_type_name' => $schema->string()
                ->description('Nombre del tipo de ejercicio (p. ej. "Correr"). Alternativa a "exercise_type_id".'),
            'date' => $schema->string()
                ->description('Fecha en formato YYYY-MM-DD. Por defecto hoy.'),
            'total_reps' => $schema->integer()
                ->description('Repeticiones totales para un registro sin desglose por series.'),
            'duration' => $schema->integer()
                ->description('Duración en minutos.'),
            'sets' => $schema->integer()
                ->description('Número de series.'),
            'reps' => $schema->integer()
                ->description('Número de repeticiones.'),
            'distance' => $schema->number()
                ->description('Distancia recorrida.'),
            'weight' => $schema->number()
                ->description('Peso usado.'),
            'calories' => $schema->integer()
                ->description('Calorías quemadas. Se calculan automáticamente a partir de la duración si se omite.'),
            'steps' => $schema->integer()
                ->description('Pasos equivalentes. Se calculan automáticamente a partir de la duración si se omite.'),
            'notes' => $schema->string()
                ->description('Notas adicionales.'),
        ];
    }
}
