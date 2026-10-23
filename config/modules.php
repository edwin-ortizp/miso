<?php

return [
    'navigation' => [
        'overview' => ['label' => 'Vista general', 'icon' => 'bi-grid-1x2', 'items' => [
            ['label' => 'Panel de pared', 'route' => 'panel', 'module' => 'home', 'icon' => 'bi-tablet-landscape', 'active' => ['panel']],
            ['label' => 'Mi día', 'route' => 'home', 'module' => 'home', 'icon' => 'bi-sunrise', 'active' => ['home']],
            ['label' => 'Estadísticas', 'route' => 'statistics', 'module' => 'statistics', 'icon' => 'bi-bar-chart', 'active' => ['statistics*']],
            ['label' => 'Ajustes', 'route' => 'settings', 'module' => 'settings', 'icon' => 'bi-gear', 'active' => ['settings*']],
        ]],
        'body' => ['label' => 'Salud y cuerpo', 'icon' => 'bi-heart-pulse', 'items' => [
            ['label' => 'Salud', 'route' => 'health', 'module' => 'health', 'icon' => 'bi-heart-pulse', 'active' => ['health*']],
            ['label' => 'Ejercicio', 'route' => 'exercise', 'module' => 'exercise', 'icon' => 'bi-activity', 'active' => ['exercise*']],
            ['label' => 'Hidratación', 'route' => 'water.daily', 'module' => 'water', 'icon' => 'bi-droplet', 'active' => ['water*']],
            ['label' => 'Comidas', 'route' => 'meals.weekly', 'module' => 'meals', 'archetype' => 'dashboard', 'icon' => 'bi-egg-fried', 'active' => ['meals*']],
        ]],
        'habits' => ['label' => 'Hábitos', 'icon' => 'bi-check2-square', 'items' => [
            ['label' => 'Hábitos', 'route' => 'habits', 'module' => 'habits', 'icon' => 'bi-check2-square', 'active' => ['habits*']],
            ['label' => 'Hábitos a evitar', 'route' => 'negative-habits', 'module' => 'negative-habits', 'icon' => 'bi-shield-check', 'active' => ['negative-habits*']],
        ]],
        'productivity' => ['label' => 'Productividad', 'icon' => 'bi-calendar-check', 'items' => [
            ['label' => 'Tareas', 'route' => 'tasks.list', 'module' => 'tasks', 'icon' => 'bi-list-task', 'active' => ['tasks*']],
            ['label' => 'Pomodoro', 'route' => 'pomodoro', 'module' => 'pomodoro', 'icon' => 'bi-stopwatch', 'active' => ['pomodoro*']],
            ['label' => 'Objetivos', 'route' => 'goals', 'module' => 'goals', 'icon' => 'bi-flag', 'active' => ['goals*']],
        ]],
        'life' => ['label' => 'Vida personal', 'icon' => 'bi-person-heart', 'items' => [
            ['label' => 'Diario', 'route' => 'journal', 'module' => 'journal', 'icon' => 'bi-journal-text', 'active' => ['journal*']],
            ['label' => 'Relaciones', 'route' => 'relationships', 'module' => 'relationships', 'icon' => 'bi-people', 'active' => ['relationships*']],
            ['label' => 'Planes', 'route' => 'plans', 'module' => 'plans', 'icon' => 'bi-map', 'active' => ['plans*']],
            ['label' => 'Ánimo y energía', 'route' => 'mood', 'module' => 'mood', 'icon' => 'bi-emoji-smile', 'active' => ['mood*']],
        ]],
        'vehicles' => ['label' => 'Vehículos', 'icon' => 'bi-car-front', 'items' => [
            ['label' => 'Vehículos', 'route' => 'vehicles', 'module' => 'vehicles', 'icon' => 'bi-car-front', 'active' => ['vehicles*']],
        ]],
    ],
    'home' => [
        'archetype' => 'dashboard',
        'title' => 'Mi día',
        'subtitle' => 'Una vista coordinada de lo que importa hoy.',
        'icon' => 'bi-sunrise',
        'patterns' => ['home', 'panel'],
        'preserve' => ['date'],
    ],
    'water' => [
        'archetype' => 'daily-log',
        'title' => 'Hidratación',
        'subtitle' => 'Registra tu consumo y observa el ritmo de cumplimiento.',
        'icon' => 'bi-droplet',
        'patterns' => ['water*'],
        'preserve' => ['date', 'period'],
        // Registro diario (objetivo, calendario y agregar rápido) y Ajustes (meta diaria) usan el panel contextual.
        'rail' => true,
        'tabs' => [
            ['label' => 'Registro diario', 'route' => 'water.daily', 'icon' => 'bi-droplet-half', 'active' => ['water.daily']],
            ['label' => 'Calendario', 'route' => 'water.calendar', 'archetype' => 'dashboard', 'icon' => 'bi-calendar3', 'active' => ['water.calendar']],
            ['label' => 'Semanal', 'route' => 'water.weekly', 'archetype' => 'dashboard', 'icon' => 'bi-bar-chart-line', 'active' => ['water.weekly']],
            ['label' => 'Rango', 'route' => 'water.range', 'archetype' => 'dashboard', 'icon' => 'bi-graph-up', 'active' => ['water.range']],
            ['label' => 'Ajustes', 'route' => 'water.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['water.settings']],
        ],
    ],
    'exercise' => [
        'archetype' => 'daily-log',
        'title' => 'Ejercicio', 'subtitle' => 'Actividad, esfuerzo y progreso físico.', 'icon' => 'bi-activity',
        'patterns' => ['exercise*'], 'preserve' => ['date'],
        // Registro diario (objetivo y calendario) y Ajustes (meta diaria) usan el panel contextual.
        'rail' => true,
        'tabs' => [
            ['label' => 'Registro diario', 'route' => 'exercise', 'icon' => 'bi-activity', 'active' => ['exercise']],
            ['label' => 'Estadísticas', 'route' => 'exercise.statistics', 'archetype' => 'dashboard', 'icon' => 'bi-bar-chart-line', 'active' => ['exercise.statistics']],
            ['label' => 'Ajustes', 'route' => 'exercise.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['exercise.settings']],
        ],
    ],
    'health' => [
        'archetype' => 'list',
        'title' => 'Salud', 'subtitle' => 'Historial, síntomas y seguimiento corporal.', 'icon' => 'bi-heart-pulse',
        'patterns' => ['health*'], 'preserve' => [],
        // Rediseño: sin banner de módulo (la identidad vive en la barra superior) y con panel contextual.
        'rail' => true,
        'tabs' => [
            ['label' => 'Registro', 'route' => 'health', 'icon' => 'bi-clock-history', 'active' => ['health']],
            ['label' => 'Vista del cuerpo', 'route' => 'health.body', 'archetype' => 'detail', 'icon' => 'bi-person-standing', 'active' => ['health.body']],
        ],
    ],
    'vehicles' => [
        'archetype' => 'list',
        'title' => 'Vehículos', 'subtitle' => 'Mantenimiento, consumo y próximos cuidados.', 'icon' => 'bi-car-front',
        'patterns' => ['vehicles*'], 'preserve' => [],
    ],
    'habits' => [
        'archetype' => 'daily-log',
        'title' => 'Hábitos', 'subtitle' => 'Constancia diaria y perspectiva semanal.', 'icon' => 'bi-check2-square',
        'patterns' => ['habits*'], 'preserve' => ['date'],
        'tabs' => [
            ['label' => 'Registro diario', 'route' => 'habits', 'icon' => 'bi-check2-square', 'active' => ['habits']],
            ['label' => 'Resumen semanal', 'route' => 'habits.weekly', 'archetype' => 'dashboard', 'icon' => 'bi-bar-chart-line', 'active' => ['habits.weekly']],
            ['label' => 'Ajustes', 'route' => 'habits.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['habits.settings']],
        ],
    ],
    'mood' => [
        'archetype' => 'daily-log',
        'title' => 'Ánimo y energía', 'subtitle' => 'Registra cómo te sientes y con cuánta energía cuentas.', 'icon' => 'bi-emoji-smile',
        'patterns' => ['mood*'], 'preserve' => ['date'],
        'tabs' => [
            ['label' => 'Registro diario', 'route' => 'mood', 'icon' => 'bi-emoji-smile', 'active' => ['mood']],
            ['label' => 'Ajustes', 'route' => 'mood.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['mood.settings']],
        ],
    ],
    'journal' => [
        'archetype' => 'list',
        'title' => 'Diario', 'subtitle' => 'Pensamientos, emociones y perspectiva en un mismo lugar.', 'icon' => 'bi-journal-text',
        'patterns' => ['journal*'], 'preserve' => ['date', 'week', 'period', 'from', 'to'],
        'tabs' => [
            ['label' => 'Entradas', 'route' => 'journal', 'icon' => 'bi-journal-text', 'active' => ['journal']],
            ['label' => 'Resumen', 'route' => 'journal.summary', 'icon' => 'bi-card-list', 'active' => ['journal.summary']],
            ['label' => 'Vida', 'route' => 'journal.life', 'archetype' => 'dashboard', 'icon' => 'bi-grid-3x3-gap', 'active' => ['journal.life']],
            ['label' => 'Semana', 'route' => 'journal.life.week', 'archetype' => 'detail', 'icon' => 'bi-calendar-week', 'active' => ['journal.life.week']],
        ],
    ],
    'pomodoro' => [
        'archetype' => 'daily-log',
        'title' => 'Pomodoro', 'subtitle' => 'Tiempo de enfoque, pausas y constancia.', 'icon' => 'bi-stopwatch',
        'patterns' => ['pomodoro*'], 'preserve' => ['date'],
        'tabs' => [
            ['label' => 'Temporizador', 'route' => 'pomodoro', 'icon' => 'bi-stopwatch', 'active' => ['pomodoro']],
            ['label' => 'Ajustes', 'route' => 'pomodoro.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['pomodoro.settings']],
        ],
    ],
    'meals' => [
        'archetype' => 'list',
        'title' => 'Comidas', 'subtitle' => 'Planifica la semana sin perder de vista el día.', 'icon' => 'bi-egg-fried',
        'patterns' => ['meals*'], 'preserve' => ['date'],
        'tabs' => [
            ['label' => 'Planificación', 'route' => 'meals.weekly', 'icon' => 'bi-calendar-week', 'active' => ['meals.weekly']],
            ['label' => 'Preparaciones', 'route' => 'meals.preparations', 'icon' => 'bi-egg-fried', 'active' => ['meals.preparations']],
            ['label' => 'Qué cocinar', 'route' => 'meals.suggestions', 'icon' => 'bi-lightbulb', 'active' => ['meals.suggestions']],
            ['label' => 'Recetas', 'route' => 'meals.recipes', 'icon' => 'bi-book', 'active' => ['meals.recipes']],
            ['label' => 'Ingredientes', 'route' => 'meals.ingredients', 'icon' => 'bi-basket', 'active' => ['meals.ingredients', 'meals.compare']],
            ['label' => 'Compras', 'route' => 'meals.shopping', 'icon' => 'bi-cart3', 'active' => ['meals.shopping']],
            ['label' => 'Historial de compras', 'route' => 'meals.purchases', 'icon' => 'bi-receipt', 'active' => ['meals.purchases', 'meals.purchases.show']],
            ['label' => 'Ajustes', 'route' => 'meals.settings', 'archetype' => 'settings', 'icon' => 'bi-gear', 'active' => ['meals.settings', 'meals.settings.categories']],
        ],
    ],
    'tasks' => [
        'archetype' => 'list',
        'title' => 'Tareas', 'subtitle' => 'Decide, ordena y completa el trabajo con claridad.', 'icon' => 'bi-list-check',
        'patterns' => ['tasks*'], 'preserve' => ['date', 'status', 'category', 'priority', 'size', 'q'],
        'tabs' => [
            ['label' => 'Lista', 'route' => 'tasks.list', 'icon' => 'bi-list-task', 'active' => ['tasks.list']],
            ['label' => 'Gantt', 'route' => 'tasks.gantt', 'archetype' => 'dashboard', 'icon' => 'bi-bar-chart-steps', 'active' => ['tasks.gantt']],
            ['label' => 'Calendario', 'route' => 'tasks.calendar', 'archetype' => 'dashboard', 'icon' => 'bi-calendar3', 'active' => ['tasks.calendar']],
            ['label' => 'Flujo', 'route' => 'tasks.flow', 'archetype' => 'dashboard', 'icon' => 'bi-signpost-split', 'active' => ['tasks.flow']],
            ['label' => 'Kanban', 'route' => 'tasks.kanban', 'archetype' => 'dashboard', 'icon' => 'bi-kanban', 'active' => ['tasks.kanban']],
            ['label' => 'Planificación', 'route' => 'tasks.planning', 'archetype' => 'dashboard', 'icon' => 'bi-calendar-week', 'active' => ['tasks.planning']],
            ['label' => 'Progreso', 'route' => 'tasks.progress', 'archetype' => 'dashboard', 'icon' => 'bi-trophy', 'active' => ['tasks.progress']],
            ['label' => 'Ajustes', 'route' => 'tasks.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['tasks.settings', 'tasks.settings.statuses']],
        ],
    ],
    'relationships' => [
        'archetype' => 'list',
        'title' => 'Relaciones', 'subtitle' => 'Personas, círculos y momentos que quieres cuidar.', 'icon' => 'bi-people',
        'patterns' => ['relationships*'],
        // Solo la pestaña Planes de una persona aporta panel contextual.
        'rail' => true,
        'preserve' => ['circle', 'status', 'archived', 'q', 'tag', 'category', 'period', 'month'],
        'tabs' => [
            ['label' => 'Relaciones', 'route' => 'relationships', 'icon' => 'bi-people', 'active' => ['relationships']],
            ['label' => 'Acontecimientos', 'route' => 'relationships.events', 'icon' => 'bi-calendar-event', 'active' => ['relationships.events']],
            ['label' => 'Cumpleaños', 'route' => 'relationships.birthdays', 'icon' => 'bi-cake2', 'active' => ['relationships.birthdays']],
        ],
    ],
    'plans' => [
        'archetype' => 'list',
        'title' => 'Planes', 'subtitle' => 'Lugares, actividades y experiencias para compartir.', 'icon' => 'bi-map',
        'patterns' => ['plans*'],
        'rail' => true,
        'rail_routes' => ['plans', 'plans.show'],
        'preserve' => ['group', 'status', 'q', 'sort', 'city', 'types', 'circles', 'people', 'tags', 'category'],
        'tabs' => [
            ['label' => 'Todos', 'route' => 'plans', 'icon' => 'bi-map', 'active' => ['plans', 'plans.show']],
            ['label' => 'Programados', 'route' => 'plans.scheduled', 'icon' => 'bi-calendar-event', 'active' => ['plans.scheduled'], 'preserve' => ['group','q','city','types','circles','people','tags','category']],
            ['label' => 'Historial', 'route' => 'plans.history', 'icon' => 'bi-clock-history', 'active' => ['plans.history'], 'preserve' => ['q','people']],
            ['label' => 'Ajustes', 'route' => 'plans.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['plans.settings'], 'preserve' => []],
        ],
    ],
    'goals' => [
        'archetype' => 'list',
        'title' => 'Objetivos', 'subtitle' => 'Resultados, avances y próximos pasos.', 'icon' => 'bi-flag',
        'patterns' => ['goals*'], 'preserve' => ['status'],
    ],
    'statistics' => [
        'archetype' => 'dashboard',
        'title' => 'Estadísticas', 'subtitle' => 'Patrones y tendencias de tu actividad.', 'icon' => 'bi-bar-chart',
        'patterns' => ['statistics*'], 'preserve' => ['days'],
    ],
    'negative-habits' => [
        'archetype' => 'daily-log',
        'title' => 'Hábitos a evitar', 'subtitle' => 'Observa patrones sin perder de vista tu progreso.', 'icon' => 'bi-shield-check',
        'patterns' => ['negative-habits*'], 'preserve' => ['date'],
    ],
    'settings' => [
        'archetype' => 'settings',
        'title' => 'Ajustes generales', 'subtitle' => 'Perfil, seguridad y preferencias que afectan toda la aplicación.', 'icon' => 'bi-gear',
        'patterns' => ['settings*'], 'preserve' => [],
    ],
];
