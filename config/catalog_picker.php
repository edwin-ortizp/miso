<?php

use App\Models\DrinkType;
use App\Models\ExerciseType;
use App\Models\Recipe;

/**
 * Catálogos que admiten favoritos personales y «más usados» (CHG-150).
 * Para sumar otro catálogo basta registrar aquí su modelo y la tabla de registros que lo usa.
 */
return [
    // Ventana, en días, con la que se calculan los más usados.
    'usage_days' => 60,
    // Registros mínimos para sugerir un elemento como «más usado».
    'usage_minimum' => 2,

    'domains' => [
        'drinks' => ['model' => DrinkType::class, 'logs' => 'drink_logs', 'foreign_key' => 'drink_type_id', 'noun' => 'bebidas'],
        'recipes' => ['model' => Recipe::class, 'noun' => 'recetas'],
        'exercise' => ['model' => ExerciseType::class, 'logs' => 'exercise_logs', 'foreign_key' => 'exercise_type_id', 'noun' => 'ejercicios'],
    ],
];
