<?php

namespace App\Support\Habits\Actions;

use App\Actions\LogDrink;
use App\Models\DrinkType;
use App\Models\HabitDefinition;
use App\Support\Habits\HabitAction;
use App\Support\Habits\HabitActionField;
use App\Support\Habits\HabitActionResult;
use Carbon\CarbonInterface;

class LogWaterIntakeAction extends HabitAction
{
    public static function key(): string
    {
        return 'water.log_intake';
    }

    public static function module(): string
    {
        return 'water';
    }

    public static function label(): string
    {
        return 'Registrar una toma de líquido';
    }

    public static function icon(): string
    {
        return 'bi-cup-straw';
    }

    public function fields(): array
    {
        $types = app(\App\Services\Catalog\CatalogPicker::class)->ordered('drinks')->pluck('name', 'id')->all();

        return [
            HabitActionField::select('drink_type_id', 'Bebida', $types, ['required', 'string'], array_key_first($types)),
            HabitActionField::number('amount_ml', 'Cantidad (ml)', ['required', 'integer', 'min:1', 'max:5000'], 250),
        ];
    }

    public function execute(HabitDefinition $habit, CarbonInterface $date, array $config, array $input): HabitActionResult
    {
        $values = $this->merge($config, $input);
        $type = DrinkType::query()->find($values['drink_type_id'] ?? null);

        if (! $type) {
            throw new \RuntimeException('La bebida configurada ya no existe en tu catálogo.');
        }

        $amount = (int) ($values['amount_ml'] ?? 0);
        $log = LogDrink::handle($type, $date->toDateString(), $amount);

        return new HabitActionResult($log, "También registré {$amount} ml de {$type->name}.");
    }
}
