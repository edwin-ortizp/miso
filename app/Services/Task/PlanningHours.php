<?php

namespace App\Services\Task;

use App\Models\ModuleSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * CHG-142: horario en el que cada persona quiere que se le propongan horarios para sus tareas.
 * Se guarda por usuario en `module_settings` (módulo `tasks`, clave `planning`); sin ajustes se usan los
 * valores por defecto. Las horas se interpretan en la zona de la aplicación (America/Bogota).
 */
class PlanningHours
{
    /** Orden de presentación: lunes a domingo (valores de `dayOfWeek` de Carbon). */
    public const DAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];

    public const MARGINS = [0, 5, 10, 15, 30];

    /** @var array<int, array{days: array<int, array{enabled: bool, start: string, end: string}>, margin: int}> */
    private array $cache = [];

    public static function defaults(): array
    {
        $days = [];
        foreach (array_keys(self::DAYS) as $day) {
            $days[$day] = match (true) {
                $day === 0 => ['enabled' => false, 'start' => '09:00', 'end' => '12:00'],
                $day === 6 => ['enabled' => true, 'start' => '09:00', 'end' => '12:00'],
                default => ['enabled' => true, 'start' => '08:00', 'end' => '18:00'],
            };
        }

        return ['days' => $days, 'margin' => 10];
    }

    /** @return array{days: array<int, array{enabled: bool, start: string, end: string}>, margin: int} */
    public function settings(int $userId): array
    {
        return $this->cache[$userId] ??= $this->load($userId);
    }

    /** Ventana planificable de un día, o null si la persona no planifica ese día. */
    public function window(int $userId, CarbonInterface $day): ?array
    {
        $date = Carbon::parse($day->toDateString(), config('app.timezone'))->startOfDay();
        $slot = $this->settings($userId)['days'][$date->dayOfWeek] ?? null;
        if (! $slot || ! $slot['enabled']) {
            return null;
        }
        $start = $date->copy()->setTimeFromTimeString($slot['start']);
        $end = $date->copy()->setTimeFromTimeString($slot['end']);

        return $end->gt($start) ? ['start' => $start, 'end' => $end] : null;
    }

    public function margin(int $userId): int
    {
        return $this->settings($userId)['margin'];
    }

    /** Valida y guarda el horario sin tocar las demás claves de los ajustes de Tareas. */
    public function save(int $userId, array $days, int|string $margin): array
    {
        $errors = [];
        $clean = [];
        foreach (array_keys(self::DAYS) as $day) {
            $row = $days[$day] ?? [];
            $enabled = filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL);
            $start = (string) ($row['start'] ?? '');
            $end = (string) ($row['end'] ?? '');
            if ($enabled) {
                if (! self::validTime($start) || ! self::validTime($end)) {
                    $errors["planningDays.{$day}.start"] = 'Escribe las horas como HH:MM.';
                } elseif ($end <= $start) {
                    $errors["planningDays.{$day}.end"] = 'La hora de fin debe ser posterior a la de inicio.';
                }
            }
            $defaults = self::defaults()['days'][$day];
            $clean[$day] = [
                'enabled' => $enabled,
                'start' => self::validTime($start) ? $start : $defaults['start'],
                'end' => self::validTime($end) ? $end : $defaults['end'],
            ];
        }
        if (! in_array((int) $margin, self::MARGINS, true)) {
            $errors['planningMargin'] = 'Elige un margen disponible.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $setting = ModuleSetting::withoutGlobalScopes()->firstOrNew(['user_id' => $userId, 'module' => 'tasks']);
        $setting->user_id = $userId;
        $setting->settings = [...($setting->settings ?? []), 'planning' => ['days' => $clean, 'margin' => (int) $margin]];
        $setting->save();
        unset($this->cache[$userId]);

        return $this->settings($userId);
    }

    /** Vuelve a los valores por defecto (borra solo la clave `planning`). */
    public function reset(int $userId): void
    {
        $setting = ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', 'tasks')->first();
        if ($setting && array_key_exists('planning', $setting->settings ?? [])) {
            $settings = $setting->settings;
            unset($settings['planning']);
            $setting->settings = $settings;
            $setting->save();
        }
        unset($this->cache[$userId]);
    }

    private function load(int $userId): array
    {
        $stored = ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', 'tasks')->first()?->settings['planning'] ?? [];
        $defaults = self::defaults();
        $days = [];
        foreach ($defaults['days'] as $day => $default) {
            $row = is_array($stored['days'][$day] ?? null) ? $stored['days'][$day] : [];
            $days[$day] = [
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : $default['enabled'],
                'start' => self::validTime($row['start'] ?? null) ? $row['start'] : $default['start'],
                'end' => self::validTime($row['end'] ?? null) ? $row['end'] : $default['end'],
            ];
        }
        $margin = (int) ($stored['margin'] ?? $defaults['margin']);

        return ['days' => $days, 'margin' => in_array($margin, self::MARGINS, true) ? $margin : $defaults['margin']];
    }

    private static function validTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
