<?php

namespace App\Observers;

use App\Models\Medication;
use App\Services\Notifications\GenerateMedicationReminders;

/**
 * CHG-130: si cambia la programación de un medicamento, se cancelan los avisos de hoy que ya no
 * corresponden; el generador vuelve a crear (o reabrir) los que siguen vigentes en la siguiente corrida.
 */
class MedicationReminderObserver
{
    public function updated(Medication $medication): void
    {
        if ($medication->wasChanged(['is_active', 'starts_on', 'ends_on', 'times', 'frequency_type', 'interval_hours', 'weekdays'])) {
            GenerateMedicationReminders::cancelToday($medication);
        }
    }

    public function deleted(Medication $medication): void
    {
        GenerateMedicationReminders::cancelToday($medication);
    }
}
