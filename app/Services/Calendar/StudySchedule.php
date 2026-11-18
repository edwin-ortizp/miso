<?php

namespace App\Services\Calendar;

use App\Models\StudyReservation;
use Carbon\Carbon;

class StudySchedule
{
    public function occurs(StudyReservation $series, Carbon $day): bool
    {
        $anchor = Carbon::parse($series->date)->startOfDay();
        $date = $day->toDateString();
        if ($date < $series->date || ($series->until && $date > $series->until)) {
            return false;
        }

        return match ($series->frequency) {
            'daily' => (int) $anchor->diffInDays($day) % $series->interval === 0,
            'weekly' => in_array($day->dayOfWeekIso, $series->weekdays ?: [$anchor->dayOfWeekIso], true)
                && (int) $anchor->startOfWeek(Carbon::MONDAY)->diffInWeeks($day->copy()->startOfWeek(Carbon::MONDAY)) % $series->interval === 0,
            default => $date === $series->date,
        };
    }

    public function events(int $user, Carbon $start, Carbon $end, ?string $formation = null, ?string $unit = null): array
    {
        $from = $start->toDateString();
        $until = $end->toDateString();
        $inRange = fn ($query, $column) => $query->where($column, '>=', $from)->where($column, '<', $until);
        $series = StudyReservation::where('user_id', $user)
            ->when($formation, fn ($query) => $query->where('formation_id', $formation))
            ->when($unit, fn ($query) => $query->where('unit_id', $unit))
            ->where(fn ($query) => $query
                ->where(fn ($base) => $base->where('date', '<', $until)
                    ->where(fn ($overlap) => $overlap->where('date', '>=', $from)
                        ->orWhere(fn ($repeat) => $repeat->where('frequency', '!=', 'none')
                            ->where(fn ($stop) => $stop->whereNull('until')->orWhere('until', '>=', $from)))))
                ->orWhereHas('exceptions', fn ($moved) => $inRange($moved, 'date')))
            ->with(['exceptions' => fn ($query) => $query->where(fn ($dates) => $inRange($dates, 'original_date')
                ->orWhere(fn ($moved) => $inRange($moved, 'date')))])
            ->get();

        $events = [];
        foreach ($series as $reservation) {
            $exceptions = $reservation->exceptions->keyBy('original_date');
            for ($day = $start->copy()->startOfDay(); $day->lt($end); $day->addDay()) {
                $date = $day->toDateString();
                if (! $exceptions->has($date) && $this->occurs($reservation, $day)) {
                    $events[] = $this->event($reservation, $date, $date);
                }
            }
            foreach ($exceptions as $original => $exception) {
                if ($exception->date !== null && $exception->date >= $from && $exception->date < $until) {
                    $events[] = $this->event($reservation, $original, $exception->date, $exception->changes ?? []);
                }
            }
        }

        return $events;
    }

    private function event(StudyReservation $series, string $original, string $date, array $changes = []): array
    {
        return [
            'id' => 'reservation:'.$series->id.':'.$original,
            'title' => $changes['title'] ?? $series->title,
            'start' => $date.'T'.substr($changes['starts_at'] ?? $series->starts_at, 0, 5).':00',
            'end' => $date.'T'.substr($changes['ends_at'] ?? $series->ends_at, 0, 5).':00',
            'allDay' => false, 'editable' => true, 'durationEditable' => false,
            'classNames' => ['md-calendar-reservation'],
            'extendedProps' => ['type' => 'reservations', 'reservation' => $series->id, 'occurrence' => $original],
        ];
    }

    public function move(StudyReservation $series, string $occurrence, Carbon $start, Carbon $end): void
    {
        abort_unless($this->occurs($series, Carbon::parse($occurrence)) && $start->isSameDay($end) && $start->lt($end), 422);
        $exception = $series->exceptions()->where('original_date', $occurrence)->first();
        abort_if($exception && $exception->date === null, 409);
        $series->exceptions()->updateOrCreate(['original_date' => $occurrence], [
            'date' => $start->toDateString(),
            'changes' => array_replace($exception?->changes ?? [], ['starts_at' => $start->format('H:i'), 'ends_at' => $end->format('H:i')]),
        ]);
    }
}
