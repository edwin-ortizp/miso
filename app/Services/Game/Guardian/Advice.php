<?php

namespace App\Services\Game\Guardian;

/** Ayudas compartidas por los proveedores de consejos (CHG-057). */
trait Advice
{
    /**
     * Candidato a partir de una plantilla de config. Los valores se escapan;
     * los de `$strong` (nombres de tareas, eventos o categorías) van en negrita.
     *
     * @param  array<string, string|int>  $vars
     * @param  list<string>  $strong
     * @return array{key: string, html: string, weight: int}
     */
    protected function candidate(string $module, string $key, array $vars, int $weight, array $strong = []): array
    {
        $html = e((string) config("game.guardian_advice.{$module}.{$key}"));
        foreach ($vars as $name => $value) {
            $value = e((string) $value);
            $html = str_replace('{'.$name.'}', in_array($name, $strong, true) ? "<strong>{$value}</strong>" : $value, $html);
        }

        return ['key' => $key, 'html' => $html, 'weight' => $weight];
    }

    protected static function minutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h ? $h.' h'.($m ? ' '.$m.' min' : '') : $m.' min';
    }
}
