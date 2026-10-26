<?php

namespace App\Support\Ui;

use InvalidArgumentException;

final class CatalogColors
{
    public const OPTIONS = [
        'gray' => 'Gris', 'blue' => 'Azul', 'indigo' => 'Índigo', 'violet' => 'Violeta',
        'pink' => 'Rosa', 'red' => 'Rojo', 'orange' => 'Naranja', 'yellow' => 'Amarillo',
        'green' => 'Verde', 'teal' => 'Turquesa',
    ];

    public static function validate(?string $color): ?string
    {
        if ($color !== null && ! array_key_exists($color, self::OPTIONS)) {
            throw new InvalidArgumentException('Elige un color de la paleta.');
        }

        return $color;
    }
}
