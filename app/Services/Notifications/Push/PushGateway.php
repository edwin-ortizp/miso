<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;

/** Canal de entrega (CHG-068). Telegram o WhatsApp serían otra implementación con su columna `*_sent_at`. */
interface PushGateway
{
    public const SENT = 'sent';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    /** @param  array{title: string, body: string, url: string, icon?: ?string, tag?: string}  $payload */
    public function send(PushSubscription $subscription, array $payload): string;

    /** Por qué no se puede enviar (claves o extensiones faltantes), o null si está listo. */
    public function problem(): ?string;
}
