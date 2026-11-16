<?php

namespace App\Mcp\Tools\Context;

use App\Mcp\Support\DateWindow;
use App\Mcp\Support\McpOutput;
use App\Models\ExerciseLog;
use App\Models\Goal;
use App\Models\GoalEntry;
use App\Models\GoalNumericEntry;
use App\Models\HealthEvent;
use App\Models\HealthLog;
use App\Models\HealthMeasurement;
use App\Models\MedicationIntake;
use App\Models\SleepLog;
use App\Models\JournalEntry;
use App\Models\MealPlanEntry;
use App\Models\MealPlanEntryItem;
use App\Models\MoodEntry;
use App\Models\NegativeHabitLog;
use App\Models\PlanVisit;
use App\Models\PomodoroSession;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Models\Task;
use App\Models\VehicleEnergyLog;
use App\Support\GoalProgress;
use App\Support\WaterGoal;
use App\Support\WaterProgress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Línea de tiempo de lo que ha pasado en la vida del usuario en un periodo (por defecto los últimos 7 días), cruzando módulos: tareas completadas, planes y visitas, ejercicio, ánimo, energía, hidratación, hábitos, salud, eventos de sus personas, repostajes, avances en metas, resúmenes del diario, comidas, hábitos negativos y tiempo de foco. Úsala para "¿cómo me fue esta semana?", resúmenes del día o la semana, retomar contexto general o detectar patrones entre módulos. Limita "modules" si solo interesan algunos.')]
class GetRecentActivityTool extends Tool
{
    public const MODULES = ['tasks', 'plans', 'exercise', 'mood', 'energy', 'water', 'habits', 'health', 'measurements', 'sleep', 'medications', 'relationships', 'vehicles', 'goals', 'journal', 'meals', 'negative_habits', 'focus'];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            ...DateWindow::rules(),
            'days' => ['nullable', 'integer', 'min:1', 'max:62'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(self::MODULES)],
            'include_sensitive' => ['nullable', 'boolean'],
        ]);

        $window = DateWindow::fromInput($data, $data['days'] ?? 7);
        $modules = collect($data['modules'] ?? self::MODULES);
        $includeSensitive = (bool) ($data['include_sensitive'] ?? false);

        $items = collect();
        $summary = [];

        foreach ($modules as $module) {
            [$moduleItems, $moduleSummary] = match ($module) {
                'tasks' => $this->tasks($window),
                'plans' => $this->plans($window),
                'exercise' => $this->exercise($window),
                'mood' => $this->mood($window),
                'energy' => $this->energy($window),
                'water' => $this->water($window),
                'habits' => $this->habits($window),
                'health' => $this->health($window, $includeSensitive),
                'measurements' => $this->measurements($window, $includeSensitive),
                'sleep' => $this->sleep($window, $includeSensitive),
                'medications' => $this->medications($window, $includeSensitive),
                'relationships' => $this->relationshipEvents($window, $includeSensitive),
                'vehicles' => $this->vehicles($window),
                'goals' => $this->goals($window),
                'journal' => $this->journal($window),
                'meals' => $this->meals($window),
                'negative_habits' => $this->negativeHabits($window),
                'focus' => $this->focus($window),
            };

            $items = $items->concat($moduleItems);
            if ($moduleSummary !== null) {
                $summary[$module] = $moduleSummary;
            }
        }

        $byDay = $items->sortBy('order')
            ->groupBy('date')
            ->sortKeysDesc()
            ->map(fn (Collection $day, string $date) => [
                'date' => $date,
                'weekday' => Carbon::parse($date)->locale('es')->dayName,
                'items' => $day->map(fn (array $item) => "[{$item['module']}] {$item['text']}")->values()->all(),
            ])
            ->values();

        return Response::structured(McpOutput::compact([
            'period' => $window->toArray(),
            'summary' => $summary ?: null,
            'days' => $byDay->all(),
        ]));
    }

    private function item(string $date, string $module, string $text, int $order = 50): array
    {
        return ['date' => $date, 'module' => $module, 'text' => $text, 'order' => $order];
    }

    private function between($query, string $column, DateWindow $window)
    {
        return $query->whereDate($column, '>=', $window->fromDate())->whereDate($column, '<=', $window->toDate());
    }

    private function tasks(DateWindow $window): array
    {
        $tasks = Auth::user()->tasks()
            ->where('completed', true)
            ->where('completed_at', '>=', $window->from->copy()->startOfDay())
            ->where('completed_at', '<=', $window->to->copy()->endOfDay())
            ->orderBy('completed_at')
            ->limit(100)
            ->get();

        $items = $tasks->map(fn (Task $task) => $this->item(
            $task->completed_at->timezone(config('app.timezone'))->toDateString(),
            'tarea',
            'Completó "'.$task->title.'"'.($task->category ? " ({$task->category})" : ''),
            10,
        ));

        $overdue = Auth::user()->tasks()->where('completed', false)
            ->whereDate('end_date', '<', today()->toDateString())->count();

        return [$items, ['completed' => $tasks->count(), 'pending_overdue_now' => $overdue]];
    }

    private function plans(DateWindow $window): array
    {
        $visits = $this->between(PlanVisit::query()->with(['plan', 'relationships'])->whereHas('plan'), 'visited_on', $window)->get();

        $items = $visits->map(fn (PlanVisit $visit) => $this->item(
            $visit->visited_on->toDateString(),
            'plan',
            $visit->plan->title.($visit->plan->city ? " ({$visit->plan->city})" : '')
                .($visit->relationships->isNotEmpty() ? ' con '.$visit->relationships->map(fn (Relationship $person) => $person->displayName())->implode(', ') : '')
                .($visit->comment ? ": {$visit->comment}" : ''),
            20,
        ));

        return [$items, $visits->isEmpty() ? null : ['visits' => $visits->count()]];
    }

    private function exercise(DateWindow $window): array
    {
        $logs = $this->between(Auth::user()->exerciseLogs()->with('exerciseType'), 'date', $window)->get();

        $items = $logs->map(fn (ExerciseLog $log) => $this->item(
            $log->date->toDateString(),
            'ejercicio',
            ($log->exerciseType?->name ?? 'Ejercicio')
                .($log->duration ? " {$log->duration} min" : '')
                .($log->distance ? ' '.(float) $log->distance.' km' : '')
                .($log->steps ? " {$log->steps} pasos" : ''),
            30,
        ));

        return [$items, $logs->isEmpty() ? null : ['sessions' => $logs->count(), 'minutes' => (int) $logs->sum('duration')]];
    }

    private function mood(DateWindow $window): array
    {
        $entries = $this->between(Auth::user()->moodEntries(), 'date', $window)->orderBy('timestamp')->get();

        $items = $entries->groupBy(fn (MoodEntry $entry) => $entry->date->toDateString())
            ->map(fn (Collection $day, string $date) => $this->item(
                $date,
                'ánimo',
                $day->map(fn (MoodEntry $entry) => trim($entry->emoji.' '.$entry->text).($entry->situation ? " ({$entry->situation})" : ''))->implode('; '),
                40,
            ))->values();

        $values = $entries->pluck('value')->filter(fn ($value) => is_numeric($value));

        return [$items, $entries->isEmpty() ? null : McpOutput::compact([
            'entries' => $entries->count(),
            'average_value' => $values->isEmpty() ? null : round($values->avg(), 2),
        ])];
    }

    private function energy(DateWindow $window): array
    {
        $entries = $this->between(Auth::user()->energyEntries(), 'date', $window)->get();

        $items = $entries->groupBy(fn ($entry) => $entry->date->toDateString())
            ->map(fn (Collection $day, string $date) => $this->item($date, 'energía', 'Promedio '.round($day->avg('level'), 1).'/5', 41))
            ->values();

        return [$items, $entries->isEmpty() ? null : ['average_level' => round($entries->avg('level'), 2)]];
    }

    private function water(DateWindow $window): array
    {
        $goal = WaterGoal::forUser(Auth::user());
        $totals = WaterProgress::totals($window->from, $window->to);

        $items = $totals->map(fn (int $total, string $date) => $this->item($date, 'agua', "{$total} ml de {$goal} ml".($total >= $goal ? ' (meta cumplida)' : ''), 60))->values();

        return [$items, $totals->isEmpty() ? null : [
            'days_goal_met' => $totals->filter(fn (int $total) => $total >= $goal)->count(),
            'average_ml' => (int) round($totals->avg()),
        ]];
    }

    private function habits(DateWindow $window): array
    {
        $total = Auth::user()->habitDefinitions()->count();

        if ($total === 0) {
            return [collect(), null];
        }

        $completions = $this->between(Auth::user()->habitCompletions()->where('completed', true), 'date', $window)
            ->get(['habit_id', 'date']);

        $items = $completions->groupBy(fn ($completion) => $completion->date->toDateString())
            ->map(fn (Collection $day, string $date) => $this->item($date, 'hábitos', $day->unique('habit_id')->count()."/{$total} hábitos", 70))
            ->values();

        $days = $window->from->diffInDays($window->to) + 1;

        return [$items, ['completion_rate' => round($completions->unique(fn ($row) => $row->habit_id.$row->date)->count() / ($total * $days), 2)]];
    }

    private function health(DateWindow $window, bool $includeSensitive): array
    {
        $events = $this->between(Auth::user()->healthEvents(), 'event_date', $window)
            ->when(! $includeSensitive, fn ($query) => $query->where('is_sensitive', false))
            ->get();

        $recovered = $this->between(Auth::user()->healthEvents(), 'end_date', $window)
            ->when(! $includeSensitive, fn ($query) => $query->where('is_sensitive', false))
            ->whereIn('type', HealthEvent::EVOLUTION_TYPES)
            ->get();

        $logs = $this->between(Auth::user()->healthLogs()->with('healthEvent'), 'date', $window)
            ->whereHas('healthEvent', fn ($query) => $includeSensitive ? $query : $query->where('is_sensitive', false))
            ->get()
            // El registro inicial de intensidad coincide con el alta del evento.
            ->reject(fn (HealthLog $log) => $log->healthEvent && $log->date->isSameDay($log->healthEvent->event_date));

        $items = collect()
            ->concat($events->map(fn (HealthEvent $event) => $this->item($event->event_date->toDateString(), 'salud', (HealthEvent::TYPES[$event->type] ?? $event->type).': '.$event->title, 5)))
            ->concat($recovered->map(fn (HealthEvent $event) => $this->item($event->end_date->toDateString(), 'salud', 'Se recuperó de: '.$event->title, 6)))
            ->concat($logs->map(fn (HealthLog $log) => $this->item($log->date->toDateString(), 'salud', "Seguimiento \"{$log->healthEvent?->title}\": intensidad {$log->intensity}/10", 7)));

        return [$items, $items->isEmpty() ? null : ['events' => $events->count(), 'recovered' => $recovered->count()]];
    }

    private function measurements(DateWindow $window, bool $includeSensitive): array
    {
        $rows = $this->between(Auth::user()->healthMeasurements(), 'measured_at', $window)
            ->when(! $includeSensitive, fn ($query) => $query->where('is_sensitive', false))->get();

        $items = $rows->map(fn (HealthMeasurement $m) => $this->item($m->measured_at->toDateString(), 'medición',
            $m->typeLabel().' '.$m->displayValue().' '.$m->unit.($m->contextLabel() ? ' ('.$m->contextLabel().')' : '').($m->isOutOfRange() ? ' · fuera del rango de referencia general' : ''), 8));

        return [$items->values(), $rows->isEmpty() ? null : ['measurements' => $rows->count(), 'out_of_range' => $rows->filter(fn (HealthMeasurement $m) => $m->isOutOfRange())->count()]];
    }

    private function sleep(DateWindow $window, bool $includeSensitive): array
    {
        $rows = $this->between(Auth::user()->sleepLogs(), 'woke_at', $window)
            ->when(! $includeSensitive, fn ($query) => $query->where('is_sensitive', false))->get();

        $items = $rows->map(fn (SleepLog $l) => $this->item($l->woke_at->toDateString(), 'sueño',
            'Durmió '.$l->durationText().' ('.$l->slept_at->format('H:i').' → '.$l->woke_at->format('H:i').')'.($l->quality ? ", calidad {$l->quality}/5" : ''), 9));

        return [$items->values(), $rows->isEmpty() ? null : [
            'records' => $rows->count(),
            'average' => SleepLog::formatMinutes((int) round($rows->avg(fn (SleepLog $l) => $l->durationMinutes()))),
        ]];
    }

    private function medications(DateWindow $window, bool $includeSensitive): array
    {
        $rows = Auth::user()->medicationIntakes()
            ->where(fn ($q) => $q->whereDate('taken_at', '>=', $window->fromDate())->whereDate('taken_at', '<=', $window->toDate())
                ->orWhere(fn ($q) => $q->whereNull('taken_at')->whereDate('scheduled_for', '>=', $window->fromDate())->whereDate('scheduled_for', '<=', $window->toDate())))
            ->when(! $includeSensitive, fn ($query) => $query->where('is_sensitive', false))->get();

        $items = $rows->map(fn (MedicationIntake $i) => $this->item($i->occurredAt()->toDateString(), 'medicamentos',
            ($i->status === 'skipped' ? 'Omitió ' : 'Tomó ').$i->name.($i->doseText() ? ' '.$i->doseText() : '').' a las '.$i->occurredAt()->format('H:i'), 10));

        return [$items->values(), $rows->isEmpty() ? null : ['taken' => $rows->where('status', 'taken')->count(), 'skipped' => $rows->where('status', 'skipped')->count()]];
    }

    private function relationshipEvents(DateWindow $window, bool $includeSensitive): array
    {
        $events = Auth::user()->relationshipEvents()
            ->with('relationship')
            ->whereHas('relationship')
            ->active()
            ->visibleGlobally($includeSensitive)
            ->where(fn ($query) => $query
                ->where(fn ($range) => $range->whereDate('starts_on', '>=', $window->fromDate())->whereDate('starts_on', '<=', $window->toDate()))
                ->orWhere(fn ($legacy) => $legacy->whereNull('starts_on')->whereDate('event_date', '>=', $window->fromDate())->whereDate('event_date', '<=', $window->toDate())))
            ->get();

        $items = $events->map(fn (RelationshipEvent $event) => $this->item(
            ($event->starts_on ?? $event->event_date)->toDateString(),
            'persona',
            $event->relationship->displayName().': '.$event->title,
            25,
        ));

        return [$items, $events->isEmpty() ? null : ['events' => $events->count()]];
    }

    private function vehicles(DateWindow $window): array
    {
        $vehicleIds = Auth::user()->vehicles()->pluck('id', 'id');
        $logs = $vehicleIds->isEmpty() ? collect() : $this->between(VehicleEnergyLog::query()->with('vehicle')->whereIn('vehicle_id', $vehicleIds), 'recorded_on', $window)->get();

        $items = $logs->map(fn (VehicleEnergyLog $log) => $this->item(
            $log->recorded_on->toDateString(),
            'vehículo',
            'Tanqueó '.($log->vehicle?->name ?? '').': '.(float) $log->quantity.' '.$log->unit.($log->cost ? ' por $'.number_format((float) $log->cost, 0, ',', '.') : ''),
            80,
        ));

        return [$items, $logs->isEmpty() ? null : ['fillups' => $logs->count(), 'cost' => round((float) $logs->sum('cost'), 2)]];
    }

    private function goals(DateWindow $window): array
    {
        $entries = $this->between(GoalEntry::query()->with('goal')->whereHas('goal'), 'date', $window)->get();
        $values = $this->between(GoalNumericEntry::query()->with('goal')->whereHas('goal'), 'date', $window)->get();

        $items = collect()
            ->concat($entries->map(fn (GoalEntry $entry) => $this->item(
                $entry->date->toDateString(),
                'meta',
                ($entry->is_milestone ? 'Hito en ' : 'Avance en ')."\"{$entry->goal->title}\": ".McpOutput::excerpt($entry->text, 120),
                15,
            )))
            ->concat($values->map(fn (GoalNumericEntry $entry) => $this->item(
                $entry->date->toDateString(),
                'meta',
                "\"{$entry->goal->title}\": ".(float) $entry->value.' '.(GoalProgress::configuration($entry->goal->numeric_goal)['unit'] ?? ''),
                16,
            )));

        $completed = $this->between(Auth::user()->goals()->where('status', 'completed'), 'updated_at', $window)->get();
        $items = $items->concat($completed->map(fn (Goal $goal) => $this->item($goal->updated_at->timezone(config('app.timezone'))->toDateString(), 'meta', "Meta cumplida: \"{$goal->title}\"", 14)));

        return [$items, $items->isEmpty() ? null : ['updates' => $entries->count() + $values->count(), 'completed' => $completed->count() ?: null]];
    }

    /** Solo los resúmenes: el texto completo del diario se lee con list-journal-entries-tool. */
    private function journal(DateWindow $window): array
    {
        $entries = $this->between(Auth::user()->journalEntries(), 'date', $window)->get();

        $items = $entries->map(fn (JournalEntry $entry) => $this->item(
            $entry->date->toDateString(),
            'diario',
            filled($entry->summary) ? $entry->summary : 'Escribió en el diario',
            90,
        ));

        return [$items, $entries->isEmpty() ? null : ['days_written' => $entries->count()]];
    }

    private function meals(DateWindow $window): array
    {
        $entries = $this->between(Auth::user()->mealPlanEntries()->with(['items.recipe', 'items.preparation']), 'date', $window)->get();

        $items = $entries->groupBy(fn (MealPlanEntry $entry) => $entry->date->toDateString())
            ->map(fn (Collection $day, string $date) => $this->item(
                $date,
                'comidas',
                $day->map(fn (MealPlanEntry $entry) => $entry->meal_type.' ('.($entry->status === 'consumed' ? ($entry->consumption_mode === 'outside' ? 'consumida por fuera' : 'consumida') : 'planeada').'): '.$entry->items->map(fn (MealPlanEntryItem $item) => $item->recipe?->name ?? $item->name)->implode(', '))->implode('; ')
                    .' · '.(int) $day->where('status', 'planned')->sum('effective_calories').' kcal planeadas; '.(int) $day->where('status', 'consumed')->sum(fn ($entry) => $entry->consumption['calories'] ?? 0).' kcal consumidas',
                65,
            ))->values();

        return [$items, $entries->isEmpty() ? null : ['meals' => $entries->count()]];
    }

    private function negativeHabits(DateWindow $window): array
    {
        $logs = NegativeHabitLog::query()
            ->with('negativeHabitDefinition')
            ->whereBetween('timestamp', [$window->from->copy()->startOfDay()->timestamp, $window->to->copy()->endOfDay()->timestamp])
            ->get();

        $timezone = config('app.timezone');
        $items = $logs->groupBy(fn (NegativeHabitLog $log) => Carbon::createFromTimestamp((int) $log->timestamp, $timezone)->toDateString())
            ->map(fn (Collection $day, string $date) => $this->item(
                $date,
                'hábito negativo',
                $day->groupBy('habit_id')->map(fn (Collection $group) => ($group->first()->negativeHabitDefinition?->name ?? 'Hábito').' ×'.$group->count())->implode(', '),
                75,
            ))->values();

        return [$items, $logs->isEmpty() ? null : ['times' => $logs->count()]];
    }

    private function focus(DateWindow $window): array
    {
        $sessions = $this->between(Auth::user()->pomodoroSessions()->where('completed', true), 'date', $window)->get();

        $items = $sessions->groupBy(fn (PomodoroSession $session) => $session->date->toDateString())
            ->map(fn (Collection $day, string $date) => $this->item(
                $date,
                'foco',
                intdiv((int) $day->sum('duration'), 60).' min'
                    .(($topics = $day->pluck('description')->filter()->unique()->take(3)->implode(', ')) ? ": {$topics}" : ''),
                12,
            ))->values();

        return [$items, $sessions->isEmpty() ? null : ['hours' => round($sessions->sum('duration') / 3600, 1)]];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->description('Desde esta fecha (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Hasta esta fecha (YYYY-MM-DD). Por defecto hoy.'),
            'days' => $schema->integer()->description('Días hacia atrás si no envías since (por defecto 7, máximo 62).'),
            'modules' => $schema->array()->items($schema->string()->enum(self::MODULES))->description('Módulos a incluir. Por defecto todos.'),
            'include_sensitive' => $schema->boolean()->description('Incluye eventos de salud y de personas marcados como sensibles.'),
        ];
    }
}
