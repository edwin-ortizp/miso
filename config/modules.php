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
            ['label' => 'Formación', 'route' => 'learning', 'module' => 'learning', 'icon' => 'bi-mortarboard', 'active' => ['learning*']],
            ['label' => 'Colección', 'route' => 'game.balls', 'module' => 'game', 'icon' => 'bi-stars', 'active' => ['game*'], 'counter' => 'App\Support\Game\TabCounters@balls'],
        ]],
        'life' => ['label' => 'Vida personal', 'icon' => 'bi-person-heart', 'items' => [
            ['label' => 'Diario', 'route' => 'journal', 'module' => 'journal', 'icon' => 'bi-journal-text', 'active' => ['journal*']],
            ['label' => 'Amigos', 'route' => 'social.friends', 'module' => 'social', 'icon' => 'bi-person-hearts', 'active' => ['social*']],
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
        'patterns' => ['home', 'panel', 'day.close'],
        'preserve' => ['date'],
    ],
    'water' => [
        'archetype' => 'daily-log',
        'title' => 'Hidratación',
        'subtitle' => 'Registra tu consumo y observa el ritmo de cumplimiento.',
        'icon' => 'bi-droplet',
        'patterns' => ['water*'],
        'preserve' => ['date', 'period'],
        // El registro diario (objetivo, calendario y agregar rápido) usa el panel contextual; Ajustes usa secciones con barra lateral.
        'rail' => true,
        'tabs' => [
            ['label' => 'Registro diario', 'route' => 'water.daily', 'icon' => 'bi-droplet-half', 'active' => ['water.daily']],
            ['label' => 'Calendario', 'route' => 'water.calendar', 'archetype' => 'dashboard', 'icon' => 'bi-calendar3', 'active' => ['water.calendar']],
            ['label' => 'Semanal', 'route' => 'water.weekly', 'archetype' => 'dashboard', 'icon' => 'bi-bar-chart-line', 'active' => ['water.weekly']],
            ['label' => 'Rango', 'route' => 'water.range', 'archetype' => 'dashboard', 'icon' => 'bi-graph-up', 'active' => ['water.range']],
            ['label' => 'Ajustes', 'route' => 'water.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['water.settings', 'water.settings.goal', 'water.settings.categories']],
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
            ['label' => 'Ajustes', 'route' => 'exercise.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['exercise.settings', 'exercise.settings.categories']],
        ],
    ],
    'health' => [
        'archetype' => 'list',
        'title' => 'Salud', 'subtitle' => 'Historial, síntomas y seguimiento corporal.', 'icon' => 'bi-heart-pulse',
        'patterns' => ['health*'], 'preserve' => [],
        // Rediseño: sin banner de módulo (la identidad vive en la barra superior) y con panel contextual.
        'rail' => true,
        // CHG-139: funciones que cada persona puede apagar. «Registro» es la base y no se apaga.
        'features' => [
            'body' => ['label' => 'Vista del cuerpo', 'description' => 'Mapa del cuerpo para ubicar síntomas.', 'icon' => 'bi-person-standing'],
            'measurements' => ['label' => 'Mediciones', 'description' => 'Peso, tensión, glucosa y otras medidas.', 'icon' => 'bi-activity'],
            'medications' => ['label' => 'Medicamentos', 'description' => 'Tomas, horarios y recordatorios.', 'icon' => 'bi-capsule'],
            'sleep' => ['label' => 'Sueño', 'description' => 'Horas y calidad de tu descanso.', 'icon' => 'bi-moon-stars'],
            'cycle' => ['label' => 'Ciclo menstrual', 'description' => 'Periodo y síntomas. Dato sensible, sin recordatorios.', 'icon' => 'bi-droplet', 'sensitive' => true],
            'trends' => ['label' => 'Tendencias', 'description' => 'Gráficas de mediciones y sueño.', 'icon' => 'bi-graph-up', 'requires_any' => ['measurements', 'sleep']],
        ],
        'tabs' => [
            ['label' => 'Registro', 'route' => 'health', 'icon' => 'bi-clock-history', 'active' => ['health']],
            ['label' => 'Vista del cuerpo', 'feature' => 'body', 'route' => 'health.body', 'archetype' => 'detail', 'icon' => 'bi-person-standing', 'active' => ['health.body']],
            ['label' => 'Mediciones', 'feature' => 'measurements', 'route' => 'health.measurements', 'icon' => 'bi-activity', 'active' => ['health.measurements']],
            ['label' => 'Medicamentos', 'feature' => 'medications', 'route' => 'health.medications', 'icon' => 'bi-capsule', 'active' => ['health.medications']],
            ['label' => 'Sueño', 'feature' => 'sleep', 'route' => 'health.sleep', 'icon' => 'bi-moon-stars', 'active' => ['health.sleep']],
            ['label' => 'Ciclo', 'feature' => 'cycle', 'route' => 'health.cycle', 'icon' => 'bi-droplet', 'active' => ['health.cycle']],
            ['label' => 'Tendencias', 'feature' => 'trends', 'route' => 'health.trends', 'icon' => 'bi-graph-up', 'active' => ['health.trends']],
        ],
    ],
    'vehicles' => [
        'archetype' => 'list',
        'title' => 'Vehículos', 'subtitle' => 'Mantenimiento, consumo y próximos cuidados.', 'icon' => 'bi-car-front',
        'patterns' => ['vehicles*'], 'preserve' => [],
        // CHG-064: panel lateral con el guardián en la página principal.
        'rail' => true,
        'rail_routes' => ['vehicles'],
    ],
    'habits' => [
        'archetype' => 'daily-log',
        'title' => 'Hábitos', 'subtitle' => 'Constancia diaria y perspectiva semanal.', 'icon' => 'bi-check2-square',
        'patterns' => ['habits*'], 'preserve' => ['date'],
        // CHG-058: el registro diario muestra Constancia (con el guardián) y el calendario en el panel contextual.
        'rail' => true,
        'rail_routes' => ['habits'],
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
        // CHG-063: panel lateral con el guardián en la página principal.
        'rail' => true,
        'rail_routes' => ['mood'],
        'tabs' => [
            ['label' => 'Registro diario', 'route' => 'mood', 'icon' => 'bi-emoji-smile', 'active' => ['mood']],
            ['label' => 'Ajustes', 'route' => 'mood.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['mood.settings', 'mood.settings.categories']],
        ],
    ],
    'journal' => [
        'archetype' => 'list',
        'title' => 'Diario', 'subtitle' => 'Pensamientos, emociones y perspectiva en un mismo lugar.', 'icon' => 'bi-journal-text',
        'patterns' => ['journal*'], 'preserve' => ['date', 'week', 'period', 'from', 'to'],
        // CHG-063: panel lateral con el guardián en la página principal.
        'rail' => true,
        'rail_routes' => ['journal'],
        'tabs' => [
            ['label' => 'Entradas', 'route' => 'journal', 'icon' => 'bi-journal-text', 'active' => ['journal']],
            ['label' => 'Resumen', 'route' => 'journal.summary', 'icon' => 'bi-card-list', 'active' => ['journal.summary']],
            ['label' => 'Vida', 'route' => 'journal.life', 'archetype' => 'dashboard', 'icon' => 'bi-grid-3x3-gap', 'active' => ['journal.life']],
            ['label' => 'Semana', 'route' => 'journal.life.week', 'archetype' => 'detail', 'icon' => 'bi-calendar-week', 'active' => ['journal.life.week']],
        ],
    ],
    'game' => [
        'archetype' => 'list',
        'title' => 'Colección', 'subtitle' => 'Cada acción suma; ninguna ausencia resta.', 'icon' => 'bi-stars',
        'patterns' => ['game*'], 'preserve' => [],
        'tabs' => [
            ['label' => 'Balls', 'route' => 'game.balls', 'icon' => 'bi-circle-half', 'active' => ['game.balls'], 'counter' => 'App\Support\Game\TabCounters@balls'],
            ['label' => 'Colección', 'route' => 'game.collection', 'icon' => 'bi-grid-3x3-gap', 'active' => ['game.collection'], 'counter' => 'App\Support\Game\TabCounters@collection'],
            ['label' => 'Semana', 'route' => 'game.week', 'icon' => 'bi-people', 'active' => ['game.week'], 'counter' => 'App\Support\Game\TabCounters@week'],
            ['label' => 'Temporada', 'route' => 'game.season', 'icon' => 'bi-calendar-event', 'active' => ['game.season'], 'counter' => 'App\Support\Game\TabCounters@season'],
            ['label' => 'Ajustes', 'route' => 'game.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['game.settings', 'game.settings.*']],
        ],
    ],
    'pomodoro' => [
        'archetype' => 'daily-log',
        'title' => 'Pomodoro', 'subtitle' => 'Tiempo de enfoque, pausas y constancia.', 'icon' => 'bi-stopwatch',
        'patterns' => ['pomodoro*'], 'preserve' => ['date', 'q', 'sort', 'scope', 'origin', 'month'],
        // El temporizador muestra meta del día y calendario de cumplimiento en el panel contextual.
        'rail' => true,
        'tabs' => [
            ['label' => 'Temporizador', 'route' => 'pomodoro', 'icon' => 'bi-stopwatch', 'active' => ['pomodoro']],
            ['label' => 'Ajustes', 'route' => 'pomodoro.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['pomodoro.settings']],
        ],
    ],
    'meals' => [
        'archetype' => 'list',
        'title' => 'Comidas', 'subtitle' => 'Planifica la semana sin perder de vista el día.', 'icon' => 'bi-egg-fried',
        'patterns' => ['meals*'], 'preserve' => ['date'],
        // CHG-064: panel lateral con el guardián en la página principal.
        'rail' => true,
        'rail_routes' => ['meals.weekly'],
        // CHG-139: Planificación e Ingredientes son la base (recetas, plan, preparaciones e inventario dependen de ellos).
        'features' => [
            'recipes' => ['label' => 'Recetas', 'description' => 'Tus recetas con ingredientes y porciones.', 'icon' => 'bi-book'],
            'preparations' => ['label' => 'Preparaciones', 'description' => 'Porciones cocinadas para varios días.', 'icon' => 'bi-egg-fried', 'requires' => ['recipes']],
            'suggestions' => ['label' => 'Qué cocinar', 'description' => 'Ideas según lo que tienes en casa.', 'icon' => 'bi-lightbulb', 'requires' => ['recipes']],
            'shopping' => ['label' => 'Compras', 'description' => 'Lista de compras y registro de tickets.', 'icon' => 'bi-cart3'],
            'purchases' => ['label' => 'Historial de compras', 'description' => 'Tickets y precios por tienda.', 'icon' => 'bi-receipt', 'requires' => ['shopping']],
            'inventory' => ['label' => 'Movimientos de inventario', 'description' => 'Entradas y salidas de tu despensa.', 'icon' => 'bi-journal-text'],
        ],
        'tabs' => [
            ['label' => 'Planificación', 'route' => 'meals.weekly', 'icon' => 'bi-calendar-week', 'active' => ['meals.weekly']],
            ['label' => 'Preparaciones', 'feature' => 'preparations', 'route' => 'meals.preparations', 'icon' => 'bi-egg-fried', 'active' => ['meals.preparations']],
            ['label' => 'Qué cocinar', 'feature' => 'suggestions', 'route' => 'meals.suggestions', 'icon' => 'bi-lightbulb', 'active' => ['meals.suggestions']],
            ['label' => 'Recetas', 'feature' => 'recipes', 'route' => 'meals.recipes', 'icon' => 'bi-book', 'active' => ['meals.recipes']],
            ['label' => 'Ingredientes', 'route' => 'meals.ingredients', 'icon' => 'bi-basket', 'active' => ['meals.ingredients', 'meals.compare', 'meals.inventory.history']],
            ['label' => 'Compras', 'feature' => 'shopping', 'route' => 'meals.shopping', 'icon' => 'bi-cart3', 'active' => ['meals.shopping']],
            ['label' => 'Movimientos de inventario', 'feature' => 'inventory', 'route' => 'meals.inventory', 'icon' => 'bi-journal-text', 'active' => ['meals.inventory', 'meals.inventory.show']],
            ['label' => 'Historial de compras', 'feature' => 'purchases', 'route' => 'meals.purchases', 'icon' => 'bi-receipt', 'active' => ['meals.purchases', 'meals.purchases.show']],
            ['label' => 'Ajustes', 'route' => 'meals.settings', 'archetype' => 'settings', 'icon' => 'bi-gear', 'active' => ['meals.settings', 'meals.settings.categories', 'meals.settings.locations', 'meals.settings.schedules']],
        ],
    ],
    'tasks' => [
        'archetype' => 'list',
        'title' => 'Tareas', 'subtitle' => 'Decide, ordena y completa el trabajo con claridad.', 'icon' => 'bi-list-check',
        'patterns' => ['tasks*'], 'preserve' => ['date', 'status', 'category', 'priority', 'size', 'q'],
        // Atajos de la barra de estado: action = focus | route:<nombre> | dispatch:<evento>.
        'status' => [
            ['label' => 'Iniciar foco', 'icon' => 'bi-play-circle', 'action' => 'focus', 'title' => 'Inicia un Pomodoro de trabajo'],
            ['label' => 'Historial de foco', 'icon' => 'bi-clock-history', 'action' => 'route:pomodoro'],
        ],
        // CHG-139: «Lista» y «Ajustes» son la base y no se apagan.
        'features' => [
            'gantt' => ['label' => 'Gantt', 'description' => 'Cronograma de tareas con fechas.', 'icon' => 'bi-bar-chart-steps'],
            'calendar' => ['label' => 'Calendario', 'description' => 'Tus tareas por día, semana y mes.', 'icon' => 'bi-calendar3'],
            'flow' => ['label' => 'Flujo', 'description' => 'Tareas según sus dependencias.', 'icon' => 'bi-signpost-split'],
            'kanban' => ['label' => 'Kanban', 'description' => 'Tablero por estados.', 'icon' => 'bi-kanban'],
            'planning' => ['label' => 'Planificación', 'description' => 'Atrasadas, hoy, mañana y futuras.', 'icon' => 'bi-calendar-week'],
            'progress' => ['label' => 'Progreso', 'description' => 'Avance y logros de tus tareas.', 'icon' => 'bi-trophy'],
        ],
        'tabs' => [
            ['label' => 'Lista', 'route' => 'tasks.list', 'icon' => 'bi-list-task', 'active' => ['tasks.list']],
            ['label' => 'Gantt', 'feature' => 'gantt', 'route' => 'tasks.gantt', 'archetype' => 'dashboard', 'icon' => 'bi-bar-chart-steps', 'active' => ['tasks.gantt']],
            ['label' => 'Calendario', 'feature' => 'calendar', 'route' => 'tasks.calendar', 'archetype' => 'dashboard', 'icon' => 'bi-calendar3', 'active' => ['tasks.calendar']],
            ['label' => 'Flujo', 'feature' => 'flow', 'route' => 'tasks.flow', 'archetype' => 'dashboard', 'icon' => 'bi-signpost-split', 'active' => ['tasks.flow']],
            ['label' => 'Kanban', 'feature' => 'kanban', 'route' => 'tasks.kanban', 'archetype' => 'dashboard', 'icon' => 'bi-kanban', 'active' => ['tasks.kanban']],
            ['label' => 'Planificación', 'feature' => 'planning', 'route' => 'tasks.planning', 'archetype' => 'dashboard', 'icon' => 'bi-calendar-week', 'active' => ['tasks.planning']],
            ['label' => 'Progreso', 'feature' => 'progress', 'route' => 'tasks.progress', 'archetype' => 'dashboard', 'icon' => 'bi-trophy', 'active' => ['tasks.progress']],
            ['label' => 'Ajustes', 'route' => 'tasks.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['tasks.settings', 'tasks.settings.statuses', 'tasks.settings.planning']],
        ],
    ],
    'social' => [
        'archetype' => 'list',
        'title' => 'Amigos', 'subtitle' => 'Personas de MiSo con las que compartes constancia.', 'icon' => 'bi-person-hearts',
        'patterns' => ['social*'], 'preserve' => [],
        'tabs' => [
            ['label' => 'Amigos', 'route' => 'social.friends', 'icon' => 'bi-people', 'active' => ['social.friends']],
            ['label' => 'Semana', 'route' => 'social.week', 'icon' => 'bi-calendar-week', 'active' => ['social.week']],
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
            ['label' => 'Ajustes', 'route' => 'relationships.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['relationships.settings', 'relationships.settings.events']],
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
    'learning' => [
        'title' => 'Formación', 'subtitle' => 'Organiza lo que estudias y avanza paso a paso.', 'icon' => 'bi-mortarboard',
        'archetype' => 'list', 'patterns' => ['learning*'],
        'preserve' => ['q', 'type', 'category', 'status'], 'rail' => true,
        'tabs' => [
            ['label' => 'Formaciones', 'route' => 'learning', 'icon' => 'bi-mortarboard', 'active' => ['learning', 'learning.show', 'learning.units.show']],
            ['label' => 'Ajustes', 'route' => 'learning.settings', 'archetype' => 'settings', 'icon' => 'bi-gear', 'active' => ['learning.settings', 'learning.settings.categories']],
        ],
    ],
    'goals' => [
        'archetype' => 'list',
        'title' => 'Objetivos', 'subtitle' => 'Resultados, avances y próximos pasos.', 'icon' => 'bi-flag',
        'patterns' => ['goals*'], 'preserve' => ['status'],
        // CHG-063: panel lateral con el guardián en la página principal.
        'rail' => true,
        'rail_routes' => ['goals'],
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
        // CHG-063: panel lateral con el guardián en la página principal.
        'rail' => true,
        'rail_routes' => ['negative-habits'],
        'tabs' => [
            ['label' => 'Semana', 'route' => 'negative-habits', 'icon' => 'bi-shield-check', 'active' => ['negative-habits']],
            ['label' => 'Ajustes', 'route' => 'negative-habits.settings', 'archetype' => 'settings', 'icon' => 'bi-sliders', 'active' => ['negative-habits.settings']],
        ],
    ],
    'settings' => [
        'archetype' => 'settings',
        'title' => 'Ajustes generales', 'subtitle' => 'Perfil, seguridad y preferencias que afectan toda la aplicación.', 'icon' => 'bi-gear',
        'patterns' => ['settings*'], 'preserve' => [],
    ],
];
