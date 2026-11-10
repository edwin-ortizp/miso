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
    'module_guardians' => [
        'tasks' => ['psiquico', 'acero'],
        'health' => ['hada', 'normal'],
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
    'limits' => ['daily_limit' => [0, 50], 'pity_threshold' => [1, 100]],

    'rarities' => ['common', 'uncommon', 'rare', 'legendary'],
    'rarity_labels' => ['common' => 'Común', 'uncommon' => 'Poco común', 'rare' => 'Rara', 'legendary' => 'Legendaria'],

    // Base de imágenes del catálogo: {base}/{image_key}. Sin valor se muestra un marcador neutro.
    'image_base_url' => env('GAME_IMAGE_BASE_URL'),
];
