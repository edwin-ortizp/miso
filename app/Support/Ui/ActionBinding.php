<?php

namespace App\Support\Ui;

/**
 * Enlace de una acción de creación del módulo.
 *
 * El FAB y el chat del compañero muestran las mismas acciones, pero viven en
 * contextos distintos: el FAB se teletransporta con `@teleport` y conserva el
 * componente Livewire, mientras que el host del chat se mueve a `<body>` y
 * pierde el ancestro `[wire:id]`. Por eso una acción de tipo método se pinta
 * con `wire:click` en el FAB y con la referencia capturada (`wire.metodo()`)
 * en el chat; con `wire:click` el chat falla en silencio.
 */
class ActionBinding
{
    public const HREF = 'href';

    public const EVENT = 'event';

    public const METHOD = 'method';

    /** Tipo de enlace: navegación, evento del navegador o método Livewire. */
    public static function kind(array $item): string
    {
        if (! empty($item['href'])) {
            return self::HREF;
        }

        if (! empty($item['event'])) {
            return self::EVENT;
        }

        return self::METHOD;
    }

    /** Etiqueta HTML de la acción: enlace solo cuando navega. */
    public static function tag(array $item): string
    {
        return self::kind($item) === self::HREF ? 'a' : 'button';
    }

    /** Llamada del método Livewire lista para evaluar, con o sin paréntesis. */
    public static function call(array $item): string
    {
        $action = (string) ($item['action'] ?? '');

        if ($action === '') {
            return '';
        }

        return str_contains($action, '(') ? $action : $action.'()';
    }

    /** Atributos de la acción dentro del FAB canónico. */
    public static function fab(array $item): string
    {
        return match (self::kind($item)) {
            self::HREF => 'href="'.e($item['href']).'"',
            self::EVENT => 'x-on:click="'.e(self::dispatch($item)).'"',
            self::METHOD => 'wire:click="'.e($item['action'] ?? '').'"',
        };
    }

    /**
     * Atributos del chip del chat. La acción de método se resuelve dentro de
     * `act()` con la referencia Livewire capturada por el controlador; `act()`
     * avisa y devuelve `false` si la referencia no sirve, y así el chat no se
     * cierra dejando al usuario sin formulario.
     */
    public static function chat(array $item): string
    {
        return match (self::kind($item)) {
            self::HREF => 'href="'.e($item['href']).'" @click="close({ restoreFocus: false })"',
            self::EVENT => 'x-on:click="'.e(self::dispatch($item)).'; close({ restoreFocus: false })"',
            self::METHOD => 'x-on:click="act(() => wire.'.e(self::call($item)).') && close({ restoreFocus: false })"',
        };
    }

    /** Expresión que despacha el evento del navegador con su detalle. */
    private static function dispatch(array $item): string
    {
        return '$dispatch('.json_encode($item['event']).', '.json_encode($item['detail'] ?? new \stdClass).')';
    }
}
