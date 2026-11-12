<?php

return [
    'description_cache_store' => 'file',
    // Valores por defecto; cada usuario los ajusta en Colección → Ajustes (ModuleSetting módulo "game").
    'defaults' => [
        'enabled' => true,
        'sources' => ['tasks' => true, 'habits' => true, 'focus' => true, 'goals' => true, 'health' => true, 'vehicle' => true, 'wellbeing' => true, 'people' => true, 'home' => true, 'personal_tasks' => true],
        'daily_limit' => 5,
        'pity_threshold' => 10,
        'rarity_weights' => ['common' => 60, 'uncommon' => 30, 'rare' => 9, 'legendary' => 1],
        'director_enabled' => true,
        // Celebraciones (CHG-034): activas y cuáles del catálogo pueden salir.
        'celebrations' => true,
        // Sonidos sutiles (CHG-044): encendidos por defecto, volumen bajo fijo.
        'sounds' => true,
        'celebration_set' => ['confetti', 'cats', 'shooting-stars', 'balloons', 'fireworks', 'bubbles', 'balls', 'petals'],
        'seasons_enabled' => true,
        // Racha (CHG-076): acciones para cumplir el día (1–10) y si se muestra.
        'streak_threshold' => 3,
        'streak_visible' => true,
        // Reto del día (CHG-077).
        'daily_challenge' => true,
    ],

    // Experiencia que suma una especie repetida a su ejemplar.
    'duplicate_xp' => 10,

    // Niveles: nivel = 1 + floor(xp / xp_per_level), con tope max_level. Nunca bajan.
    'xp_per_level' => 40,
    'max_level' => 100,
    // Experiencia de bienvenida al descubrir una línea nueva.
    'welcome_xp' => 5,
    // Nivel por defecto para llegar a cada etapa; una especie puede definir su propio evolve_level.
    'stage_evolve_levels' => [2 => 10, 3 => 25],

    // Equipo semanal y misiones (CHG-021). La semana va de lunes a domingo en la zona de la app.
    'team_size' => 6,
    'team_xp' => 3,

    // Constancia (CHG-042): xp mínima para cada integrante del equipo por el primer
    // registro del día de cada fuente. Sin ball, misiones, afinidad ni bono de temporada.
    'consistency_xp' => 1,

    // Balls extra (CHG-076): hitos de racha, reto del día, álbumes y camino. Fuera del límite
    // diario, con tope semanal común para que no se vuelvan algo para farmear.
    'bonus_balls_per_week' => 4,

    // Racha de constancia (CHG-076). El día se cumple con el umbral de acciones del juego en al
    // menos min_areas áreas distintas; gracias automáticas por mes calendario; nunca resta.
    'streak' => [
        'min_areas' => 2,
        'grace_days_per_month' => 2,
        'milestones' => [3, 7, 14, 30, 60, 100, 180, 365],
        'history_days' => 400,
    ],

    // Reto del día (CHG-077): plantillas v1, metas (mín, máx) y lo esperado por día de cada área
    // para saber cuál va más descuidada en la semana.
    'daily_challenge' => [
        // Sin 'checkin': en el Inicio ya lo pide la burbuja del compañero (CHG-080).
        'templates' => ['tasks_done', 'category_tasks', 'habit_period', 'water_logs'],
        'targets' => ['tasks_done' => [2, 3], 'category_tasks' => [2, 2], 'water_logs' => [2, 3]],
        'weekly_per_day' => ['tasks' => 2, 'water' => 3, 'checkin' => 1],
    ],

    // Compañero del panel (CHG-049): familia de escenario por tipo (slug) y reacciones al avance del día.
    'stage_families' => [
        'fuego' => 'fuego', 'dragon' => 'fuego',
        'agua' => 'agua', 'hielo' => 'agua',
        'planta' => 'planta', 'bicho' => 'planta', 'veneno' => 'planta',
        'electrico' => 'electrico', 'acero' => 'electrico',
        'psiquico' => 'mistico', 'hada' => 'mistico', 'fantasma' => 'mistico', 'siniestro' => 'mistico',
        'roca' => 'tierra', 'tierra' => 'tierra', 'lucha' => 'tierra',
        'volador' => 'cielo', 'normal' => 'cielo',
    ],
    // Guardianes de módulo (CHG-055): tipos afines por módulo y frase por estado.
    // Avisos push del guardián (CHG-068). Franjas en America/Bogota, [hora inicio, hora fin).
    'nudges' => [
        'enabled' => (bool) env('GUARDIAN_NUDGES_ENABLED', true),
        'slots' => [
            'morning' => ['label' => 'Mañana', 'from' => 8, 'to' => 10],
            'afternoon' => ['label' => 'Tarde', 'from' => 14, 'to' => 16],
            'night' => ['label' => 'Noche', 'from' => 19, 'to' => 21],
        ],
        'daily_cap' => 2,
        // Módulos con meta diaria medible: solo se avisa si queda algo pendiente.
        'modules' => ['habits', 'water', 'tasks', 'exercise'],
        // Límites por corrida para no chocar con los cortes del hosting compartido.
        'max_seconds' => 40,
        'max_sends' => 200,
        // No se envían por push avisos más viejos que esto (evita ráfagas tras activar el canal).
        'max_age_hours' => 12,
    ],
    'module_guardians' => [
        'tasks' => ['psiquico', 'acero'],
        'health' => ['hada', 'normal'],
        'habits' => ['fuego', 'planta'],
        'water' => ['agua', 'hielo'],
        'exercise' => ['lucha', 'roca'],
        'pomodoro' => ['electrico', 'psiquico'],
        'goals' => ['dragon', 'volador'],
        'mood' => ['hada', 'normal'],
        'journal' => ['psiquico', 'fantasma'],
        'negative-habits' => ['siniestro', 'lucha'],
        'meals' => ['planta', 'bicho'],
        'relationships' => ['hada', 'normal'],
        'plans' => ['volador', 'tierra'],
        'vehicles' => ['acero', 'electrico'],
    ],
    // Frase cuando un módulo sin frases propias no tiene consejos (CHG-063).
    'guardian_default_line' => 'Aquí estoy contigo.',
    // Compañero de foco (CHG-059): frases por etapa del ciclo; cambian solo al cambiar de etapa.
    'focus_companion' => [
        'ready' => ['Cuando quieras, empezamos.', 'Elige una cosa y vamos por ella.'],
        'start' => ['Arrancamos. Yo vigilo; tú enfócate.', 'Un paso a la vez; aquí estoy.'],
        'half' => ['Ya vas por la mitad; sigue a tu ritmo.', 'Mitad lograda. Buen foco.'],
        'final' => ['Último tramo. Cierra con calma.', 'Quedan pocos minutos; tú puedes.'],
        'paused' => ['Te espero aquí.', 'Pausa tomada; seguimos cuando vuelvas.'],
        'done' => ['¡Bloque completo! Bien hecho.', '¡Lo lograste! Tómate un respiro.'],
        'break' => ['Estira, toma agua y mira lejos.', 'Descansa; el siguiente bloque te espera.'],
    ],
    'guardian_lines' => [
        'tasks' => [
            'free' => 'Día libre, disfrútalo.',
            'greet' => 'Hoy empezamos juntos.',
            'cheer' => 'Un pasito y ya cuenta.',
            'happy' => 'Buen ritmo, sigue así.',
            'celebrate' => '¡Vas volando hoy!',
        ],
        'health' => [
            'care' => 'Recupérate con calma, aquí estoy.',
            'free' => 'Todo en orden, cuídate.',
            'greet' => 'Hoy también te cuidamos.',
            'cheer' => 'Un cuidado a la vez.',
            'happy' => 'Te estás cuidando bien.',
            'celebrate' => '¡Pendientes de salud al día!',
        ],
    ],
    // Consejos del guardián (CHG-057): plantillas por regla; {variables} se escapan al reemplazar.
    'guardian_advice' => [
        'refresh_seconds' => 300,
        'tasks' => [
            'next' => 'Tu próxima es {task} a las {time}.',
            'overload' => 'Tienes {planned} planificadas y quedan {left} de día: mueve algo.',
            'stale' => '{count} vencidas de {category} llevan más de 7 días: ¿cerramos o movemos una?',
            'streak' => 'Ya cerraste {count} hoy, más que tus {weekday} de costumbre.',
            'single' => 'Hoy todo es {category}; ¿cabe algo distinto?',
        ],
        'health' => [
            'followup' => 'Hoy toca seguimiento de {event} (día {day}).',
            'unlogged' => 'Llevas {days} días sin anotar cómo sigue {event}.',
            'next' => 'Próximo cuidado: {event} {when}.',
            'calm' => '{month} va con {count} eventos; {peak} tuvo {peak_count}.',
        ],
        'water' => [
            'left' => 'Te faltan {left} para tu meta de hoy.',
            'pace' => 'Vas {now}; a esta hora sueles llevar {usual}.',
            'gap' => 'Llevas {hours} h sin tomar agua; un vaso ahora ayuda.',
            'streak' => '{days} días seguidos cumpliendo tu meta de agua.',
        ],
        'exercise' => [
            'left' => 'Te faltan {minutes} min de movimiento para tu meta.',
            'idle' => 'Llevas {days} días sin moverte; ¿algo corto hoy?',
            'forgotten' => 'Hace {days} días no haces {type}; antes era de tus favoritos.',
            'week' => 'Esta semana llevas {now} min, más que los {last} de la anterior.',
        ],
        'pomodoro' => [
            'left' => 'Te faltan {minutes} min de foco para tu meta.',
            'top' => 'Tu tarea con más foco esta semana: {task} ({minutes} min).',
            'slot' => 'La {slot} es tu mejor franja de foco: aprovéchala.',
            'now' => 'Ahora toca {task}; ¿un bloque de foco?',
        ],
        'goals' => [
            'due' => 'A {goal} le quedan {days} días y vas en {percent} %.',
            'due_plain' => 'A {goal} le quedan {days} días; ¿cuál es el siguiente paso?',
            'idle' => 'No hay avances escritos en {goal} desde hace {days} días. ¿Quieres registrar alguno?',
            'milestone' => '¡Hito en {goal}! Eso merece celebrarse.',
        ],
        'mood' => [
            'missing' => 'Aún no anotas cómo te sientes hoy; ¿cómo vas?',
            'up' => 'Tu ánimo de esta semana va mejor que la anterior.',
            'down' => 'Esta semana ha pesado un poco más; aquí estoy contigo.',
            'energy' => 'Tu energía suele estar más alta en la {slot}.',
        ],
        'negative' => [
            'clean' => '{days} días sin {habit}. Cada día cuenta.',
            'record' => 'Estás a {left} días de tu mejor racha sin {habit}.',
            'support' => 'Lo de {habit} no borra tu camino; hoy empiezas de nuevo.',
        ],
        'meals' => [
            'next' => 'Hoy toca {meal} a las {time}.',
            'pending' => '{meal} de hoy sigue sin marcar; ¿ya comiste?',
            'unplanned' => '{days} días de esta semana aún no tienen plan de comidas.',
        ],
        'relationships' => [
            'birthday' => '{person} cumple años {when}.',
            'distance' => 'Hace {days} días no registras nada con {person}; ¿un saludo?',
        ],
        'plans' => [
            'soon' => '{plan} está agendado {when}.',
            'pending' => 'Tienes {count} planes por estrenar; ¿qué tal {plan}?',
            'idle' => 'Tu último plan fue hace {days} días; ¿salimos?',
        ],
        'vehicles' => [
            'maintenance' => '{task} de {vehicle} {when}.',
            'fuel' => 'Hace {days} días no registras carga en {vehicle}.',
        ],
        'journal' => [
            'gap' => 'Llevas {days} días sin escribir; ¿qué te ronda hoy?',
            'streak' => '{days} días seguidos escribiendo. Qué buena constancia.',
            'memory' => '{when} escribiste: “{quote}”',
        ],
        'habits' => [
            'moment' => 'Te faltan {count} de la {moment}; {habit} es el más corto.',
            'risk' => '{habit} lleva {days} días seguidos; hoy mantiene la racha.',
            'forgotten' => '{habit} se ha quedado {days} días este mes; ¿lo retomamos?',
        ],
    ],
    'buddy_moods' => [
        'celebrate' => ['min' => 70, 'text' => '¡Vas volando!'],
        'happy' => ['min' => 30, 'text' => 'Buen ritmo'],
        'greet' => ['before_hour' => 15, 'text' => 'Hoy empezamos juntos'],
        'cheer' => ['text' => 'Un pasito y ya cuenta'],
        'free' => ['text' => 'Día libre, disfrútalo'],
    ],

    // Integrante del día (CHG-043): frases que el director agrega cada semana.
    'quotes' => ['min' => 7, 'max' => 14, 'max_length' => 140, 'max_age_days' => 60],
    'consistency_sources' => ['vehicle' => 'Vehículo', 'wellbeing' => 'Bienestar', 'people' => 'Personas', 'home' => 'Hogar', 'personal_tasks' => 'Tareas personales'],
    // CHG-048: tareas de estas categorías dan constancia (micro-xp) en vez de ball.
    'consistency_task_categories' => ['personal'],
    'affinity_bonus_xp' => 6,
    'missions_per_week' => 3,
    'mission_target' => [2, 4],
    'mission_rewards' => ['half' => 1, 'full' => 2],
    'mission_types' => ['category_tasks', 'habit_groups', 'variety'],
    // Habilidades reutilizables: afinidad con una categoría de tarea del usuario o con los hábitos.
    'habits_ability' => ['key' => 'habits', 'label' => 'Hábitos'],
    // Ámbitos que no son categorías de tarea (CHG-027): afinidad, variedad, bonos y misiones del director.
    'scopes' => ['habits' => 'Hábitos', 'focus' => 'Foco', 'goals' => 'Metas', 'health' => 'Salud'],

    // Temporadas del director (CHG-022). El director es un schedule de Paseo que usa el MCP;
    // estas son las reglas fijas que el backend exige a cualquier propuesta.
    'season' => [
        'default_weeks' => 4,
        'weeks' => [2, 6],
        'mission_target' => [2, 5],
        'max_focus' => 2,
        'team_bonus' => [1.0, 1.5],
        'max_bonus_scopes' => 2,
        'rarity_max' => 100,
        'legendary_max' => 3,
        'featured_weight_max' => 2,
        // Temporadas temáticas (CHG-045): hasta 2 tipos destacados, x1,5 dentro de su rareza.
        'max_featured_types' => 2,
        'featured_type_weight' => 1.5,
        'theme_hint_weeks' => 6,
        'propose_window_days' => 7,
        'history_weeks' => 8,
        // Camino de temporada (CHG-079): objetivo = semanas × points_per_week (mínimo min_target);
        // 1 punto por actividad hasta daily_cap al día y mission_points por misión cumplida.
        'path' => [
            'points_per_week' => 20,
            'min_target' => 20,
            'daily_cap' => 5,
            'mission_points' => 3,
            'nodes' => [
                ['percent' => 5, 'reward' => ['type' => 'ball', 'count' => 1]],
                ['percent' => 15, 'reward' => ['type' => 'xp', 'count' => 10]],
                ['percent' => 30, 'reward' => ['type' => 'ball', 'count' => 1]],
                ['percent' => 45, 'reward' => ['type' => 'xp', 'count' => 15]],
                ['percent' => 60, 'reward' => ['type' => 'xp', 'count' => 20]],
                ['percent' => 80, 'reward' => ['type' => 'ball', 'count' => 1]],
                ['percent' => 100, 'reward' => ['type' => 'ball', 'count' => 2]],
            ],
        ],
    ],

    // Poder (CHG-025): round(total_estadísticas_base × (1 + (nivel − 1) × factor)). Sin estadísticas usa la base.
    'power_level_factor' => 0.05,
    'power_default_base' => 300,
    // Tipos con color propio en _game.css (clase game-type--{slug}); los demás usan el neutro.
    'type_palette' => ['acero', 'agua', 'bicho', 'dragon', 'electrico', 'fantasma', 'fuego', 'hada', 'hielo', 'lucha', 'normal', 'planta', 'psiquico', 'roca', 'siniestro', 'tierra', 'veneno', 'volador'],

    // Temas de temporada (CHG-045). window: [mes-día inicio, mes-día fin]; el cumpleaños usa +-7 días
    // alrededor del mes-día de users.birth_date (nunca el año). celebration: función en game-celebrations.js.
    'season_themes' => [
        'navidad' => ['label' => 'Navidad', 'icon' => 'bi-tree-fill', 'celebration' => 'snow', 'window' => ['12-01', '12-31'], 'suggested_types' => ['hielo', 'hada']],
        'ano-nuevo' => ['label' => 'Año nuevo', 'icon' => 'bi-stars', 'celebration' => 'sparkles', 'window' => ['01-01', '01-31'], 'suggested_types' => ['electrico', 'psiquico']],
        'halloween' => ['label' => 'Halloween', 'icon' => 'bi-moon-stars-fill', 'celebration' => 'pumpkins', 'window' => ['10-15', '10-31'], 'suggested_types' => ['fantasma', 'siniestro']],
        'amor-amistad' => ['label' => 'Amor y amistad', 'icon' => 'bi-heart-fill', 'celebration' => 'hearts', 'window' => ['09-01', '09-30'], 'suggested_types' => ['hada', 'normal']],
        'cumpleanos' => ['label' => 'Tu cumpleaños', 'icon' => 'bi-cake2-fill', 'celebration' => 'candles', 'window' => null, 'suggested_types' => ['fuego', 'hada']],
    ],

    // Catálogo de celebraciones (resources/js/game-celebrations.js). Para agregar una: clave aquí y función allá.
    'celebration_catalog' => [
        'confetti' => 'Confeti',
        'cats' => 'Gatitos',
        'shooting-stars' => 'Estrellas fugaces',
        'balloons' => 'Globos',
        'fireworks' => 'Fuegos artificiales',
        'bubbles' => 'Burbujas',
        'balls' => 'Lluvia de balls',
        'petals' => 'Pétalos',
    ],

    // Rangos permitidos para los ajustes numéricos.
    'limits' => ['daily_limit' => [0, 50], 'pity_threshold' => [1, 100], 'streak_threshold' => [1, 10]],

    'rarities' => ['common', 'uncommon', 'rare', 'legendary'],
    'rarity_labels' => ['common' => 'Común', 'uncommon' => 'Poco común', 'rare' => 'Rara', 'legendary' => 'Legendaria'],

    // Base de imágenes del catálogo: {base}/{image_key}. Sin valor se muestra un marcador neutro.
    'image_base_url' => env('GAME_IMAGE_BASE_URL'),
];
