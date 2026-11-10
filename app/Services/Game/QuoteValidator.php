<?php

namespace App\Services\Game;

/**
 * Valida las frases que propone el director (CHG-043): cantidad, longitud,
 * textos únicos y sin palabras de culpa. Todo o nada.
 */
class QuoteValidator
{
    /**
     * @param  mixed  $quotes
     * @return array{quotes: array<int, string>, errors: array<int, string>}
     */
    public function validate(mixed $quotes): array
    {
        $rules = config('game.quotes');
        if (! is_array($quotes) || ! array_is_list($quotes)) {
            return ['quotes' => [], 'errors' => ['quotes debe ser una lista de textos.']];
        }

        $errors = [];
        $count = count($quotes);
        if ($count < $rules['min'] || $count > $rules['max']) {
            $errors[] = "Envía entre {$rules['min']} y {$rules['max']} frases (llegaron {$count}).";
        }

        $clean = [];
        foreach ($quotes as $i => $quote) {
            $n = $i + 1;
            if (! is_string($quote) || trim($quote) === '') {
                $errors[] = "Frase {$n}: debe ser un texto no vacío.";

                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $quote));
            if (mb_strlen($text) > $rules['max_length']) {
                $errors[] = "Frase {$n}: máximo {$rules['max_length']} caracteres.";
            }
            if ($word = MissionProposalValidator::penaltyWord($text)) {
                $errors[] = "Frase {$n}: no uses \"{$word}\"; las frases son cálidas y sin culpa.";
            }
            if (in_array(mb_strtolower($text), array_map('mb_strtolower', $clean), true)) {
                $errors[] = "Frase {$n}: está repetida.";
            }
            $clean[] = $text;
        }

        return ['quotes' => $errors === [] ? $clean : [], 'errors' => $errors];
    }
}
