<?php

namespace App\Support\Habits;

use App\Models\HabitDefinition;
use Carbon\CarbonInterface;

/**
 * Lo que un módulo sabe hacer cuando se completa un hábito.
 *
 * Registrar un módulo nuevo es crear una subclase y añadirla a config/habit_actions.php;
 * ni el servicio de hábitos ni la pantalla de ajustes necesitan saber que existe.
 */
abstract class HabitAction
{
    /** Identificador estable que se guarda en habit_actions.action_key. */
    abstract public static function key(): string;

    /** Clave del módulo en config/modules.php, para agrupar el selector. */
    abstract public static function module(): string;

    abstract public static function label(): string;

    abstract public static function icon(): string;

    /**
     * Campos del formulario, resueltos contra el catálogo del usuario autenticado.
     *
     * @return array<int, HabitActionField>
     */
    abstract public function fields(): array;

    /**
     * @param  array<string, mixed>  $config  Lo configurado en ajustes.
     * @param  array<string, mixed>  $input  Lo respondido al completar, en modo «prompt».
     */
    abstract public function execute(HabitDefinition $habit, CarbonInterface $date, array $config, array $input): HabitActionResult;

    /** Etiqueta del módulo tal como aparece en la navegación. */
    public static function moduleLabel(): string
    {
        return config('modules.'.static::module().'.title', static::module());
    }

    /**
     * Los campos que se preguntan al completar cuando el modo es «prompt».
     *
     * @return array<int, HabitActionField>
     */
    public function promptFields(array $config = []): array
    {
        return array_values(array_filter($this->fields(), fn (HabitActionField $field) => $field->ask));
    }

    /** @return array<string, mixed> Valores iniciales del formulario de ajustes. */
    public function defaults(): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn (HabitActionField $field) => [$field->key => $field->default])
            ->all();
    }

    /**
     * Combina lo configurado con lo respondido. Lo respondido manda, pero un campo
     * omitido cae al valor fijo de la configuración.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function merge(array $config, array $input): array
    {
        $values = $config;

        foreach ($input as $key => $value) {
            if ($value !== null && $value !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
