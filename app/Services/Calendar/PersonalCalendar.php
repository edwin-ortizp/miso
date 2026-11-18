<?php

namespace App\Services\Calendar;

use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use App\Models\HealthEvent;
use App\Models\MenstrualCycle;
use App\Services\Experience\PersonalExperience;
use Carbon\Carbon;

class PersonalCalendar
{
    public function habits(int $user, Carbon $start, Carbon $end): array
    {
        $habits = HabitDefinition::where('user_id', $user)->where('show_in_calendar', true)
            ->get(['id', 'name', 'base_time', 'created_at'])->keyBy('id');
        if ($habits->isEmpty()) {
            return [];
        }

        $done = HabitCompletion::where('user_id', $user)->whereIn('habit_id', $habits->keys())
            ->where('completed', true)->where('date', '>=', $start->toDateString())
            ->where('date', '<', $end->toDateString())->get(['habit_id', 'date'])
            ->mapWithKeys(fn ($row) => [$row->habit_id.':'.$row->date->toDateString() => true]);

        $today = today()->toDateString();
        $events = [];
        foreach ($start->copy()->startOfDay()->daysUntil($end->copy()->subDay()) as $day) {
            $date = $day->toDateString();
            foreach ($habits as $habit) {
                $key = $habit->id.':'.$date;
                $completed = isset($done[$key]);
                if (! $completed && ($date < $today || $date < $habit->created_at?->toDateString())) {
                    continue;
                }
                $events[] = [
                    'id' => 'habit:'.$key,
                    'title' => ($completed ? '✓ ' : '○ ').$habit->name.' · '.($completed ? 'Completado' : 'Pendiente'),
                    'start' => $date.($habit->base_time ? 'T'.substr($habit->base_time, 0, 5).':00' : ''),
                    'allDay' => ! $habit->base_time, 'editable' => false,
                    'classNames' => ['md-calendar-habit'],
                    'extendedProps' => ['type' => 'habits', 'detailUrl' => route('habits', ['date' => $date])],
                ];
            }
        }

        return $events;
    }

    public function health(int $user, Carbon $start, Carbon $end): array
    {
        $events = [];
        $from = $start->toDateString();
        $until = $end->toDateString();
        $health = HealthEvent::where('user_id', $user)->where('event_date', '<', $until)
            ->where(fn ($query) => $query->where('event_date', '>=', $from)->orWhere('end_date', '>=', $from))
            ->get(['id', 'title', 'type', 'event_date', 'end_date', 'is_sensitive']);
        foreach ($health as $entry) {
            $events[] = $this->healthEvent('health:'.$entry->id,
                $entry->is_sensitive ? (HealthEvent::TYPES[$entry->type] ?? 'Evento de salud') : $entry->title,
                $entry->event_date, $entry->end_date ?? $entry->event_date, route('health'));
        }
        if (! app(PersonalExperience::class)->featureVisible('health', 'cycle', $user)) {
            return $events;
        }

        $today = today();
        $cycles = MenstrualCycle::where('user_id', $user)->where('started_on', '<', $until)
            ->where(fn ($query) => $query->where('ended_on', '>=', $from)
                ->when($from <= $today->toDateString(), fn ($query) => $query
                    ->orWhere(fn ($open) => $open->whereNull('ended_on')->where('started_on', '<=', $today->toDateString()))))
            ->get(['id', 'started_on', 'ended_on']);
        foreach ($cycles as $cycle) {
            $events[] = $this->healthEvent('cycle:'.$cycle->id, 'Menstruación',
                $cycle->started_on, $cycle->ended_on ?? $today, route('health.cycle', ['month' => $start->format('Y-m')]));
        }

        return $events;
    }

    private function healthEvent(string $id, string $title, Carbon $start, Carbon $end, string $url): array
    {
        return [
            'id' => $id, 'title' => $title, 'start' => $start->toDateString(),
            'end' => $end->copy()->addDay()->toDateString(), 'allDay' => true, 'editable' => false,
            'classNames' => ['md-calendar-health'],
            'extendedProps' => ['type' => 'health', 'detailUrl' => $url],
        ];
    }
}
