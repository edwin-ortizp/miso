<?php

namespace App\Services\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Avisos de amistad en la campanita (CHG-125): solicitud recibida, solicitud aceptada y hito de
 * racha compartida. Idempotentes por el índice único (usuario, origen, tipo, ocurrencia) y con el
 * par de cuentas normalizado, así que reenviar una solicitud no duplica el aviso. Nunca lanzan.
 * Sin scheduler: se crean al ocurrir el hecho.
 */
class FriendNotices
{
    public const SOURCE = 'friendship';
    public const REQUEST = 'friend_request';
    public const ACCEPTED = 'friend_accepted';
    public const STREAK = 'shared_streak';
    public const TYPES = [self::REQUEST, self::ACCEPTED, self::STREAK];

    /** Origen sintético por par (la columna `source_id` es uuid y `friendships.id` es numérico). */
    public static function sourceId(int $a, int $b): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'miso:friendship:'.min($a, $b).':'.max($a, $b))->toString();
    }

    public function requested(Friendship $friendship): void
    {
        $this->notify($friendship->addressee_id, $friendship->requester_id, self::REQUEST, 'req',
            fn (string $name) => ["{$name} quiere ser tu amiga o amigo", 'Revisa la solicitud en Amigos.']);
    }

    /** Avisa a quien envió la solicitud que $accepter la aceptó. */
    public function accepted(Friendship $friendship, int $accepterId): void
    {
        $this->notify($friendship->otherId($accepterId), $accepterId, self::ACCEPTED, 'acc',
            fn (string $name) => ["{$name} aceptó tu solicitud", 'Ya son amigos en MiSo.']);
    }

    /** Avisa a ambos un hito de racha compartida (una sola vez por par y hito). */
    public function sharedStreak(User $me, int $friendId, int $milestone): void
    {
        foreach ([[$me->id, $friendId], [$friendId, $me->id]] as [$recipient, $other]) {
            $this->notify($recipient, $other, self::STREAK, 'streak', fn (string $name) => [
                "{$milestone} días juntos con {$name}", 'Su racha compartida sigue. Cada día suma.',
            ], ':'.$milestone);
        }
    }

    /** @param callable(string): array{0: string, 1: string} $text */
    private function notify(int $recipientId, int $otherId, string $type, string $prefix, callable $text, string $suffix = ''): void
    {
        try {
            if (Friendship::query()->between($recipientId, $otherId)->where('status', Friendship::BLOCKED)->exists()) {
                return;
            }
            if (! (new NotificationPreferences)->get($recipientId)['friend_notices']) {
                return;
            }
            $other = User::find($otherId);
            if (! $other) {
                return;
            }
            [$title, $body] = $text($other->full_name ?: $other->name);
            $low = min($recipientId, $otherId);
            $high = max($recipientId, $otherId);

            UserNotification::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $recipientId, 'source_type' => self::SOURCE, 'source_id' => self::sourceId($recipientId, $otherId),
                'type' => $type, 'occurrence_key' => "{$prefix}:{$low}:{$high}{$suffix}",
            ], ['title' => mb_strimwidth($title, 0, 120, '…'), 'body' => $body, 'scheduled_for' => now('UTC')]);
        } catch (Throwable $exception) {
            Log::warning('social.friend_notice_failed', ['type' => $type, 'error' => $exception->getMessage()]);
        }
    }
}
