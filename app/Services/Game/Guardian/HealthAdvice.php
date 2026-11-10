<?php

namespace App\Services\Game\Guardian;

use App\Models\HealthEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/** Consejos de Salud (CHG-057): seguimientos, registros pendientes, próximo cuidado y comparativa del año. 3 consultas. */
class HealthAdvice
{
    use Advice;

    /** @return list<array{key: string, html: string, weight: int}> */
    public function candidates(CarbonInterface $now): array
    {
        $out = [];
        $today = $now->copy()->startOfDay();

        $active = HealthEvent::query()->whereIn('type', HealthEvent::EVOLUTION_TYPES)->whereNull('end_date')
            ->whereDate('event_date', '<=', $today)->withMax('logs', 'date')
            ->orderByDesc('event_date')->limit(3)->get(['id', 'title', 'event_date']);
        foreach ($active as $event) {
            $day = (int) $event->event_date->copy()->startOfDay()->diffInDays($today) + 1;
            $out[] = $this->candidate('health', 'followup', ['event' => $event->title, 'day' => $day], 2, ['event']);

            $last = $event->logs_max_date ? \Illuminate\Support\Carbon::parse($event->logs_max_date) : $event->event_date;
            $days = (int) $last->copy()->startOfDay()->diffInDays($today);
            if ($days >= 3) {
                $out[] = $this->candidate('health', 'unlogged', ['days' => $days, 'event' => $event->title], 3, ['event']);
            }
        }

        $next = HealthEvent::query()->whereBetween('event_date', [$today, $today->copy()->addDays(2)->endOfDay()])
            ->whereNotIn('type', HealthEvent::EVOLUTION_TYPES)->orderBy('event_date')->first(['id', 'title', 'event_date']);
        if ($next) {
            $date = $next->event_date;
            $when = $date->isSameDay($today) ? 'hoy' : ($date->isSameDay($today->copy()->addDay()) ? 'mañana' : 'el '.$date->translatedFormat('l'));
            $out[] = $this->candidate('health', 'next', ['event' => $next->title, 'when' => $when], 3, ['event']);
        }

        $months = HealthEvent::query()->whereBetween('event_date', [$now->copy()->startOfYear(), $now->copy()->endOfMonth()])
            ->pluck('event_date')->countBy(fn ($d) => (int) $d->month);
        $current = (int) ($months[$now->month] ?? 0);
        $peakMonth = $months->sortDesc()->keys()->first();
        if ($peakMonth && $peakMonth !== $now->month && $months[$peakMonth] > $current) {
            $name = fn (int $m) => Str::ucfirst($now->copy()->month($m)->translatedFormat('F'));
            $out[] = $this->candidate('health', 'calm', [
                'month' => $name($now->month), 'count' => $current,
                'peak' => Str::lcfirst($name($peakMonth)), 'peak_count' => $months[$peakMonth],
            ], 1);
        }

        return $out;
    }
}
