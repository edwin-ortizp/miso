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
\App\Models\MoodState::create(['text' => 'Calma de prueba', 'emoji' => '🙂', 'value' => 3, 'is_active' => true, 'is_pinned' => true, 'sort_order' => 0]);

echo json_encode($fixture, JSON_THROW_ON_ERROR);
