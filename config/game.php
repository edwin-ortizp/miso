<?php

return [
    'description_cache_store' => 'file',
    // Valores por defecto; cada usuario los ajusta en Colección → Ajustes (ModuleSetting módulo "game").
    'defaults' => [
        'enabled' => true,
        'sources' => ['tasks' => true, 'habits' => true, 'focus' => true, 'goals' => true, 'health' => true],
        'daily_limit' => 5,
        'pity_threshold' => 10,
        'rarity_weights' => ['common' => 60, 'uncommon' => 30, 'rare' => 9, 'legendary' => 1],
        'director_enabled' => true,
        // Celebraciones (CHG-034): activas y cuáles del catálogo pueden salir.
        'celebrations' => true,
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
        'propose_window_days' => 7,
        'history_weeks' => 8,
    ],

    // Poder (CHG-025): round(total_estadísticas_base × (1 + (nivel − 1) × factor)). Sin estadísticas usa la base.
    'power_level_factor' => 0.05,
    'power_default_base' => 300,
    // Tipos con color propio en _game.css (clase game-type--{slug}); los demás usan el neutro.
    'type_palette' => ['acero', 'agua', 'bicho', 'dragon', 'electrico', 'fantasma', 'fuego', 'hada', 'hielo', 'lucha', 'normal', 'planta', 'psiquico', 'roca', 'siniestro', 'tierra', 'veneno', 'volador'],

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
