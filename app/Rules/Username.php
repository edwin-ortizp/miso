<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Nombre de usuario social: 3 a 20 caracteres en minúsculas, números o guion bajo. */
class Username implements ValidationRule
{
    public const RESERVED = ['admin', 'administrador', 'miso', 'soporte', 'support', 'root', 'sistema', 'system', 'ayuda', 'help', 'api'];

    public static function normalize(string $value): string
    {
        return mb_strtolower(ltrim(trim($value), '@'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;
        if (! preg_match('/^[a-z0-9_]{3,20}$/', $value)) {
            $fail('Usa de 3 a 20 letras minúsculas, números o guion bajo.');
        } elseif (in_array($value, self::RESERVED, true)) {
            $fail('Ese nombre de usuario no está disponible.');
        }
    }
}
