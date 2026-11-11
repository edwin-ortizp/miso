<?php

namespace App\Http\Controllers\Push;

use App\Actions\LogDrink;
use App\Http\Controllers\Controller;
use App\Models\DrinkType;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * CHG-069: "+1 vaso" desde el aviso push, sin abrir la app. Lo autoriza la URL
 * firmada (12 h, una por notificación), no la sesión. La notificación se reclama
 * con un UPDATE condicional sobre `action_done_at`: repetir la petición no suma otro vaso.
 */
class QuickWaterController extends Controller
{
    public function __invoke(string $notification): JsonResponse
    {
        $notice = UserNotification::withoutGlobalScopes()->findOrFail($notification);
        $claimed = UserNotification::withoutGlobalScopes()->whereKey($notice->id)
            ->whereNull('action_done_at')->update(['action_done_at' => now('UTC')]);
        if ($claimed !== 1) {
            return response()->json(['status' => 'already-done']);
        }

        $user = User::findOrFail($notice->user_id);
        $previous = Auth::user();
        Auth::setUser($user);
        try {
            $water = DrinkType::where('name', 'like', '%agua%')->orderBy('name')->first() ?? DrinkType::orderBy('name')->first();
            if ($water) {
                LogDrink::handle($water, now()->toDateString(), 250);
            }
        } finally {
            $previous ? Auth::setUser($previous) : Auth::forgetUser();
        }

        return response()->json(['status' => $water ? 'logged' : 'no-drink']);
    }
}
