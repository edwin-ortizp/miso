<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\Log;

/**
 * Entrega por push las notificaciones disponibles (CHG-068). Cada fila se RECLAMA
 * con un UPDATE condicional sobre `push_sent_at` antes de enviar: dos corridas a la
 * vez (cron duplicado, ejecución manual) nunca envían la misma notificación dos veces.
 * Un fallo no se reintenta, para no duplicar ni insistir.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly PushGateway $gateway,
        private readonly NotificationPreferences $preferences,
    ) {}

    /** @return array{sent: int, failed: int, skipped: int, expired: int, stopped: bool, problem: ?string} */
    public function run(bool $dryRun = false): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'expired' => 0, 'stopped' => false, 'problem' => $this->gateway->problem()];
        if ($result['problem'] !== null) {
            Log::warning('Push sin configurar: '.$result['problem']);

            return $result;
        }

        $started = microtime(true);
        $maxSeconds = (float) config('game.nudges.max_seconds', 40);
        $maxSends = (int) config('game.nudges.max_sends', 200);
        $prefs = [];
        $now = now('UTC');

        UserNotification::withoutGlobalScopes()
            ->whereNull('push_sent_at')->whereNull('cancelled_at')->whereNull('read_at')
            ->where('scheduled_for', '<=', $now)
            ->where('scheduled_for', '>=', $now->copy()->subHours((int) config('game.nudges.max_age_hours', 12)))
            ->whereIn('user_id', PushSubscription::select('user_id'))
            ->orderBy('scheduled_for')
            ->chunkById(100, function ($notices) use (&$result, &$prefs, $dryRun, $started, $maxSeconds, $maxSends) {
                foreach ($notices as $notice) {
                    if (microtime(true) - $started > $maxSeconds || $result['sent'] + $result['failed'] >= $maxSends) {
                        $result['stopped'] = true;

                        return false;
                    }
                    $prefs[$notice->user_id] ??= $this->preferences->get($notice->user_id);
                    if (! NotificationPreferences::pushAllowed($prefs[$notice->user_id], $notice->type)) {
                        $result['skipped']++;

                        continue;
                    }
                    if ($dryRun) {
                        $result['sent']++;

                        continue;
                    }
                    $claimed = UserNotification::withoutGlobalScopes()->whereKey($notice->id)
                        ->whereNull('push_sent_at')->update(['push_sent_at' => now('UTC')]);
                    if ($claimed !== 1) {
                        continue;
                    }
                    $this->deliver($notice, $result);
                }
            });

        return $result;
    }

    private function deliver(UserNotification $notice, array &$result): void
    {
        $payload = [
            'title' => $notice->title,
            'body' => $notice->body,
            'url' => $this->withVia($notice->destination()),
            'tag' => $notice->type.':'.$notice->occurrence_key,
        ] + self::actions($notice);
        $ok = false;
        foreach (PushSubscription::where('user_id', $notice->user_id)->get() as $subscription) {
            $status = $this->gateway->send($subscription, $payload);
            if ($status === PushGateway::EXPIRED) {
                $subscription->delete();
                $result['expired']++;
            }
            $ok = $ok || $status === PushGateway::SENT;
        }
        $ok ? $result['sent']++ : $result['failed']++;
    }

    /**
     * CHG-069: botones del aviso. Agua → "+1 vaso" (registra sin abrir la app, URL firmada 12 h);
     * el resto → "Check-in". Los navegadores sin acciones (iPhone) ignoran estos campos.
     *
     * @return array{actions: list<array{action: string, title: string}>, quick_url?: string, checkin_url: string}
     */
    public static function actions(UserNotification $notice): array
    {
        $checkin = route('home', ['checkin' => 1, 'via' => 'checkin']);
        $isWater = $notice->type === GuardianNudgePlanner::TYPE
            && GuardianNudgePlanner::moduleFor((string) $notice->source_id) === 'water';

        if ($isWater) {
            return [
                'actions' => [['action' => 'quick-water', 'title' => '+1 vaso'], ['action' => 'open', 'title' => 'Abrir']],
                'quick_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('push.quick-water', now()->addHours(12), ['notification' => $notice->id]),
                'checkin_url' => $checkin,
            ];
        }

        return [
            'actions' => [['action' => 'checkin', 'title' => 'Check-in'], ['action' => 'open', 'title' => 'Abrir']],
            'checkin_url' => $checkin,
        ];
    }

    private function withVia(string $url): string
    {
        [$path, $hash] = array_pad(explode('#', $url, 2), 2, null);
        // CHG-070: si el destino ya trae su canal (p. ej. ?via=close), se respeta.
        if (preg_match('/[?&]via=/', $path)) {
            return $url;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').'via=push'.($hash !== null ? '#'.$hash : '');
    }
}
