<?php

namespace App\Services\Learning;

use App\Models\LearningUnit;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AcademicRecords
{
    public function subjects(int $user)
    {
        return LearningUnit::withoutGlobalScopes()->where('user_id', $user)->where('kind', 'subject');
    }

    public function subject(int $user, string $id): LearningUnit
    {
        return $this->subjects($user)->findOrFail($id);
    }

    public function book(int $user, string $unit): object
    {
        return $this->owned('learning_gradebooks', $user, $unit)->first() ?? self::defaultBook();
    }

    public static function defaultBook(): object
    {
        return (object) ['mode' => 'direct', 'minimum' => 0, 'maximum' => 500, 'passing' => 300];
    }

    public function cuts(int $user, string $unit)
    {
        return $this->owned('learning_grade_cuts', $user, $unit)->orderBy('title')->orderBy('id')->get()->keyBy('id');
    }

    public function evaluations(int $user, string $unit)
    {
        return $this->evaluationQuery($user)->where('e.unit_id', $unit)
            ->orderByRaw('CASE WHEN COALESCE(t.end_date, t.start_date, e.scheduled_on) IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(t.end_date, t.start_date, e.scheduled_on)')->orderBy('e.created_at')->orderBy('e.id')
            ->get(['e.*', 't.title as task_title', 't.completed as task_completed',
                DB::raw('COALESCE(t.end_date, t.start_date) as task_deadline'),
                DB::raw('CASE WHEN t.end_date IS NOT NULL THEN t.end_is_date ELSE t.start_is_date END as task_all_day')])
            ->keyBy('id');
    }

    public function evaluationQuery(int $user): Builder
    {
        return DB::table('learning_evaluations as e')
            ->leftJoin('tasks as t', fn ($join) => $join->on('e.task_id', '=', 't.id')->on('e.user_id', '=', 't.user_id'))
            ->where('e.user_id', $user);
    }

    public function saveTask(int $user, string $unit, string $id, array $data, bool $graded): Task
    {
        return $this->write($user, function () use ($user, $unit, $id, $data, $graded) {
            $this->subject($user, $unit);
            $draft = $this->validate($data, ['title' => 'required|string|max:255', 'date' => 'nullable|date_format:Y-m-d']);
            $link = $this->owned('learning_subject_tasks', $user, $unit)->where('task_id', $id)->first();
            if ($link) {
                return Task::where('user_id', $user)->findOrFail($id);
            }

            $task = new Task(['title' => $draft['title'], 'task_code' => random_int(10000, 99999),
                'end_date' => $draft['date'] ?? null, 'end_is_date' => filled($draft['date'] ?? null)]);
            $task->user_id = $user;
            $task->id = $id;
            abort_unless($task->save(), 422);
            DB::table('learning_subject_tasks')->insert(['task_id' => $id, 'user_id' => $user, 'unit_id' => $unit]);

            if ($graded) {
                $this->persistEvaluation($user, $unit, $id, $data + ['kind' => 'work'], $task);
            }

            return $task;
        });
    }

    public function saveBook(int $user, string $unit, array $data): void
    {
        $this->write($user, function () use ($user, $unit, $data) {
            $this->subject($user, $unit);
            $rules = 'required|numeric|decimal:0,2|between:-1000,1000';
            $draft = $this->validate($data, ['mode' => 'required|in:direct,cuts', 'minimum' => $rules, 'maximum' => $rules, 'passing' => $rules]);
            $minimum = $this->hundredths($draft['minimum']);
            $maximum = $this->hundredths($draft['maximum']);
            $passing = $this->hundredths($draft['passing']);
            if ($minimum >= $maximum) {
                $this->invalid('maximum', 'La nota máxima debe ser mayor que la mínima.');
            }
            if ($passing < $minimum || $passing > $maximum) {
                $this->invalid('passing', 'El mínimo para pasar debe estar dentro de la escala.');
            }
            $evaluations = $this->owned('learning_evaluations', $user, $unit);
            if ((clone $evaluations)->where(fn ($query) => $query->where('score', '<', $minimum)->orWhere('score', '>', $maximum))->exists()) {
                $this->invalid('maximum', 'La escala debe incluir las notas ya registradas.');
            }
            $old = $this->book($user, $unit);
            if ($old->mode !== $draft['mode'] && ((clone $evaluations)->whereNotNull('weight')->exists() || $this->cuts($user, $unit)->isNotEmpty())) {
                $this->invalid('mode', 'Para cambiar el esquema, retira los pesos y cortes que ya configuraste.');
            }
            DB::table('learning_gradebooks')->updateOrInsert(['unit_id' => $unit, 'user_id' => $user], [
                'mode' => $draft['mode'], 'minimum' => $minimum, 'maximum' => $maximum, 'passing' => $passing,
            ]);
        });
    }

    public function saveCut(int $user, string $unit, string $id, array $data): void
    {
        $this->write($user, function () use ($user, $unit, $id, $data) {
            $this->subject($user, $unit);
            abort_unless($this->book($user, $unit)->mode === 'cuts', 422);
            $draft = $this->validate($data, ['title' => 'required|string|max:160', 'weight' => 'required|numeric|decimal:0,2|between:0.01,100']);
            $weight = $this->hundredths($draft['weight']);
            $others = $this->owned('learning_grade_cuts', $user, $unit)->where('id', '<>', $id);
            $this->validateWeight($others, $weight);
            DB::table('learning_grade_cuts')->updateOrInsert(['id' => $id, 'user_id' => $user, 'unit_id' => $unit], [
                'title' => $draft['title'], 'weight' => $weight,
            ]);
        });
    }

    public function saveEvaluation(int $user, string $unit, string $id, array $data): void
    {
        $this->write($user, function () use ($user, $unit, $id, $data) {
            $this->subject($user, $unit);
            $this->persistEvaluation($user, $unit, $id, $data);
        });
    }

    private function persistEvaluation(int $user, string $unit, string $id, array $data, ?Task $task = null): void
    {
        $existing = $this->owned('learning_evaluations', $user, $unit)->where('id', $id)->first();
        $linked = $task || $existing?->task_id;
        $draft = $this->validate($data, [
            'title' => $linked ? 'nullable|string|max:255' : 'required|string|max:255',
            'kind' => 'required|in:exam,work,other', 'weight' => 'nullable|numeric|decimal:0,2|between:0.01,100',
            'cut_id' => 'nullable|uuid', 'date' => 'nullable|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i', 'score' => 'nullable|numeric|decimal:0,2',
        ]);
        if (! $linked && filled($draft['time'] ?? null) && ! filled($draft['date'] ?? null)) {
            $this->invalid('date', 'Indica una fecha para usar la hora.');
        }
        $book = $this->book($user, $unit);
        $cut = $book->mode === 'cuts' ? ($draft['cut_id'] ?? null) : null;
        if ($cut && ! $this->owned('learning_grade_cuts', $user, $unit)->where('id', $cut)->exists()) {
            $this->invalid('cut_id', 'Elige un corte de esta materia.');
        }
        $weight = isset($draft['weight']) ? $this->hundredths($draft['weight']) : null;
        if ($weight !== null && ($book->mode === 'direct' || $cut)) {
            $others = $this->owned('learning_evaluations', $user, $unit)->where('cut_id', $cut)->where('id', '<>', $id);
            $this->validateWeight($others, $weight);
        }
        $score = $this->score($book, $draft['score'] ?? null);
        DB::table('learning_evaluations')->updateOrInsert(['id' => $id, 'user_id' => $user, 'unit_id' => $unit], [
            'title' => $task?->title ?? ($linked ? $existing->title : $draft['title']),
            'task_id' => $task?->id ?? $existing?->task_id,
            'kind' => $linked ? 'work' : $draft['kind'],
            'scheduled_on' => $linked ? null : ($draft['date'] ?? null),
            'scheduled_at' => $linked ? null : ($draft['time'] ?? null),
            'cut_id' => $cut, 'weight' => $weight, 'score' => $score,
            'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
        ]);
    }

    public function recordScore(int $user, string $unit, string $id, mixed $value): void
    {
        $this->write($user, function () use ($user, $unit, $id, $value) {
            $this->subject($user, $unit);
            $record = $this->owned('learning_evaluations', $user, $unit)->where('id', $id);
            abort_unless($record->exists(), 404);
            $record->update(['score' => $this->score($this->book($user, $unit), $value), 'updated_at' => now()]);
        });
    }

    public function delete(int $user, string $unit, string $type, string $id): void
    {
        $this->write($user, function () use ($user, $unit, $type, $id) {
            $this->subject($user, $unit);
            if ($type === 'cut' && $this->owned('learning_evaluations', $user, $unit)->where('cut_id', $id)->exists()) {
                $this->invalid('cut_id', 'Mueve las evaluaciones a otro corte antes de eliminarlo.');
            }
            $table = $type === 'cut' ? 'learning_grade_cuts' : 'learning_evaluations';
            $this->owned($table, $user, $unit)->where('id', $id)->delete();
        });
    }

    public function score(object $book, mixed $value): ?int
    {
        $raw = $this->validate(['score' => $value], ['score' => 'nullable|numeric|decimal:0,2|between:-1000,1000'])['score'];
        $score = $raw !== null ? $this->hundredths($raw) : null;
        if ($score !== null && ($score < $book->minimum || $score > $book->maximum)) {
            $this->invalid('score', 'Escribe una nota dentro de la escala de la materia.');
        }

        return $score;
    }

    private function validateWeight(Builder $others, int $weight): void
    {
        if ($others->sum('weight') + $weight > 10_000) {
            $this->invalid('weight', 'Con este peso, el grupo superaría el 100 %.');
        }
    }

    private function validate(array $data, array $rules): array
    {
        foreach ($rules as $key => $rule) {
            if (is_string($data[$key] ?? null)) {
                $data[$key] = trim($data[$key]);
                if (str_contains($rule, 'numeric')) {
                    $data[$key] = str_replace(',', '.', $data[$key]);
                }
                $data[$key] = $data[$key] === '' ? null : $data[$key];
            }
        }

        return Validator::make($data, $rules, [
            'required' => 'Completa este campo.', 'numeric' => 'Escribe un número válido.',
            'decimal' => 'Usa como máximo dos decimales.', 'between.numeric' => 'Escribe un valor entre :min y :max.',
            'date_format' => 'Revisa el formato de la fecha o la hora.',
        ])->validate();
    }

    private function hundredths(string|int|float $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function owned(string $table, int $user, string $unit): Builder
    {
        return DB::table($table)->where('user_id', $user)->where('unit_id', $unit);
    }

    private function write(int $user, callable $callback): mixed
    {
        return DB::transaction(function () use ($user, $callback) {
            User::whereKey($user)->lockForUpdate()->firstOrFail();

            return $callback();
        }, 3);
    }
}
