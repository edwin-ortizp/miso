<?php

namespace App\Services\Game\Guardian;

use App\Models\HealthEvent;
use App\Models\SleepLog;
use Illuminate\Support\Collection;

/** Presentation data only: callers supply records already scoped to the current user. */
final class HealthContext
{
    public static function make(string $section, string $title, array $facts, string $empty): array
    {
        return compact('section', 'title', 'facts', 'empty');
    }

    public static function measurements(Collection $latest): array
    {
        return self::make('measurements', 'Últimas mediciones', $latest->map(fn ($m) => $m->typeLabel().': '.$m->displayValue().' '.$m->unit.' · '.$m->measured_at->format('d/m/Y H:i')
        )->all(), 'Aún no hay mediciones registradas.');
    }

    public static function medications(Collection $today): array
    {
        $facts = [];
        if ($today->isNotEmpty()) {
            $facts[] = 'Hoy: '.$today->where('state', 'taken')->count().' tomadas · '
                .$today->whereIn('state', ['pending', 'overdue'])->count().' pendientes · '
                .$today->where('state', 'skipped')->count().' omitidas.';
            $next = $today->firstWhere('state', 'pending');
            if ($next) {
                $facts[] = 'Próxima toma programada de hoy: '.$next['medication']->name.' · '.$next['scheduled_for']->format('H:i').'.';
            }
        }

        return self::make('medications', 'Tomas de hoy', $facts, 'No hay tomas programadas hoy.');
    }

    public static function sleep(?SleepLog $last, int $goal, array $week): array
    {
        $facts = ['Meta personal: '.SleepLog::formatMinutes($goal).'.'];
        if ($last) {
            $facts[] = 'Último descanso: '.SleepLog::formatMinutes($last->durationMinutes()).' · '.$last->woke_at->format('d/m/Y').'.';
        }
        $facts[] = $week['nights'] > 0
            ? 'Últimos 7 días: '.$week['nights'].' registros · promedio '.SleepLog::formatMinutes($week['average_minutes']).'.'
            : 'No hay registros de sueño en los últimos 7 días.';
        if (! $last) {
            $facts[] = 'Aún no hay descansos registrados.';
        }

        return self::make('sleep', 'Tu descanso', $facts, 'Aún no hay descansos registrados.');
    }

    public static function cycle(Collection $cycles, array $summary, $lastLog): array
    {
        $open = $cycles->first(fn ($c) => $c->isOpen());
        $facts = [];
        if ($cycles->isNotEmpty()) {
            $facts[] = $open ? 'Periodo en curso desde el '.$open->started_on->format('d/m/Y').'.' : 'No hay un periodo en curso.';
            $facts[] = 'Último inicio registrado: '.$summary['last_start']->format('d/m/Y').'.';
        }
        if ($lastLog) {
            $facts[] = 'Último registro diario: '.$lastLog->date->format('d/m/Y').'.';
        }
        $facts[] = $summary['window_from']
            ? 'Tiende a empezar entre el '.$summary['window_from']->format('d/m/Y').' y el '.$summary['window_to']->format('d/m/Y').' (según '.$summary['starts'].' ciclos). Es una estimación: tu ciclo puede variar.'
            : 'Aún no hay datos suficientes para estimar tu próximo periodo.';

        return self::make('cycle', 'Tu ciclo', $facts, 'Aún no hay registros de ciclo.');
    }

    public static function trends(Collection $charts, ?array $sleep, int $days): array
    {
        $facts = $charts->map(fn ($c) => $c['definition']['label'].': '.$c['summary']['count'].' '.($c['summary']['count'] === 1 ? 'registro' : 'registros').' en '.$c['summary']['days'].' '.($c['summary']['days'] === 1 ? 'día' : 'días').' con datos.')->all();
        if ($sleep) {
            $facts[] = 'Sueño: '.$sleep['summary']['count'].' registros en '.$sleep['summary']['days'].' días con datos.';
        }

        return self::make('trends', 'Últimos '.$days.' días', $facts, 'Aún no hay datos para mostrar tendencias en este periodo.');
    }

    public static function body(Collection $zones, int $total, ?string $selected): array
    {
        $zone = $selected ? $zones->get($selected) : null;
        $facts = $selected
            ? [$zone ? $zone['label'].': '.$zone['count'].' eventos · Último: '.$zone['last'] : (HealthEvent::BODY_AREAS[$selected] ?? 'Zona').': sin eventos en el periodo seleccionado.']
            : ($total > 0 ? [$total.' eventos en '.$zones->count().' zonas del cuerpo dentro de los filtros actuales.'] : []);

        return self::make('body', 'Vista del cuerpo', $facts, 'No hay eventos con zona del cuerpo en este periodo.');
    }
}
