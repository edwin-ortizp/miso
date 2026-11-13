<?php

/*
 * Voz del compañero en el chat de creación (CHG-100). Ver docs/UX-WRITING.md.
 * Frases cortas, en segunda persona, cálidas; una pregunta por mensaje;
 * como mucho un emoji y solo al cerrar algo; sin presionar ni juzgar.
 * Variables: {n}, {name}, {total}.
 */

return [
    'greeting' => [
        'morning' => '¡Buenos días! ¿Qué anotamos?',
        'afternoon' => '¡Hola! ¿Qué anotamos?',
        'night' => '¡Buenas noches! ¿Algo antes de descansar?',
    ],

    // Saludo breve cuando el módulo hace su propia pregunta.
    'salute' => [
        'morning' => '¡Buenos días!',
        'afternoon' => '¡Hola!',
        'night' => '¡Buenas noches!',
    ],

    'hint' => 'Tócame para anotar algo ✨',
    'follow_up' => '¿Algo más?',
    'error' => 'Algo falló al guardar. Tu texto sigue aquí.',

    'tasks' => [
        'greeting' => '¿Qué tienes pendiente?',
        'single_prompt' => 'Cuéntame la tarea.',
        'bulk_prompt' => 'Dime varias, una por línea.',
        'single_placeholder' => '¿Qué tienes que hacer?',
        'bulk_placeholder' => 'Una tarea por línea',
        'reply' => '¡Anotada! Ya tiene su lugar.',
        'reply_many' => '¡Listas! {n} tareas en tu lista.',
        'when' => ['' => 'Sin fecha', 'today' => 'Hoy', 'tomorrow' => 'Mañana'],
    ],

    'water' => [
        'greeting' => '¿Qué tomaste?',
        'reply' => '+250 ml de {name}. ¡Vas por {total} ml hoy!',
    ],

    'mood' => [
        'greeting' => '¿Cómo te sientes ahora?',
        'reply' => 'Gracias por contármelo.',
        'energy_question' => '¿Y cómo está tu energía?',
        'energy_reply' => 'Anotado. Cuídate mucho 💛',
        'energy_levels' => [
            1 => ['emoji' => '🪫', 'label' => 'Agotado'],
            2 => ['emoji' => '😮‍💨', 'label' => 'Baja'],
            3 => ['emoji' => '🙂', 'label' => 'Normal'],
            4 => ['emoji' => '💪', 'label' => 'Buena'],
            5 => ['emoji' => '⚡', 'label' => 'A tope'],
        ],
    ],
];
