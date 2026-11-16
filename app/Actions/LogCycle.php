<?php

namespace App\Actions;

use App\Models\CycleDayLog;
use App\Models\MenstrualCycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Inicio y fin del periodo y registro diario del ciclo (CHG-130). Compartida por la pantalla y MCP.
 * Todo queda sensible por defecto; las fechas se interpretan en la zona de la aplicación.
 */
class LogCycle
{
    /** Cae dentro del periodo [inicio, fin] de otro ciclo (el abierto cubre hasta hoy). */
    private static function coveringCycle(Carbon $date, ?MenstrualCycle $except = null): ?MenstrualCycle
    {
        return MenstrualCycle::when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->whereDate('started_on', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereDate('ended_on', '>=', $date->toDateString())->orWhereNull('ended_on'))
            ->orderByDesc('started_on')->get()
            ->first(fn (MenstrualCycle $c) => $c->isOpen() ? $date->toDateString() <= self::today()->toDateString() : true);
    }

    public static function today(): Carbon
    {
        return now(config('app.timezone'))->startOfDay();
    }

    private static function date(mixed $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value, config('app.timezone'))->startOfDay() : null;
    }

    /** @throws ValidationException */
    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /**
     * Marca el inicio de un periodo. Repetir la misma fecha devuelve el periodo existente.
     *
     * @throws ValidationException
     */
    public static function start(?string $startedOn = null, ?string $notes = null, bool $sensitive = true, ?string $source = null): MenstrualCycle
    {
        Validator::make(['notes' => $notes], ['notes' => ['nullable', 'string', 'max:1000']])->validate();
        $date = self::date($startedOn) ?? self::today();
        if ($date->gt(self::today())) {
            self::fail('started_on', 'La fecha no puede ser futura.');
        }
        if ($same = MenstrualCycle::whereDate('started_on', $date->toDateString())->first()) {
            return $same;
        }
        if (MenstrualCycle::whereNull('ended_on')->exists()) {
            self::fail('started_on', 'Ya tienes un periodo en curso. Márcalo como terminado o corrige su fecha.');
        }
        if (self::coveringCycle($date)) {
            self::fail('started_on', 'Esa fecha cae dentro de otro periodo registrado.');
        }

        return MenstrualCycle::create(['started_on' => $date, 'notes' => filled($notes) ? $notes : null, 'is_sensitive' => $sensitive, 'source' => $source]);
    }

    /**
     * Marca el fin del periodo abierto.
     *
     * @throws ValidationException
     */
    public static function end(?string $endedOn = null): MenstrualCycle
    {
        $cycle = MenstrualCycle::whereNull('ended_on')->orderByDesc('started_on')->first();
        if (! $cycle) {
            self::fail('ended_on', 'No tienes un periodo en curso. Marca primero cuándo empezó.');
        }

        return self::setEnd($cycle, self::date($endedOn) ?? self::today());
    }

    /**
     * Corrige las fechas de un periodo.
     *
     * @throws ValidationException
     */
    public static function update(MenstrualCycle $cycle, string $startedOn, ?string $endedOn, ?string $notes = null): MenstrualCycle
    {
        Validator::make(['notes' => $notes], ['notes' => ['nullable', 'string', 'max:1000']])->validate();
        $start = self::date($startedOn) ?? self::fail('started_on', 'Indica cuándo empezó el periodo.');
        if ($start->gt(self::today())) {
            self::fail('started_on', 'La fecha no puede ser futura.');
        }
        if (MenstrualCycle::whereKeyNot($cycle->id)->whereDate('started_on', $start->toDateString())->exists()
            || self::coveringCycle($start, $cycle)) {
            self::fail('started_on', 'Esa fecha cae dentro de otro periodo registrado.');
        }
        $cycle->started_on = $start;
        $cycle->notes = filled($notes) ? $notes : null;
        $end = self::date($endedOn);
        if ($end === null) {
            $cycle->ended_on = null;
            $cycle->save();

            return $cycle;
        }

        return self::setEnd($cycle, $end);
    }

    private static function setEnd(MenstrualCycle $cycle, Carbon $end): MenstrualCycle
    {
        $start = $cycle->started_on->copy()->startOfDay();
        if ($end->gt(self::today())) {
            self::fail('ended_on', 'La fecha no puede ser futura.');
        }
        if ($end->lt($start) || $start->diffInDays($end) + 1 > MenstrualCycle::MAX_PERIOD_DAYS) {
            self::fail('ended_on', 'El fin del periodo no puede ser anterior al inicio ni durar más de '.MenstrualCycle::MAX_PERIOD_DAYS.' días.');
        }
        $next = MenstrualCycle::whereKeyNot($cycle->id)->whereDate('started_on', '>', $start->toDateString())->orderBy('started_on')->first();
        if ($next && $end->gte($next->started_on->copy()->startOfDay())) {
            self::fail('ended_on', 'Esa fecha cae dentro de otro periodo registrado.');
        }
        $cycle->ended_on = $end;
        $cycle->save();

        return $cycle;
    }

    /**
     * Registra (o actualiza) el flujo y los síntomas de un día.
     *
     * @param  list<string>  $symptoms
     *
     * @throws ValidationException
     */
    public static function day(?string $date, ?string $flow, array $symptoms = [], ?string $notes = null, bool $sensitive = true, ?string $source = null): CycleDayLog
    {
        Validator::make(['flow' => $flow, 'symptoms' => $symptoms, 'notes' => $notes], [
            'flow' => ['nullable', 'in:'.implode(',', array_keys(CycleDayLog::FLOWS))],
            'symptoms' => ['array'],
            'symptoms.*' => ['in:'.implode(',', array_keys(CycleDayLog::SYMPTOMS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'flow.in' => 'Elige un flujo válido.',
            'symptoms.*.in' => 'Elige síntomas de la lista.',
        ])->validate();

        $day = self::date($date) ?? self::today();
        if ($day->gt(self::today())) {
            self::fail('date', 'La fecha no puede ser futura.');
        }
        if (blank($flow) && $symptoms === [] && blank($notes)) {
            self::fail('flow', 'Indica el flujo, algún síntoma o una nota.');
        }

        $attributes = [
            'flow' => filled($flow) ? $flow : null,
            'symptoms' => array_values(array_unique($symptoms)),
            'notes' => filled($notes) ? $notes : null,
            'is_sensitive' => $sensitive,
            'source' => $source,
        ];

        $log = CycleDayLog::whereDate('date', $day->toDateString())->first();
        if ($log) {
            $log->update($attributes);

            return $log;
        }

        return CycleDayLog::create([...$attributes, 'date' => $day]);
    }
}
