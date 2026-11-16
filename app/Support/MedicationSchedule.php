<?php

namespace App\Support;

use App\Models\Medication;
use App\Models\MedicationIntake;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * CHG-129: calcula las tomas esperadas de un día a partir de la programación del medicamento.
 * Las tomas esperadas no se guardan por adelantado: se derivan aquí y solo se persiste lo que ocurre.
 * El día y las horas se interpretan en la zona de la aplicación (America/Bogota).
 */
final class MedicationSchedule
{
    /**
     * Horas esperadas de un medicamento en un día.
     *
     * @return Collection<int, Carbon>
     */
    public static function slots(Medication $medication, CarbonInterface $day): Collection
    {
        $date = Carbon::parse($day->toDateString(), config('app.timezone'))->startOfDay();

        if (! $medication->is_active
            || $date->toDateString() < $medication->starts_on->toDateString()
            || ($medication->ends_on !== null && $date->toDateString() > $medication->ends_on->toDateString())) {
            return collect();
        }

        $times = collect($medication->times ?? [])
            ->filter(fn ($time) => is_string($time) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time))
            ->unique()->sort()->values();

        return match ($medication->frequency_type) {
            'every_hours' => self::everyHours($medication, $date, $times->first() ?? '08:00'),
            'weekdays' => in_array($date->dayOfWeek, array_map('intval', $medication->weekdays ?? []), true)
                ? self::atTimes($date, $times) : collect(),
            default => self::atTimes($date, $times),
        };
    }

    /**
     * Tomas del día del usuario autenticado cruzadas con lo registrado.
     * Estados: pending (aún no llega la hora), overdue (la hora pasó), taken, skipped.
     *
     * @return Collection<int, array{medication: Medication, scheduled_for: Carbon, intake: ?MedicationIntake, state: string}>
     */
    public static function forDay(CarbonInterface $day, ?CarbonInterface $now = null, bool $includeSensitive = true): Collection
    {
        $now = $now ? Carbon::instance($now)->setTimezone(config('app.timezone')) : now();
        $start = Carbon::parse($day->toDateString(), config('app.timezone'))->startOfDay();
        $end = $start->copy()->endOfDay();

        $intakes = MedicationIntake::query()
            ->whereNotNull('medication_id')
            ->whereBetween('scheduled_for', [$start, $end])
            ->get()
            ->keyBy(fn (MedicationIntake $i) => $i->medication_id.'|'.$i->scheduled_for->format('Y-m-d H:i'));

        $medications = Medication::query()
            ->where('is_active', true)
            ->when(! $includeSensitive, fn ($q) => $q->where('is_sensitive', false))
            ->orderBy('name')->get();

        return $medications
            ->flatMap(fn (Medication $medication) => self::slots($medication, $start)->map(function (Carbon $slot) use ($medication, $intakes, $now) {
                $intake = $intakes->get($medication->id.'|'.$slot->format('Y-m-d H:i'));

                return [
                    'medication' => $medication,
                    'scheduled_for' => $slot,
                    'intake' => $intake,
                    'state' => $intake ? ($intake->status === 'skipped' ? 'skipped' : 'taken') : ($slot->lte($now) ? 'overdue' : 'pending'),
                ];
            }))
            ->sortBy(fn (array $row) => $row['scheduled_for']->timestamp.$row['medication']->name)
            ->values();
    }

    /** @return Collection<int, Carbon> */
    private static function atTimes(Carbon $date, Collection $times): Collection
    {
        return $times->map(fn (string $time) => $date->copy()->setTimeFromTimeString($time))->values();
    }

    /** @return Collection<int, Carbon> */
    private static function everyHours(Medication $medication, Carbon $date, string $first): Collection
    {
        $interval = max(1, (int) $medication->interval_hours);
        $slot = $date->copy()->setTimeFromTimeString($first);
        $slots = collect();
        while ($slot->toDateString() === $date->toDateString()) {
            $slots->push($slot->copy());
            $slot->addHours($interval);
        }

        return $slots;
    }
}
