<?php

namespace App\Mcp\Tools\Context;

use App\Mcp\Support\McpOutput;
use App\Mcp\Tools\Health\Concerns\PresentsHealthEvents;
use App\Models\HealthEvent;
use App\Models\HealthMeasurement;
use App\Models\SleepLog;
use App\Support\HealthTrends;
use App\Support\MedicationSchedule;
use App\Support\SleepProgress;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Reúne en una llamada el contexto de salud relevante: malestares activos con su evolución, citas y exámenes próximos, tareas de salud pendientes o vinculadas, eventos recientes, antecedentes relacionados con lo que se busca mediciones con unidad de los últimos 30 días, sueño de la última semana, medicamentos de hoy, la relación cautelosa entre sueño y energía y ánimo y energía de los últimos días. El ciclo menstrual nunca se incluye aquí (usa list-cycle-tool solo si preguntó por él). Úsala primero cuando el usuario cuente un síntoma, dolor o malestar, hable de una cita, examen o tratamiento, o pregunte por su salud. Pasa en "search" las palabras clave del síntoma o sistema (p. ej. "riñón renal orina fiebre") y en "body_area" la zona si aplica. Omite eventos sensibles salvo include_sensitive=true.')]
class GetHealthContextTool extends Tool
{
    use PresentsHealthEvents;

