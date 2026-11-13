<?php

namespace App\Http\Controllers\Android;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationSource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __invoke(Request $request, string $notification)
    {
        $notice = UserNotification::where('user_id', $request->user()->id)->whereNull('cancelled_at')
            ->where('scheduled_for', '<=', now('UTC'))->find($notification);
        if (! $notice) {
            return response()->view('android.notice-unavailable', [], 404);
        }
        if (array_key_exists($notice->source_type, NotificationSource::TYPES)
            && ! app(NotificationSource::class)->find($request->user()->id, $notice->source_type, $notice->source_id)) {
            return response()->view('android.notice-unavailable', [], 404);
        }
        $destination = $notice->destination();
        abort_unless(parse_url($destination, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST), 403);
        [$path, $fragment] = array_pad(explode('#', $destination, 2), 2, null);
        if (! preg_match('/[?&]via=/', $path)) {
            $path .= (str_contains($path, '?') ? '&' : '?').'via=push';
        }

        return redirect($path.($fragment !== null ? '#'.$fragment : ''));
    }
}
