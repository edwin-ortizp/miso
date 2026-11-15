<?php

namespace App\Services\Experience;

/** Small, deterministic ideas: choosing one never creates a record. */
class OnboardingChoices
{
    public const CONTEXTS = [
        'work' => ['label' => 'Trabajo', 'icon' => 'bi-briefcase', 'hint' => 'Responsabilidades laborales y tiempo para ti.'],
        'study' => ['label' => 'Estudio', 'icon' => 'bi-mortarboard', 'hint' => 'Clases, aprendizaje y otras actividades.'],
        'care' => ['label' => 'Cuido del hogar o de otras personas', 'icon' => 'bi-house-heart', 'hint' => 'También hay un espacio para tus propias prácticas.'],
        'none' => ['label' => 'Ninguna de estas', 'icon' => 'bi-compass', 'hint' => 'Podemos empezar sin ponerle una etiqueta a tu día.'],
    ];

    public const NEEDS = [
        'care' => ['label' => 'Cuidarme en lo cotidiano', 'icon' => 'bi-heart', 'hint' => 'Hacerle un espacio a una práctica pequeña de cuidado.'],
        'organize' => ['label' => 'Organizar mi día', 'icon' => 'bi-list-check', 'hint' => 'Tener a mano lo que quiero hacer.'],
        'learn' => ['label' => 'Aprender o estudiar', 'icon' => 'bi-book', 'hint' => 'Volver cada día a algo que quiero aprender.'],
        'personal' => ['label' => 'Avanzar en algo personal', 'icon' => 'bi-stars', 'hint' => 'Dedicar un momento a algo que me importa.'],
        'unsure' => ['label' => 'Todavía no sé', 'icon' => 'bi-compass', 'hint' => 'Ver ideas sencillas y elegir una para empezar.'],
    ];

    public static function habits(): array
    {
        return [
            'water' => ['name' => 'Tomar agua', 'time' => 'morning', 'need' => 'care', 'icon' => '💧', 'hint' => 'Por la mañana, como parte de tu rutina.', 'contexts' => []],
            'pause' => ['name' => 'Hacer una pausa breve', 'time' => 'anytime', 'need' => 'care', 'icon' => '🌿', 'hint' => 'Un momento para hacer una pausa en tu día.', 'contexts' => ['work', 'care']],
            'rest' => ['name' => 'Dedicar unos minutos a descansar', 'time' => 'night', 'need' => 'care', 'icon' => '🌙', 'hint' => 'Reservar un rato tranquilo al terminar el día.', 'contexts' => []],
            'pending' => ['name' => 'Revisar mis pendientes', 'time' => 'morning', 'need' => 'organize', 'icon' => '📝', 'hint' => 'Mirar qué tienes por hacer antes de empezar.', 'contexts' => ['work']],
            'tomorrow' => ['name' => 'Preparar lo necesario para mañana', 'time' => 'night', 'need' => 'organize', 'icon' => '🎒', 'hint' => 'Dejar a mano lo que usarás al día siguiente.', 'contexts' => ['study', 'care']],
            'space' => ['name' => 'Ordenar un espacio pequeño', 'time' => 'anytime', 'need' => 'organize', 'icon' => '🧺', 'hint' => 'Una mesa, un cajón o un rincón que tú elijas.', 'contexts' => []],
            'read' => ['name' => 'Leer unos minutos', 'time' => 'anytime', 'need' => 'learn', 'icon' => '📖', 'hint' => 'Un libro o un tema que te interese.', 'contexts' => []],
            'review' => ['name' => 'Repasar algo aprendido', 'time' => 'afternoon', 'need' => 'learn', 'icon' => '📚', 'hint' => 'Volver a una idea o a tus apuntes.', 'contexts' => ['study']],
            'skill' => ['name' => 'Practicar una habilidad', 'time' => 'anytime', 'need' => 'learn', 'icon' => '🌱', 'hint' => 'Un pequeño ejercicio de algo que estés aprendiendo.', 'contexts' => []],
            'project' => ['name' => 'Dedicar unos minutos a mi proyecto', 'time' => 'anytime', 'need' => 'personal', 'icon' => '✨', 'hint' => 'Avanzar un poco, sin tener que resolverlo todo.', 'contexts' => ['work']],
            'enjoy' => ['name' => 'Practicar una actividad que disfruto', 'time' => 'anytime', 'need' => 'personal', 'icon' => '🎨', 'hint' => 'Hacerle un espacio a algo que te gusta.', 'contexts' => ['care']],
            'next' => ['name' => 'Revisar mi siguiente paso', 'time' => 'morning', 'need' => 'personal', 'icon' => '🧭', 'hint' => 'Elegir una acción pequeña para algo personal.', 'contexts' => []],
        ];
    }

    public static function suggestions(string $need, array $contexts, bool $all = false): array
    {
        $habits = self::habits();
        $primary = array_filter($habits, fn ($habit, $key) => $need === 'unsure'
            ? in_array($key, ['water', 'pending', 'read'], true) : $habit['need'] === $need, ARRAY_FILTER_USE_BOTH);
        // Stable ordering on ties keeps the same ideas in the same place after a render.
        uasort($primary, fn ($a, $b) => (bool) array_intersect($b['contexts'], $contexts) <=> (bool) array_intersect($a['contexts'], $contexts));

        return $all ? $primary + $habits : $primary;
    }

    public static function legacyContext(array $contexts): string
    {
        return in_array('work', $contexts, true)
            ? (in_array('study', $contexts, true) ? 'both' : 'work')
            : (in_array('study', $contexts, true) ? 'study' : 'other');
    }
}