    private const RECENT_DAYS = 30;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'body_area' => ['nullable', 'string', Rule::in(array_keys(HealthEvent::BODY_AREAS))],
            'depth' => ['nullable', 'string', Rule::in(['brief', 'full'])],
            'include_sensitive' => ['nullable', 'boolean'],
        ]);

        $full = ($data['depth'] ?? 'brief') === 'full';
        $includeSensitive = (bool) ($data['include_sensitive'] ?? false);
        $visible = fn ($query) => $includeSensitive ? $query : $query->where('is_sensitive', false);

        $active = $this->applyActiveHealthFilter($visible(Auth::user()->healthEvents()))
            ->with(['logs', 'tasks'])
            ->orderByDesc('event_date')
            ->get();

        $upcoming = $visible(Auth::user()->healthEvents())
            ->whereDate('event_date', '>', today()->toDateString())
            ->orderBy('event_date')
            ->limit(10)
            ->get();

        $shown = $active->pluck('id')->concat($upcoming->pluck('id'));

        $recent = $visible(Auth::user()->healthEvents())
            ->whereDate('event_date', '>=', today()->subDays(self::RECENT_DAYS)->toDateString())
            ->whereDate('event_date', '<=', today()->toDateString())
            ->whereNotIn('id', $shown)
            ->orderByDesc('event_date')
            ->limit($full ? 20 : 8)
            ->get();

        $shown = $shown->concat($recent->pluck('id'));
        $related = $this->related($data, $visible, $shown, $full ? 20 : 6);

        $hidden = $includeSensitive ? 0 : (Auth::user()->healthEvents()->where('is_sensitive', true)->count()
            + Auth::user()->healthMeasurements()->where('is_sensitive', true)->count()
            + Auth::user()->sleepLogs()->where('is_sensitive', true)->count()
            + Auth::user()->medications()->where('is_active', true)->where('is_sensitive', true)->count());

        return Response::structured(McpOutput::compact([
            'today' => today()->toDateString(),
            'active' => $active->map(fn (HealthEvent $event) => [
                ...$this->presentHealthEvent($event, $full, $full ? 10 : 3),
                'tasks' => $this->presentTasks($event->tasks),
            ])->all(),
            'upcoming' => $upcoming->map(fn (HealthEvent $event) => $this->presentHealthEvent($event, $full))->all(),
            'pending_tasks' => $this->pendingHealthTasks($active),
            'recent' => $recent->map(fn (HealthEvent $event) => $this->presentHealthEvent($event, $full))->all(),
            'related_history' => $related?->map(fn (HealthEvent $event) => $this->presentHealthEvent($event, $full))->all(),
            'wellbeing_last_7_days' => $this->wellbeing(),
            'measurements_last_30_days' => $this->measurements($includeSensitive),
            'sleep_last_7_days' => $this->sleep($includeSensitive),
            'sleep_and_energy' => HealthTrends::sleepEnergy(30, null, $includeSensitive)['text'] ?? null,
            'medications_today' => $this->medicationsToday($includeSensitive),
            'hidden_sensitive' => $hidden ?: null,
        ]));
    }

    /** Antecedentes que coinciden con lo que se busca, fuera de lo ya mostrado. */
    private function related(array $data, callable $visible, Collection $exclude, int $limit): ?Collection
    {
        if (blank($data['search'] ?? null) && blank($data['body_area'] ?? null)) {
            return null;
        }

        $query = $visible(Auth::user()->healthEvents())->whereNotIn('id', $exclude);

        if (filled($data['search'] ?? null)) {
            $this->applyHealthSearch($query, $data['search']);
        }

        $events = $query->orderByDesc('event_date')->limit(200)->get();

        return $this->filterByBodyArea($events, $data['body_area'] ?? null)->take($limit)->values();
    }

    /**
     * Tareas pendientes de salud: las vinculadas a eventos activos y las de una
     * categoría de salud (se detecta por la key o el nombre de la categoría).
     */
    private function pendingHealthTasks(Collection $active): ?array
    {
        $categories = Auth::user()->taskCategories()->get()
            ->filter(fn ($category) => preg_match('/salud|health|m[eé]dic/iu', $category->key.' '.$category->name) === 1)
            ->pluck('key');

        $tasks = Auth::user()->tasks()
            ->where('completed', false)
            ->where(fn ($query) => $query
                ->whereIn('category', \App\Models\TaskCategory::expandSelection($categories->all(), Auth::id(), 'key'))
                ->orWhereHas('healthEvents'))
            ->limit(30)
            ->get()
            ->reject(fn (Task $task) => $active->contains(fn (HealthEvent $event) => $event->tasks->contains('id', $task->id)));

        return $this->presentTasks($tasks);
    }

    private function presentTasks(Collection $tasks): ?array
    {
        $tasks = $tasks->where('completed', false);

        if ($tasks->isEmpty()) {
            return null;
        }

        return $tasks->sortBy(fn (Task $task) => $task->end_date?->timestamp ?? $task->start_date?->timestamp ?? PHP_INT_MAX)
            ->map(fn (Task $task) => McpOutput::compact([
                'id' => $task->id,
                'title' => $task->title,
                'date' => ($task->end_date ?? $task->start_date)?->toDateString(),
            ]))->values()->all();
    }

    /** Resumen por tipo de las mediciones del último mes; lo sensible solo con include_sensitive. */
    private function measurements(bool $includeSensitive): ?array
    {
        $rows = Auth::user()->healthMeasurements()->where('measured_at', '>=', today()->subDays(29))
            ->when(! $includeSensitive, fn ($q) => $q->where('is_sensitive', false))
            ->orderByDesc('measured_at')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->groupBy('type')->map(function (Collection $group, string $type) {
            /** @var HealthMeasurement $latest */
            $latest = $group->first();

            return McpOutput::compact([
                'type' => $type,
                'label' => HealthMeasurement::TYPES[$type]['label'] ?? $type,
                'unit' => $latest->unit,
                'count' => $group->count(),
                'latest' => $latest->displayValue().($latest->contextLabel() ? ' ('.$latest->contextLabel().')' : '').' · '.$latest->measured_at->format('Y-m-d H:i'),
                'average' => round($group->avg('value'), 1),
                'average_value2' => $group->whereNotNull('value2')->isEmpty() ? null : round($group->whereNotNull('value2')->avg('value2'), 1),
                'out_of_range' => $group->filter(fn (HealthMeasurement $m) => $m->isOutOfRange())->count() ?: null,
                'reference_range' => $latest->referenceText(),
            ]);
        })->values()->all();
    }

    private function sleep(bool $includeSensitive): ?array
    {
        $logs = SleepProgress::recent(7)->when(! $includeSensitive, fn (Collection $c) => $c->reject(fn (SleepLog $l) => $l->is_sensitive))->values();
        if ($logs->isEmpty()) {
            return null;
        }
        $goal = SleepProgress::goalMinutes(Auth::user());
        $summary = SleepProgress::summary($logs, $goal);

        return McpOutput::compact([
            'records' => $summary['nights'],
            'average' => SleepLog::formatMinutes($summary['average_minutes']),
            'goal' => SleepLog::formatMinutes($goal),
            'meeting_goal' => $summary['meeting_goal'],
            'average_quality' => $summary['average_quality'],
        ]);
    }

    private function medicationsToday(bool $includeSensitive): ?array
    {
        $rows = MedicationSchedule::forDay(now(), null, $includeSensitive);
        if ($rows->isEmpty()) {
            return null;
        }

        return McpOutput::compact([
            'scheduled' => $rows->count(),
            'taken' => $rows->where('state', 'taken')->count(),
            'skipped' => $rows->where('state', 'skipped')->count() ?: null,
            'pending' => $rows->whereIn('state', ['pending', 'overdue'])
                ->map(fn (array $row) => $row['medication']->name.' '.$row['scheduled_for']->format('H:i').($row['state'] === 'overdue' ? ' (atrasada)' : ''))->values()->all() ?: null,
        ]);
    }

    private function wellbeing(): ?array
    {
        $from = today()->subDays(6)->toDateString();
        $energy = Auth::user()->energyEntries()->whereDate('date', '>=', $from)->get();
        $mood = Auth::user()->moodEntries()->whereDate('date', '>=', $from)->get();
        $values = $mood->pluck('value')->filter(fn ($value) => is_numeric($value));

        if ($energy->isEmpty() && $mood->isEmpty()) {
            return null;
        }

        return McpOutput::compact([
            'average_energy' => $energy->isEmpty() ? null : round($energy->avg('level'), 2),
            'average_mood_value' => $values->isEmpty() ? null : round($values->avg(), 2),
            'moods' => $mood->groupBy('text')->map(fn ($group, $text) => trim($group->first()->emoji.' '.$text).' ×'.$group->count())->values()->take(5)->all() ?: null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Palabras clave del síntoma, órgano o sistema; trae antecedentes que mencionen cualquiera de ellas en título o notas.'),
            'body_area' => $schema->string()->enum(array_keys(HealthEvent::BODY_AREAS))->description('Zona del cuerpo para buscar antecedentes en esa zona.'),
            'depth' => $schema->string()->enum(['brief', 'full'])->description('brief (por defecto): notas recortadas y lo esencial. full: notas completas, más evolución y más antecedentes.'),
            'include_sensitive' => $schema->boolean()->description('Incluye eventos sensibles. Úsalo solo si el tema actual los requiere.'),
        ];
    }
}
