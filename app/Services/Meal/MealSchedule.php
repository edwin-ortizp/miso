<?php

namespace App\Services\Meal;

use App\Models\MealPlanEntry;
use App\Models\ModuleSetting;
use Carbon\Carbon;

class MealSchedule
{
    public const TYPES = ['desayuno' => 'Desayuno', 'comida' => 'Onces (mañana)', 'almuerzo' => 'Almuerzo', 'merienda' => 'Merienda (tarde)', 'cena' => 'Cena'];

    public static function defaults(): array
    {
        $slots = [];
        foreach (['desayuno' => ['07:00', 30], 'comida' => ['10:00', 15], 'almuerzo' => ['12:30', 60], 'merienda' => ['16:00', 30], 'cena' => ['19:00', 60]] as $type => [$time, $duration]) {
            $slots[$type] = ['time' => $time, 'duration' => $duration];
        }
        return ['weekday' => $slots, 'weekend' => $slots];
    }

    public function profiles(int $userId): array
    {
        $settings = ModuleSetting::where('user_id', $userId)->where('module', 'meals')->value('settings') ?? [];
        return array_replace_recursive(self::defaults(), $settings['schedules'] ?? []);
    }

    public function resolve(string $date, string $type, array $profiles, ?string $time = null, ?int $duration = null): array
    {
        $day = Carbon::parse($date, config('app.timezone'));
        $slot = $profiles[$day->isWeekend() ? 'weekend' : 'weekday'][$type];
        $start = $day->setTimeFromTimeString($time ?? $slot['time']);
        return ['start' => $start, 'end' => $start->copy()->addMinutes($duration ?? $slot['duration'])];
    }

    public function events(int $userId, Carbon $start, Carbon $end): array
    {
        $profiles = $this->profiles($userId);
        return MealPlanEntry::where('user_id', $userId)
            ->where('date', '>=', $start->copy()->timezone(config('app.timezone'))->subDay()->toDateString())
            ->where('date', '<=', $end->copy()->timezone(config('app.timezone'))->toDateString())
            ->with(['items.recipe', 'items.preparation'])->get()->map(function ($entry) use ($profiles, $start, $end) {
                $times = $this->resolve($entry->date->toDateString(), $entry->meal_type, $profiles, $entry->scheduled_time, $entry->duration_minutes);
                if ($times['start']->gte($end) || $times['end']->lte($start)) return null;
                $summary = $entry->items->map(fn ($item) => $item->name ?: ($item->recipe?->name ?? $item->preparation?->name))->filter()->implode(', ');
                $status = $entry->status === 'consumed' ? ($entry->consumption_mode === 'outside' ? 'Consumida fuera' : 'Consumida') : 'Planeada';
                return [
                    'id' => 'meal:'.$entry->id,
                    'title' => self::TYPES[$entry->meal_type].' · '.($summary ?: 'Sin platos').' · '.$status,
                    'start' => $times['start']->toIso8601String(), 'end' => $times['end']->toIso8601String(),
                    'allDay' => false, 'editable' => false, 'startEditable' => false, 'durationEditable' => false,
                    'classNames' => ['md-calendar-meal'],
                    'extendedProps' => ['type' => 'meals', 'summary' => $summary, 'status' => $entry->status, 'statusLabel' => $status, 'timeLabel' => $times['start']->format('d/m H:i').' – '.$times['end']->format('d/m H:i'),
                        'detailUrl' => route('meals.weekly', ['date' => $entry->date->toDateString(), 'meal' => $entry->id])],
                ];
            })->filter()->values()->all();
    }
}
