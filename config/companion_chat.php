<?php

/*
 * Voz del compañero en el chat de creación (CHG-100). Ver docs/UX-WRITING.md.
 * Frases cortas, en segunda persona, cálidas; una pregunta por mensaje;
 * como mucho un emoji y solo al cerrar algo; sin presionar ni juzgar.
 * Variables: {n}, {name}, {total}.
 */

return [
    'hello' => 'Hola.',
    'hello_named' => 'Hola, {name}.',
    'action_question' => '¿Vamos con «{action}»?',
    'screen_questions' => [
        'tasks.calendar' => '¿Qué tarea quieres poner en el calendario?',
        'tasks.gantt' => '¿Qué tarea quieres sumar al cronograma?',
        'habits.settings' => '¿Qué hábito quieres empezar?',
        'negative-habits.settings' => '¿Qué hábito quieres evitar?',
        'health' => '¿Qué quieres registrar sobre tu salud?',
        'health.body' => '¿Qué quieres anotar en tu registro de salud?',
        'health.measurements' => '¿Qué medición quieres anotar?',
        'health.medications' => '¿Qué quieres registrar sobre tus medicamentos?',
        'health.sleep' => '¿Qué quieres anotar sobre tu descanso?',
        'health.cycle' => '¿Qué quieres anotar de tu ciclo?',
        'meals.weekly' => '¿Qué comida quieres planear?',
        'meals.preparations' => '¿Qué quieres cocinar para tener listo?',
        'meals.suggestions' => '¿Armamos la lista de compras con tu plan?',
        'meals.recipes' => '¿Qué receta quieres guardar?',
        'meals.ingredients' => '¿Qué ingrediente quieres agregar?',
        'meals.shopping' => '¿Qué falta comprar?',
        'meals.purchases' => '¿Qué compra quieres registrar?',
        'meals.compare' => '¿Qué precio encontraste?',
        'learning' => '¿Qué quieres empezar a estudiar?',
        'pomodoro' => '¿Cuánto tiempo quieres registrar?',
        'goals' => '¿Qué objetivo tienes en mente?',
        'goals.show' => '¿Qué tarea te acerca a este objetivo?',
        'relationships' => '¿A quién quieres agregar a tu agenda?',
        'relationships.show' => '¿Qué momento quieres guardar?',
        'relationships.history' => '¿Qué momento quieres guardar?',
        'relationships.plans' => '¿Qué plan quieres hacer con esta persona?',
        'relationships.tasks' => '¿Qué tarea quieres anotar para esta persona?',
        'social.friends' => '¿A quién quieres agregar como amigo?',
        'plans' => '¿Qué plan tienes en mente?',
        'plans.show' => '¿Qué visita quieres guardar?',
        'vehicles' => '¿Qué vehículo quieres agregar?',
        'vehicles.maintenance' => '¿Qué mantenimiento quieres planear?',
        'vehicles.expenses' => '¿Qué gasto del vehículo quieres anotar?',
    ],

    'hint' => 'Tócame para anotar algo',
    'follow_up' => '¿Algo más?',
    'error' => 'No pude guardarlo. Tu texto sigue aquí.',

    'tasks' => [
        'greeting' => '¿Qué tarea tienes en mente?',
        'single_prompt' => '¿Qué tarea quieres anotar?',
        'bulk_prompt' => 'Pásame las tareas, una por línea.',
        'single_placeholder' => '¿Qué tienes que hacer?',
        'bulk_placeholder' => 'Una tarea por línea',
        'reply' => 'Listo, ya quedó en tu lista.',
        'reply_many' => '¡Listo! Sumé {n} tareas a tu lista.',
        'create_label' => 'Crear tarea',
        'bulk_label' => 'Varias',
        'view_label' => 'Ver tareas',
        'sort_label' => 'Ordenar',
        'next_label' => '¿Qué sigue?',
        'view_question' => '¿Qué tareas quieres ver?',
        'view_reply' => 'Mostrando «{label}». Hay {total} en la lista.',
        'view_empty' => 'Por aquí no hay tareas. ¿Anotamos una?',
        'sort_question' => '¿Cómo las ordeno?',
        'sort_reply' => 'Listo, ordenadas por {label}.',
        'next_reply' => 'Puedes seguir con esta: {title}.',
        'next_empty' => 'No hay pendientes en esta lista. ¿Vemos otro filtro?',
        'next_stale' => 'Esa tarea ya no aparece como pendiente aquí. Puedes buscar la siguiente.',
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
        'greeting' => '¿Qué tomaste para hidratarte?',
        'reply' => 'Sumé 250 ml de {name}. El total de ese día va en {total} ml.',
    ],

    'exercise' => [
        'greeting' => '¿Qué actividad quieres registrar?',
        'reply' => '¡Listo! Sumé 30 min de {name}.',
    ],

    'mood' => [
        'greeting' => '¿Cómo te sientes en este momento?',
        'reply' => 'Gracias por contármelo.',
        'energy_question' => '¿Y cómo anda tu energía?',
        'energy_reply' => 'Ya quedó registrada tu energía 💛',
        'energy_levels' => [
            1 => ['emoji' => '🪫', 'label' => 'Agotado'],
            2 => ['emoji' => '😮‍💨', 'label' => 'Baja'],
            3 => ['emoji' => '🙂', 'label' => 'Normal'],
            4 => ['emoji' => '💪', 'label' => 'Buena'],
            5 => ['emoji' => '⚡', 'label' => 'A tope'],
        ],
    ],
];
