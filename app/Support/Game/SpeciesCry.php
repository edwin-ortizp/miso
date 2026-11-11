<?php

namespace App\Support\Game;

use Illuminate\Support\Str;

/**
 * Grito (onomatopeya) de un Pokémon a partir de su nombre (CHG-059):
 * las primeras sílabas, repetidas. Pikachu → "¡Pika-pika!", Ralts → "¡Ral-ral!".
 */
class SpeciesCry
{
    public static function for(string $name): string
    {
        $word = preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($name)));
        if ($word === '') {
            return '';
        }

        $isVowel = fn (int $i) => $i < strlen($word) && str_contains('aeiouy', $word[$i]);
        $root = '';
        $i = 0;
        $first = true;
        while ($i < strlen($word) && strlen($root) < 3) {
            while ($i < strlen($word) && ! $isVowel($i)) { $root .= $word[$i++]; } // ataque
            while ($isVowel($i)) { $root .= $word[$i++]; }                          // núcleo
            if ($first && $i < strlen($word) && ! $isVowel($i) && ! $isVowel($i + 1)) { // coda si no abre otra sílaba
                $root .= $word[$i++];
            }
            $first = false;
        }
        $root = substr($root, 0, 5);

        return '¡'.Str::ucfirst($root).'-'.$root.'!';
    }
}
