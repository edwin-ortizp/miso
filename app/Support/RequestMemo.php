<?php

namespace App\Support;

/**
 * Memoria de una sola petición (CHG-095) para lecturas idénticas que varios
 * componentes de la misma página repiten (catálogo de categorías de tareas).
 * Se registra como `scoped` y se vacía cuando cambia cualquiera de sus orígenes
 * (ver AppServiceProvider), así que nunca sobrevive a una escritura.
 */
class RequestMemo
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function remember(string $key, callable $compute): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : ($this->values[$key] = $compute());
    }

    public function flush(): void
    {
        $this->values = [];
    }
}
