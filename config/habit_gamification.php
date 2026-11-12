<?php

return [
    'streak_milestones' => [3, 7, 14, 30, 100],

    'periods' => [
        'morning' => ['label' => 'mañana', 'title' => 'Mañana en marcha', 'icon' => 'bi-sunrise-fill'],
        'afternoon' => ['label' => 'tarde', 'title' => 'Tarde encaminada', 'icon' => 'bi-sun-fill'],
        'night' => ['label' => 'noche', 'title' => 'Noche completada', 'icon' => 'bi-moon-stars-fill'],
        'anytime' => ['label' => 'rutina', 'title' => 'Rutina completada', 'icon' => 'bi-check2-circle'],
    ],

    'messages' => [
        'habit' => [
            '{habit} ya suma a la persona que quieres ser.',
            'Un gesto pequeño, una dirección clara: {habit} está hecho.',
            'Bien ahí. Cumpliste {habit} y mantuviste el movimiento.',
            'Lo importante es volver a elegirlo: {habit}, completado.',
        ],
        'period' => [
            'Cerraste tu rutina de {period}. Respira y reconoce el avance.',
            'Todo lo que elegiste para la {period} está hecho.',
            'Rutina de {period} completada. Tu día ya tiene una buena base.',
        ],
        'day' => [
            'Completaste todos tus hábitos de hoy.',
        ],
        'streak' => [
            '{streak} días eligiendo {habit}. Tu constancia ya deja huella.',
            'Llegaste a una racha de {streak} días con {habit}. Sigue a tu ritmo.',
            '{habit} suma {streak} días seguidos. Reconoce lo que estás construyendo.',
        ],
        'recovery' => [
            'Ayer quedaron {missed} hábitos abiertos. Hoy puedes volver con {habit}.',
            'Un día incompleto no borra nada. Retoma con {habit}, sin prisa.',
            'Hoy cuenta de nuevo. {habit} puede ser tu primer paso.',
        ],
        'recovery_started' => [
            'Ya retomaste el movimiento. Si quieres continuar, sigue con {habit}.',
            'Volver también es constancia. Tu siguiente paso puede ser {habit}.',
        ],
        'next' => [
            '{habit} puede ser el siguiente gesto amable de tu día.',
            'Cuando estés listo, continúa con {habit}.',
            'Tu rutina sigue abierta. El próximo paso es {habit}.',
        ],
        'start' => [
            'No necesitas hacerlo todo de una vez. Empieza con {habit}.',
            'Una acción basta para poner el día en movimiento: {habit}.',
            'Comienza pequeño y deja que el ritmo aparezca: {habit}.',
        ],
        'complete' => [
            'Completaste todos tus hábitos de hoy. Puedes descansar o elegir qué sigue.',
        ],
    ],
];
