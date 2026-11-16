<?php

namespace App\Actions;

use App\Models\SleepLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Registra o actualiza un periodo de sueño (CHG-129). Compartida por la pantalla y las herramientas MCP.
 * Repetir un registro idéntico devuelve el existente en lugar de duplicarlo.
 */
class LogSleep
{
    public const MIN_MINUTES = 10;

    public const MAX_MINUTES = 24 * 60;

    /**
     * @param  array<string, mixed>  $data  slept_at, woke_at, quality, notes, is_sensitive
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data, ?SleepLog $editing = null): array
    {
        $validator = Validator::make($data, [
            'slept_at' => ['required', 'date'],
            'woke_at' => ['required', 'date'],
            'quality' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
        ], [
            'slept_at.required' => 'Indica a qué hora te dormiste.',
            'slept_at.date' => 'Indica una fecha y hora válidas para dormir.',
            'woke_at.required' => 'Indica a qué hora despertaste.',
            'woke_at.date' => 'Indica una fecha y hora válidas para despertar.',
            'quality.between' => 'La calidad debe estar entre 1 y 5.',
        ]);

        $validator->after(function ($validator) use ($data, $editing) {
            if ($validator->errors()->hasAny(['slept_at', 'woke_at'])) {
                return;
            }
            [$slept, $woke] = [self::parse($data['slept_at']), self::parse($data['woke_at'])];

            if ($woke->lte($slept)) {
                $validator->errors()->add('woke_at', 'La hora de despertar debe ser posterior a la hora de dormir.');

                return;
            }
            $minutes = $slept->diffInMinutes($woke);
            if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
                $validator->errors()->add('woke_at', 'La duración debe estar entre 10 minutos y 24 horas.');

                return;
            }
            if (self::identical($slept, $woke, $editing) === null && self::overlapping($slept, $woke, $editing)) {
                $validator->errors()->add('slept_at', 'Ese horario se cruza con otro registro de sueño.');
            }
        });

        return $validator->validate();
    }

    /** @param  array<string, mixed>  $data  Datos validados con validate(). */
    public static function handle(array $data, ?string $source = null): SleepLog
    {
        [$slept, $woke] = [self::parse($data['slept_at']), self::parse($data['woke_at'])];

        return self::identical($slept, $woke) ?? SleepLog::create([...self::attributes($data), 'slept_at' => $slept, 'woke_at' => $woke, 'source' => $source]);
    }

    /** @param  array<string, mixed>  $data */
    public static function update(SleepLog $log, array $data): SleepLog
    {
        $log->update([...self::attributes($data), 'slept_at' => self::parse($data['slept_at']), 'woke_at' => self::parse($data['woke_at'])]);

        return $log;
    }

    private static function parse(mixed $value): Carbon
    {
        return Carbon::parse($value, config('app.timezone'))->startOfMinute();
    }

    /** @return array<string, mixed> */
    private static function attributes(array $data): array
    {
        return [
            'quality' => filled($data['quality'] ?? null) ? (int) $data['quality'] : null,
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
        ];
    }

    private static function identical(Carbon $slept, Carbon $woke, ?SleepLog $editing = null): ?SleepLog
    {
        return SleepLog::where('slept_at', $slept)->where('woke_at', $woke)
            ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))->first();
    }

    private static function overlapping(Carbon $slept, Carbon $woke, ?SleepLog $editing): bool
    {
        return SleepLog::where('slept_at', '<', $woke)->where('woke_at', '>', $slept)
            ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))->exists();
    }
}
