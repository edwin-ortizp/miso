<?php

namespace App\Services\Notifications;

use App\Models\Reminder;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageReminders
{
    public const UNITS = ['minutes' => 'Minutos', 'hours' => 'Horas', 'days' => 'Días', 'weeks' => 'Semanas', 'months' => 'Meses', 'years' => 'Años'];

    public function rows(Model $source): array
    {
        return $this->query($source)->orderBy('id')->get()->map(fn ($r) => ['id' => $r->id, 'amount' => $r->amount, 'unit' => $r->unit])->all();
    }

    public function validate(array $rows, bool $hasAnchor): void
    {
        Validator::make(['reminders' => $rows], [
            'reminders' => ['array'], 'reminders.*.amount' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'reminders.*.unit' => ['required', Rule::in(array_keys(self::UNITS))],
            'reminders.*.id' => ['nullable', 'uuid', 'distinct:strict'],
        ])->validate();
        $pairs = array_map(fn ($row) => ((int) $row['amount']).':'.$row['unit'], $rows);
        if (count($pairs) !== count(array_unique($pairs))) {
            throw ValidationException::withMessages(['reminders' => 'No repitas la misma anticipación.']);
        }
        if ($rows && ! $hasAnchor) {
            throw ValidationException::withMessages(['reminders' => 'Define primero la fecha del elemento para configurar recordatorios.']);
        }
    }

    public function sync(Model $source, array $rows): void
    {
        abort_unless(in_array($source::class, [\App\Models\Task::class, \App\Models\Plan::class, \App\Models\Goal::class], true), 422);
        $helper = app(NotificationSource::class);
        $this->validate($rows, $helper->anchor($source) !== null);
        DB::transaction(function () use ($source, $rows, $helper) {
            User::whereKey($source->user_id)->lockForUpdate()->firstOrFail();
            $existing = $this->query($source)->get()->keyBy('id');
            $ids = array_filter(array_column($rows, 'id'));
            foreach ($ids as $id) {
                abort_unless($existing->has($id), 404);
            }
            foreach ($existing->except($ids) as $removed) {
                $this->cancelReminder($source, $removed->id);
            }
            // Remove the old configurations first so swapping anticipations is valid.
            $this->query($source)->delete();
            foreach ($rows as $row) {
                $reminder = filled($row['id'] ?? null) ? $existing->get($row['id']) : null;
                abort_if(filled($row['id'] ?? null) && ! $reminder, 404);
                $reminder ??= new Reminder(['user_id' => $source->user_id, 'source_type' => $helper->type($source), 'source_id' => $source->id]);
                if ($reminder->exists && ($reminder->amount !== (int) $row['amount'] || $reminder->unit !== $row['unit'])) {
                    $this->cancelReminder($source, $reminder->id);
                }
                $reminder->amount = (int) $row['amount'];
                $reminder->unit = $row['unit'];
                $this->calculate($helper->anchor($source), $reminder->amount, $reminder->unit);
                $reminder->exists = false;
                $reminder->save();
            }
        }, 3);
    }

    public function calculate(CarbonImmutable $anchor, int $amount, string $unit): CarbonImmutable
    {
        try {
            $date = match ($unit) {
                'months' => $anchor->subMonthsNoOverflow($amount),
                'years' => $anchor->subYearsNoOverflow($amount),
                default => $anchor->sub($unit, $amount),
            };
            if ($date->year < 1970 || $date->year > 9999) {
                throw new \InvalidArgumentException;
            }

            return $date->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['reminders' => 'La anticipación queda fuera del rango de fechas admitido (1970–9999).']);
        }
    }

    public function refresh(Model $source, bool $deleted = false): void
    {
        DB::transaction(function () use ($source, $deleted) {
            User::whereKey($source->user_id)->lockForUpdate()->firstOrFail();
            $this->refreshLocked($source, $deleted);
        }, 3);
    }

    private function refreshLocked(Model $source, bool $deleted): void
    {
        app(NotificationSource::class)->cancelFor($source);
        if ($deleted) {
            $this->query($source)->delete();
        }
    }

    public function materialize(Model $source, Reminder $reminder): ?UserNotification
    {
        $helper = app(NotificationSource::class);
        $anchor = $helper->anchor($source);
        if (! $anchor || ! $helper->active($source)) {
            return null;
        }
        $scheduled = $this->calculate($anchor, $reminder->amount, $reminder->unit);
        $now = CarbonImmutable::now(NotificationSource::TIMEZONE);
        if ($scheduled->timezone(NotificationSource::TIMEZONE)->toDateString() !== $now->toDateString() || $scheduled->isAfter($now)) {
            return null;
        }

        return app(CreateNotification::class)->create(User::findOrFail($source->user_id), $source,
            'reminder', $reminder->id.':'.$scheduled->format('Y-m-d H:i:s'),
            'Recordatorio', 'Tienes un recordatorio de '.match ($source::class) {
                \App\Models\Task::class => 'una tarea.', \App\Models\Plan::class => 'un plan.', default => 'una meta.',
            }, $scheduled);
    }

    private function cancelReminder(Model $source, string $id): void
    {
        app(NotificationSource::class)->cancelFor($source, $id);
    }

    private function query(Model $source)
    {
        return Reminder::withoutGlobalScopes()->where('user_id', $source->user_id)->where('source_type', app(NotificationSource::class)->type($source))->where('source_id', $source->id);
    }
}
