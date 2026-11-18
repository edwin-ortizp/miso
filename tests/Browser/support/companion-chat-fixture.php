<?php

// The shared fixture enforces its exact, disposable SQLite database before migrating.
ob_start();
require __DIR__.'/companion-voice-fixture.php';
$fixture = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

foreach ([
    ['title' => 'Chat fixture hoy', 'start_date' => today()],
    ['title' => 'Chat fixture vencida', 'end_date' => today()->subDay(), 'priority' => 'urgent-important'],
    ['title' => 'Chat fixture futura', 'start_date' => today()->addDays(3)],
    ['title' => 'Chat fixture recurrente', 'start_date' => today(), 'is_recurrent' => true, 'recurrence' => ['customDays' => 7]],
] as $attributes) {
    $task = \App\Models\Task::create($attributes);
    $fixture['tasks'][$task->title] = $task->id;
}

\App\Models\DrinkType::create(['name' => 'Agua de prueba', 'hydration_factor' => 1, 'icon' => '💧']);
\App\Models\ExerciseType::create(['name' => 'Caminata de prueba', 'calories_per_hour' => 150, 'steps_equivalent' => 0]);
$pushups = \App\Models\ExerciseType::create(['name' => 'Flexiones de pecho', 'calories_per_hour' => 350]);
\App\Models\ExerciseType::create(['name' => 'Trotar', 'calories_per_hour' => 500]);
$exerciseHabit = \App\Models\HabitDefinition::create(['name' => 'Flexiones de la mañana', 'time_of_day' => 'anytime']);
\App\Models\HabitAction::create([
    'habit_id' => $exerciseHabit->id,
    'action_key' => 'exercise.log',
    'mode' => \App\Models\HabitAction::MODE_PROMPT,
    'config' => ['exercise_type_id' => $pushups->id],
]);
$fixture['exerciseHabit'] = $exerciseHabit->id;

\App\Models\MoodState::create(['text' => 'Calma de prueba', 'emoji' => '🙂', 'value' => 3, 'is_active' => true, 'is_pinned' => true, 'sort_order' => 0]);

// CHG-113: pantallas de detalle con datos propios (vehículo y relación).
$vehicle = \App\Models\Vehicle::create([
    'name' => 'Vehículo de prueba',
    'vehicle_type' => 'automovil',
    'power_source' => 'gasolina',
    'usage_unit' => 'km',
    'current_usage' => 1000,
    'fuel_volume_unit' => 'gal',
]);
$relationship = \App\Models\Relationship::create([
    'full_name' => 'Contacto de prueba',
    'category' => 'otro',
]);
// Acción con argumento: `openVisitForm('<uuid>')` en el detalle del plan.
$plan = \App\Models\Plan::create([
    'title' => 'Plan de prueba',
    'type' => 'restaurant',
    'status' => 'pending',
]);
$fixture['vehicles'] = ['probe' => $vehicle->id];
$fixture['relationships'] = ['probe' => $relationship->id];
$fixture['plans'] = ['probe' => $plan->id];

echo json_encode($fixture, JSON_THROW_ON_ERROR);
