<?php

namespace App\Support\Game;

use Illuminate\Container\Attributes\Scoped;

/**
 * Bandera por petición (CHG-041): se enciende cuando se crea un evento del
 * juego del usuario autenticado. `AddGameEventHeader` la convierte en la
 * cabecera `X-Game-Event` para que el navegador solo consulte novedades
 * cuando las hubo.
 */
#[Scoped]
class RequestGameActivity
{
    private bool $touched = false;

    public function touch(): void
    {
        $this->touched = true;
    }

    public function touched(): bool
    {
        return $this->touched;
    }
}
