<?php

namespace App\Support\Ui;

/**
 * Resuelve el contador opcional de una pestaña o ítem del menú declarado en
 * config/modules.php como `'counter' => 'Clase@método'`. El método devuelve
 * `['text' => visible, 'label' => accesible]` o null. Una instancia por petición.
 */
class NavCounter
{
    /** @return array{text: string, label: string}|null */
    public static function resolve(?string $counter): ?array
    {
        if (! $counter || ! auth()->check() || ! str_contains($counter, '@')) {
            return null;
        }
        [$class, $method] = explode('@', $counter, 2);
        app()->scopedIf($class);

        return app($class)->{$method}();
    }
}
