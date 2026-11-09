<?php

namespace App\Services\Game;

use App\Models\Game\GameSpecies;

/**
 * Resuelve la imagen de una especie a partir de GAME_IMAGE_BASE_URL. Cambiar la
 * fuente de imágenes no toca la lógica del juego. Sin base devuelve null y la
 * vista muestra un marcador neutro.
 */
class SpeciesImageProvider
{
    public function url(GameSpecies $species): ?string
    {
        $base = rtrim((string) config('game.image_base_url'), '/');
        if ($base === '' || ! $species->image_key) {
            return null;
        }

        if (preg_match('#^https?://#i', $species->image_key)) {
            return $species->image_key;
        }

        return $base.'/'.ltrim($species->image_key, '/');
    }
}
