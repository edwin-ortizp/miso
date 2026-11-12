<?php

namespace App\Services\Notifications;

use App\Models\Goal;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\Task;
use App\Models\UserNotification;
use App\Services\Insights\WeeklyInsights;
use App\Services\Notifications\Push\GuardianNudgePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/** Read-only context for the inbox, including notices written before this UI. */
class NotificationPresentation
{
    public function forNotices(Collection $notices, int $userId): array
    {
        $notices = $notices->filter(fn ($notice) => (int) $notice->user_id === $userId);
        $sources = [];
        foreach ($notices->groupBy('source_type') as $type => $group) {
            $class = Relation::getMorphedModel($type) ?? $type;
            if (! in_array($class, NotificationSource::TYPES, true)) {
                continue;
            }
            $sources[$type] = $class::withoutGlobalScopes()->where('user_id', $userId)
                ->whereIn('id', $group->pluck('source_id')->unique())->get()->keyBy('id');
        }

        return $notices->mapWithKeys(fn ($notice) => [$notice->id => $this->present($notice, $sources[$notice->source_type][$notice->source_id] ?? null)])->all();
    }

    private function present(UserNotification $notice, $source): array
    {
        $now = CarbonImmutable::now(NotificationSource::TIMEZONE);
        $special = in_array($notice->source_type, [GuardianNudgePlanner::SOURCE, WeeklyInsights::SOURCE], true);
        $kind = match (true) {
            $source instanceof Task => 'Tarea',
            $source instanceof Plan => 'Plan',
            $source instanceof Goal => 'Meta',
            $source instanceof Relationship => 'Cumpleaños',
            $notice->source_type === GuardianNudgePlanner::SOURCE => 'Guardián',
            $notice->source_type === WeeklyInsights::SOURCE => 'Resumen semanal',
            default => 'Aviso',
        };
        $icon = match ($notice->type) {
            'task_due' => 'bi-check2-square', 'plan_due' => 'bi-calendar-event',
            'birthday' => 'bi-gift', 'reminder' => 'bi-alarm',
            'guardian_nudge' => 'bi-stars', 'weekly_insights' => 'bi-bar-chart', default => 'bi-info-circle',
        };
        $title = $special ? $notice->title : ($source instanceof Relationship ? 'Cumpleaños de '.$source->full_name : ($source?->title ?? 'Elemento no disponible'));
        $body = $special ? $notice->body : '';
        $event = null;
        // Occurrence keys for automatic notices contain the original event date.
        if ($source && in_array($notice->type, ['task_due', 'plan_due', 'birthday'], true)) {
            $event = $this->parseDate($notice->occurrence_key);
        } elseif ($source && $notice->type === 'reminder') {
            $event = app(NotificationSource::class)->anchor($source);
        }
        $withTime = $source instanceof Task && ! $source->end_is_date;
        $tone = 'neutral';
        $context = $kind;
        if ($event) {
            $active = app(NotificationSource::class)->active($source);
            $late = $active && ! ($source instanceof Relationship) && ($withTime ? $event->lt($now) : $event->startOfDay()->lt($now->startOfDay()));
            $today = $event->isSameDay($now);
            if ($active && ! ($source instanceof Relationship)) {
                $tone = $late ? 'danger' : ($today ? 'warning' : 'neutral');
            }
            $verb = match (true) {
                $source instanceof Relationship => '',
                ! $active => 'Fecha del aviso: ',
                $late => $source instanceof Plan ? 'Fecha pasada: ' : 'Vencida: ',
                $source instanceof Plan => 'Programado: ',
                default => 'Vence ',
            };
            $context .= ' · '.($notice->type === 'reminder' ? 'Recordatorio · Fecha actual: ' : $verb).$this->dateLabel($event, $now, $withTime);
            if (! $active && ! ($source instanceof Relationship)) {
                $context .= ' · '.match (true) {
                    $source instanceof Task && $source->completed => 'Completada',
                    $source instanceof Plan && $source->status === 'done' => 'Realizado',
                    default => 'No pendiente',
                };
            }
            if ($late && $notice->type === 'reminder') {
                $context .= ' · Vencida';
            }
        } elseif ($notice->source_type === GuardianNudgePlanner::SOURCE) {
            $context .= ' · '.match (GuardianNudgePlanner::moduleFor((string) $notice->source_id)) {
                'habits' => 'Hábitos', 'water' => 'Hidratación', 'tasks' => 'Tareas', 'exercise' => 'Ejercicio', default => 'Inicio',
            };
        } elseif ($notice->source_type === WeeklyInsights::SOURCE && ($week = $this->parseDate($notice->occurrence_key))) {
            $context .= ' · Semana del '.$this->dateLabel($week, $now);
        } elseif (! $source && ! $special) {
            $context = 'El elemento ya no está disponible. Abrir módulo';
        } elseif ($notice->type === 'reminder') {
            $context .= ' · Recordatorio · Sin fecha actual';
        }
        $destination = match (true) {
            $notice->source_type === WeeklyInsights::SOURCE => route('statistics').'#insights',
            $notice->source_type === GuardianNudgePlanner::SOURCE => route(match (GuardianNudgePlanner::moduleFor((string) $notice->source_id)) {
                'habits' => 'habits', 'water' => 'water.daily', 'tasks' => 'tasks.list', 'exercise' => 'exercise', default => 'home',
            }),
            $source instanceof Task => route('tasks.list', ['edit' => $source->id]),
            $source instanceof Plan => route('plans.show', $source->id),
            $source instanceof Goal => route('goals.show', $source->id),
            $source instanceof Relationship => route('relationships.show', $source->id),
            default => route(match (Relation::getMorphedModel($notice->source_type) ?? $notice->source_type) {
                Task::class => 'tasks.list', Plan::class => 'plans', Goal::class => 'goals', Relationship::class => 'relationships', default => 'home',
            }),
        };

        return compact('title', 'body', 'context', 'icon', 'tone', 'destination') + [
            'noticeDate' => $this->dateLabel(CarbonImmutable::instance($notice->scheduled_for)->timezone(NotificationSource::TIMEZONE), $now, true),
            'noticeIso' => $notice->scheduled_for->toIso8601String(),
        ];
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value, NotificationSource::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateLabel(CarbonImmutable $date, CarbonImmutable $now, bool $withTime = false): string
    {
        $absolute = $date->locale('es')->translatedFormat('j M'.($date->year !== $now->year ? ' Y' : ''));
        $relative = $date->isSameDay($now) ? 'hoy' : ($date->isSameDay($now->addDay()) ? 'mañana' : ($date->isSameDay($now->subDay()) ? 'ayer' : null));

        return ($relative ? $relative.', ' : '').$absolute.($withTime ? ' · '.$date->format('G:i') : '');
    }
}
