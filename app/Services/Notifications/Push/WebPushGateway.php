<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/** Web Push con VAPID (minishlink/web-push), sin colas: apto para hosting compartido. */
class WebPushGateway implements PushGateway
{
    private ?WebPush $client = null;

    public function problem(): ?string
    {
        foreach (['openssl', 'curl', 'mbstring'] as $extension) {
            if (! extension_loaded($extension)) {
                return "Falta la extensión PHP {$extension}.";
            }
        }
        if (! config('webpush.public_key') || ! config('webpush.private_key')) {
            return 'Faltan VAPID_PUBLIC_KEY y VAPID_PRIVATE_KEY en .env (php artisan miso:vapid).';
        }

        return null;
    }

    public function send(PushSubscription $subscription, array $payload): string
    {
        try {
            $report = $this->client()->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ['TTL' => 3600, 'urgency' => 'normal'],
            );
        } catch (Throwable $e) {
            report($e);

            return self::FAILED;
        }

        if ($report->isSuccess()) {
            return self::SENT;
        }

        return $report->isSubscriptionExpired() ? self::EXPIRED : self::FAILED;
    }

    private function client(): WebPush
    {
        return $this->client ??= new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.subject'),
                'publicKey' => (string) config('webpush.public_key'),
                'privateKey' => (string) config('webpush.private_key'),
            ],
        ], [], new \GuzzleHttp\Client(['timeout' => (int) config('webpush.timeout', 10)]));
    }
}
