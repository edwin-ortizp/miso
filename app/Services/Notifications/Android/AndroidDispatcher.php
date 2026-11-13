<?php

namespace App\Services\Notifications\Android;

use App\Models\AndroidDelivery;
use App\Models\AndroidInstallation;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\DB;

class AndroidDispatcher
{
    public function __construct(private FcmGateway $gateway, private NotificationPreferences $preferences) {}

    public function run(bool $dryRun = false, ?float $maxSeconds = null, ?int $maxSends = null): array
    {
        $r = ['sent' => 0, 'failed' => 0, 'problem' => $this->gateway->problem()];
        if ($r['problem']) {
            return $r;
        }
        $started = microtime(true);
        $budget = $maxSends ?? (int) config('game.nudges.max_sends', 200);
        $maxSeconds ??= (float) config('game.nudges.max_seconds', 40);
        if (! $dryRun) {
            AndroidDelivery::where('status', 'sending')->where('claimed_at', '<', now('UTC')->subMinutes(5))->update(['status' => 'failed']);
        }
        $count = 0;
        foreach (AndroidInstallation::where('active', true)->lazyById(100) as $device) {
            if (microtime(true) - $started >= $maxSeconds) {
                return $r;
            }
            $prefs = $this->preferences->get($device->user_id);
            $notices = UserNotification::withoutGlobalScopes()->where('user_id', $device->user_id)
                ->whereNull('read_at')->whereNull('cancelled_at')->where('scheduled_for', '<=', now('UTC'))
                ->where('scheduled_for', '>=', $device->activated_at)
                ->where('scheduled_for', '>=', now('UTC')->subHours(config('game.nudges.max_age_hours', 12)))
                ->whereNotExists(function ($q) use ($device) {
                    $q->selectRaw('1')->from('android_deliveries')->whereColumn('android_deliveries.notification_id', 'notifications.id')
                        ->where('installation_id', $device->id)->where('binding', $device->binding)
                        ->where(fn ($x) => $x->whereIn('status', ['sent', 'failed', 'sending'])
                            ->orWhere(fn ($y) => $y->where('status', 'retry')->where('next_attempt_at', '>', now('UTC'))));
                })->lazyById(100);
            foreach ($notices as $notice) {
                if (++$count > $budget || microtime(true) - $started >= $maxSeconds) {
                    return $r;
                }
                if (! NotificationPreferences::pushAllowed($prefs, $notice->type)) {
                    continue;
                }
                $identity = ['installation_id' => $device->id, 'notification_id' => $notice->id, 'binding' => $device->binding];
                if ($dryRun) {
                    if (! AndroidDelivery::where($identity)->whereIn('status', ['sent', 'failed', 'sending'])->exists()) {
                        $r['sent']++;
                    }

                    continue;
                }
                AndroidDelivery::firstOrCreate($identity);
                // Conditional claim: another cron cannot send the same attempt.
                $query = AndroidDelivery::where($identity)->whereIn('status', ['pending', 'retry'])->where('attempts', '<', 3)
                    ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now('UTC')));
                if (! $query->update(['status' => 'sending', 'claimed_at' => now('UTC'), 'attempts' => DB::raw('attempts + 1')])) {
                    continue;
                }
                $delivery = AndroidDelivery::where($identity)->firstOrFail();
                $device->refresh();
                if (! $device->active || $device->binding !== $delivery->binding || $device->user_id !== $notice->user_id) {
                    $delivery->update(['status' => 'failed']);

                    continue;
                }
                $status = $this->gateway->send($device->token, ['version' => '1', 'notice_id' => $notice->id, 'binding' => $device->binding]);
                if ($status === 'expired') {
                    $device->update(['active' => false]);
                }
                $retry = $status === 'transient' && $delivery->attempts < 3;
                $delivery->update(['status' => $status === 'sent' ? 'sent' : ($retry ? 'retry' : 'failed'),
                    'accepted_at' => $status === 'sent' ? now('UTC') : null,
                    'next_attempt_at' => $retry ? now('UTC')->addMinutes($delivery->attempts === 1 ? 1 : 5) : null]);
                $status === 'sent' ? $r['sent']++ : $r['failed']++;
            }
        }

        return $r;
    }
}
