<?php

namespace App\Http\Controllers\Push;

use App\Actions\LogMedicationIntake;
use App\Http\Controllers\Controller;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\GenerateMedicationReminders;
use App\Services\Notifications\NotificationSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * CHG-130: «Tomado» desde el aviso push de un medicamento, sin abrir la app. Lo autoriza la URL
 * firmada (vence al final del día local de la toma, una por notificación), no la sesión.
 * La notificación se reclama con un UPDATE condicional sobre `action_done_at`: repetir la
 * petición no registra otra toma. Si el registro no se puede hacer, el reclamo se libera.
 */
class QuickMedicationController extends Controller
{
    public function __invoke(string $notification): JsonResponse
    {
        $notice = UserNotification::withoutGlobalScopes()
            ->where('type', GenerateMedicationReminders::TYPE)->whereKey($notification)->firstOrFail();

        $claimed = UserNotification::withoutGlobalScopes()->whereKey($notice->id)
            ->whereNull('action_done_at')->update(['action_done_at' => now('UTC')]);
        if ($claimed !== 1) {
            return response()->json(['status' => 'already-done']);
        }

        $user = User::findOrFail($notice->user_id);
        $previous = Auth::user();
        Auth::setUser($user);
        try {
            $medication = Medication::find($notice->source_id);
            $slot = Carbon::createFromFormat('Y-m-d:H:i', (string) $notice->occurrence_key, NotificationSource::TIMEZONE);

            if (! $medication || ! $medication->is_active || ! $slot) {
                $this->release($notice);

                return response()->json(['status' => 'unavailable']);
            }

            $alreadyTaken = MedicationIntake::where('medication_id', $medication->id)
                ->where('scheduled_for', $slot->copy()->startOfMinute())->where('status', 'taken')->exists();
            if (! $alreadyTaken) {
                LogMedicationIntake::take($medication, $slot, now(), ['source' => 'push']);
            }

            return response()->json(['status' => $alreadyTaken ? 'already-done' : 'logged']);
        } catch (\Throwable $e) {
            $this->release($notice);

            throw $e;
        } finally {
            $previous ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }

    private function release(UserNotification $notice): void
    {
        UserNotification::withoutGlobalScopes()->whereKey($notice->id)->update(['action_done_at' => null]);
    }
}
