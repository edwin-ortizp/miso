<?php

namespace Tests\Feature\Notifications;

use App\Models\PushSubscription;
use App\Services\Notifications\Push\PushGateway;

/** Doble del canal push: registra los envíos y devuelve el estado configurado. */
class FakePushGateway implements PushGateway
{
    public array $sent = [];

    public function __construct(public string $status = self::SENT, public ?string $problem = null) {}

    public function send(PushSubscription $subscription, array $payload): string
    {
        $this->sent[] = ['endpoint' => $subscription->endpoint, 'payload' => $payload];

        return $this->status;
    }

    public function problem(): ?string
    {
        return $this->problem;
    }
}
