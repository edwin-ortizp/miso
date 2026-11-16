<?php

namespace App\Services\Notifications;

use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\MedicationSchedule;
use Carbon\CarbonInterface;

/**
 * CHG-130: crea el aviso `medication_due` de cada toma de hoy cuya hora ya llegó y sigue sin registrar.
 *
 * Corre dentro de `notifications:generate` (sin cron nuevo). Es idempotente: la clave única de
 * `notifications` (usuario, origen, tipo y ocurrencia) deja una sola fila por toma. No reconstruye
 * días anteriores y no pasa por la compuerta de las 9:00 de los demás avisos.
 */
class GenerateMedicationReminders
{
    public const TYPE = 'medication_due';

    /** Pasada esta ventana el aviso queda en la campana pero no sale por push. */
    public const PUSH_WINDOW_MINUTES = 60;

    /** @return int Avisos creados en esta corrida. */
    public function run(User $user, ?CarbonInterface $now = null): int
    {
        $now = ($now ?? now())->copy()->setTimezone(NotificationSource::TIMEZONE);
        $today = $now->copy()->startOfDay();
        $create = app(CreateNotification::class);
        $created = 0;

        Medication::withoutGlobalScopes()->where('user_id', $user->id)->where('is_active', true)
            ->eachById(function (Medication $medication) use ($user, $now, $today, $create, &$created) {
                // Las tomas esperadas se calculan en la zona de la aplicación (Bogotá); el aviso se guarda en UTC.
                $slots = MedicationSchedule::slots($medication, $today)->filter(fn ($slot) => $slot->lte($now));
                if ($slots->isEmpty()) {
                    return;
                }

                $registered = MedicationIntake::withoutGlobalScopes()->where('user_id', $user->id)
                    ->where('medication_id', $medication->id)
                    ->whereBetween('scheduled_for', [$today, $today->copy()->endOfDay()])
                    ->pluck('scheduled_for')->map(fn ($date) => $date->format('H:i'))->all();

                foreach ($slots as $slot) {
                    if (in_array($slot->format('H:i'), $registered, true)) {
                        continue;
                    }
                    $notice = $create->create(
                        $user, $medication, self::TYPE, $slot->format('Y-m-d:H:i'),
                        $this->title($medication), 'Toma programada a las '.$slot->format('H:i').'.',
                        $slot,
                    );
                    if ($notice) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    /** Cancela el aviso de una toma concreta (p. ej. porque ya se registró). */
    public static function cancelSlot(Medication $medication, CarbonInterface $slot): void
    {
        $key = $slot->copy()->setTimezone(NotificationSource::TIMEZONE)->format('Y-m-d:H:i');

        self::pending($medication)->where('occurrence_key', $key)->update(['cancelled_at' => now('UTC')]);
    }

    /** Cancela los avisos de hoy de un medicamento (cambió su programación o se eliminó). */
    public static function cancelToday(Medication $medication): void
    {
        $today = now(NotificationSource::TIMEZONE)->toDateString();

        self::pending($medication)->where('occurrence_key', 'like', $today.':%')->update(['cancelled_at' => now('UTC')]);
    }

    private static function pending(Medication $medication)
    {
        return UserNotification::withoutGlobalScopes()->where('user_id', $medication->user_id)
            ->where('source_type', 'medication')->where('source_id', $medication->id)
            ->where('type', self::TYPE)->whereNull('cancelled_at');
    }

    /** Título del aviso: genérico si el medicamento es sensible (se ve en la pantalla bloqueada). */
    private function title(Medication $medication): string
    {
        if ($medication->is_sensitive) {
            return 'Es hora de una toma programada';
        }

        return mb_strimwidth('Es hora de tu '.trim($medication->name.' '.($medication->doseText() ?? '')), 0, 80, '…');
    }
}
