<?php

namespace App\Services\Learning;

use App\Models\Task;
use App\Services\TaskGamificationService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StudyAgenda
{
    public function __construct(private AcademicRecords $records, private GradeProjection $projection) {}

    public function query(int $user, string $formation = '', string $semester = '', string $unit = ''): Builder
    {
        $tasks = DB::table('learning_subject_tasks as link')->join('tasks as t', function ($join) {
            $join->on('link.task_id', '=', 't.id')->on('link.user_id', '=', 't.user_id');
        })->where('link.user_id', $user)
            ->selectRaw("t.id, link.unit_id, t.title, 'task' as kind, COALESCE(t.end_date, t.start_date) as moment,
                CASE WHEN t.end_date IS NOT NULL THEN t.end_is_date ELSE t.start_is_date END as all_day,
                t.completed, t.is_recurrent, NULL as score");

        $moment = DB::getDriverName() === 'sqlite'
            ? "e.scheduled_on || ' ' || COALESCE(e.scheduled_at, '00:00:00')"
            : "CONCAT(e.scheduled_on, ' ', COALESCE(e.scheduled_at, '00:00:00'))";
        $evaluations = DB::table('learning_evaluations as e')->where('e.user_id', $user)->whereNull('e.task_id')
            ->selectRaw("e.id, e.unit_id, e.title, e.kind, $moment as moment,
                CASE WHEN e.scheduled_at IS NULL THEN 1 ELSE 0 END as all_day,
                0 as completed, 0 as is_recurrent, e.score");

        return DB::query()->fromSub($tasks->unionAll($evaluations), 'agenda')
            ->join('learning_units as u', 'agenda.unit_id', '=', 'u.id')
            ->join('learning_formations as f', 'u.formation_id', '=', 'f.id')
            ->where('u.user_id', $user)->where('f.user_id', $user)
            ->when($formation !== '', fn ($query) => $query->where('u.formation_id', $formation))
            ->when($semester === 'none', fn ($query) => $query->whereNull('u.parent_id'))
            ->when($semester !== '' && $semester !== 'none', fn ($query) => $query->where('u.parent_id', $semester))
            ->when($unit !== '', fn ($query) => $query->where('u.id', $unit))
            ->select('agenda.*', 'u.formation_id', 'u.title as subject', 'f.title as formation')
            ->orderByRaw('CASE WHEN agenda.moment IS NULL THEN 1 ELSE 0 END')
            ->orderBy('agenda.moment')->orderBy('agenda.id');
    }

    public function actionable(Builder $query): Builder
    {
        return $query->where('agenda.completed', false)->where(function ($query) {
            $query->where('agenda.kind', 'task')->orWhereNull('agenda.score')
                ->orWhereDate('agenda.moment', '>=', now()->toDateString());
        });
    }

    public function complete(int $user, string $id, ?string $unit, TaskGamificationService $tasks): array
    {
        abort_unless(DB::table('learning_subject_tasks')->where('user_id', $user)->where('task_id', $id)
            ->when($unit, fn ($query) => $query->where('unit_id', $unit))->exists(), 404);

        return DB::transaction(function () use ($user, $id, $tasks) {
            $task = Task::where('user_id', $user)->lockForUpdate()->findOrFail($id);

            return $task->is_recurrent ? ['recurring' => true] : $tasks->complete($task);
        }, 3);
    }

    public function attention(int $user, string $formation, string $semester): array
    {
        $attention = [];
        $this->records->subjects($user)->where('status', '<>', 'completed')
            ->whereHas('formation', fn ($query) => $query->where('user_id', $user)->where('status', '<>', 'completed'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('learning_evaluations as e')
                ->whereColumn('e.unit_id', 'learning_units.id')->where('e.user_id', $user))
            ->when($formation !== '', fn ($query) => $query->where('formation_id', $formation))
            ->when($semester === 'none', fn ($query) => $query->whereNull('parent_id'))
            ->when($semester !== '' && $semester !== 'none', fn ($query) => $query->where('parent_id', $semester))
            ->with('formation:id,title')->chunkById(100, function ($subjects) use ($user, &$attention) {
                $records = fn ($table) => DB::table($table)->where('user_id', $user)->whereIn('unit_id', $subjects->modelKeys())->get();
                $books = $records('learning_gradebooks')->keyBy('unit_id');
                $cuts = $records('learning_grade_cuts')->groupBy('unit_id');
                $evaluations = $records('learning_evaluations')->groupBy('unit_id');
                foreach ($subjects as $subject) {
                    $book = $books[$subject->id] ?? AcademicRecords::defaultBook();
                    $result = $this->projection->calculate($book, $cuts[$subject->id] ?? [], $evaluations[$subject->id] ?? []);
                    if ($result['urgency']) {
                        $rank = ($result['state'] === 'impossible' ? $book->passing - $result['value'] : $result['value'] - $book->minimum)
                            / ($book->maximum - $book->minimum);
                        $attention[] = ['subject' => $subject, 'result' => $result, 'rank' => $rank];
                    }
                }

                usort($attention, function ($left, $right) {
                    return [$right['result']['urgency'], $right['rank'], $left['subject']->id]
                        <=> [$left['result']['urgency'], $left['rank'], $right['subject']->id];
                });
                $attention = array_slice($attention, 0, 6);
            });

        return $attention;
    }

    public function options(int $user, string $formation = '', string $semester = ''): array
    {
        return $this->records->subjects($user)
            ->when($formation !== '', fn ($query) => $query->where('formation_id', $formation))
            ->when($semester === 'none', fn ($query) => $query->whereNull('parent_id'))
            ->when($semester !== '' && $semester !== 'none', fn ($query) => $query->where('parent_id', $semester))
            ->with(['formation:id,title', 'parent:id,title'])->orderBy('title')->get()
            ->groupBy(fn ($unit) => $unit->formation->title)
            ->map(fn ($subjects) => $subjects->mapWithKeys(fn ($unit) => [
                $unit->id => $unit->title.($unit->parent ? ' · '.$unit->parent->title : ''),
            ])->all())->all();
    }

    public function calendarEvents(int $user, Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->setTimezone(config('app.timezone'));
        $end = $end->copy()->setTimezone(config('app.timezone'));
        $through = $end->isStartOfDay() ? $end->toDateString() : $end->copy()->addDay()->toDateString();

        return DB::table('learning_evaluations as e')
            ->join('learning_units as u', 'e.unit_id', '=', 'u.id')
            ->where('e.user_id', $user)->where('u.user_id', $user)->whereNull('e.task_id')
            ->where('e.scheduled_on', '>=', $start->toDateString())->where('e.scheduled_on', '<', $through)
            ->orderBy('e.scheduled_on')->orderBy('e.scheduled_at')->orderBy('e.id')
            ->get(['e.id', 'e.title', 'e.scheduled_on', 'e.scheduled_at', 'e.unit_id', 'u.formation_id', 'u.title as subject'])
            ->filter(function ($event) use ($start, $end) {
                if (! $event->scheduled_at) {
                    return true;
                }
                $moment = Carbon::parse($event->scheduled_on.' '.$event->scheduled_at);

                return $moment->gte($start) && $moment->lt($end);
            })
            ->map(fn ($event) => [
                'id' => 'evaluation-'.$event->id,
                'title' => $event->title.' · '.$event->subject,
                'start' => $event->scheduled_at
                    ? Carbon::parse($event->scheduled_on.' '.$event->scheduled_at)->toIso8601String()
                    : $event->scheduled_on,
                'allDay' => ! $event->scheduled_at, 'editable' => false,
                'classNames' => ['md-calendar-event--evaluations'],
                'url' => route('learning.units.show', [
                    $event->formation_id, $event->unit_id, 'section' => 'grades', 'evaluation' => $event->id,
                ]),
                'extendedProps' => ['type' => 'evaluations'],
            ])->values()->all();
    }

    public static function moment(object $row): ?Carbon
    {
        if (! $row->moment) {
            return null;
        }

        return Carbon::parse($row->moment)->locale('es');
    }

    public static function overdue(object $row): bool
    {
        return self::moment($row)?->lt($row->all_day ? today() : now()) ?? false;
    }

    public static function type(string $kind): string
    {
        return match ($kind) {
            'task' => 'Entrega', 'exam' => 'Parcial', 'work' => 'Trabajo', default => 'Evaluación',
        };
    }
}
