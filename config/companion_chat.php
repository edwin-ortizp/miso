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
        'create_label' => 'Crear tarea',
        'bulk_label' => 'Varias',
        'view_label' => 'Ver tareas',
        'sort_label' => 'Ordenar',
        'next_label' => '¿Qué sigue?',
        'view_question' => '¿Cuáles quieres ver?',
        'view_reply' => 'Te muestro «{label}»: tienes {total}.',
        'view_empty' => 'No hay tareas aquí. ¿Creamos una?',
        'sort_question' => '¿Cómo las ordeno?',
        'sort_reply' => 'Listo, ordenadas por {label}.',
        'next_reply' => 'Tu siguiente: {title}.',
        'next_empty' => 'No hay pendientes en esta lista. ¿Vemos otro filtro?',
        'next_stale' => 'Esa tarea ya no está pendiente en esta lista. Busquemos la siguiente.',
        'recurring_confirmation' => 'Confirma la próxima fecha en el formulario para completar esta tarea recurrente.',
        'completed_reply' => '¡Completada! Un pendiente menos.',
        'invalid_filter' => 'Ese filtro no está disponible.',
        'invalid_sort' => 'Ese orden no está disponible.',
        'missing_result' => 'Primero elige una tarea con «¿Qué sigue?».',
        'suggestions_label' => 'Acciones sugeridas',
        'home_label' => 'Inicio',
        'clear_label' => 'Quitar filtro de fecha',
        'open_label' => 'Abrir',
        'complete_label' => 'Completar',
        'keywords' => [
            'single' => ['crear tarea', 'nueva tarea'],
            'bulk' => ['varias tareas', 'crear varias'],
            'view' => ['ver tareas', 'mostrar tareas'],
            'sort' => ['ordenar', 'ordenar tareas'],
            'next' => ['qué sigue', 'siguiente tarea'],
        ],
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
