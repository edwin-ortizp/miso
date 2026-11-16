<?php

namespace App\Services\Game;

use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Aviso persistente de ball en la campanita (CHG-117). Una fila por ball, idempotente por el
 * índice único (usuario, origen, tipo, ocurrencia). Solo vive en la bandeja: no hay push,
 * Android ni Telegram porque `push_game_ball` no existe en las preferencias.
 */
class BallNotice
{
    public const TYPE = 'game_ball';

    public const SOURCE = 'game';

    /** Origen sintético determinista: la columna `source_id` no admite nulos. */
    public static function sourceId(): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'miso:game-ball')->toString();
    }

    /** Nunca lanza: el aviso no debe impedir el premio. */
    public function granted(GameEncounter $encounter, GameEvent $event): void
    {
        try {
            $effect = $event->effect ?? [];
            $special = (bool) ($effect['special'] ?? false);
            $label = (string) ($effect['title'] ?? $event->source_label ?? '');
            $detail = $special ? preg_replace('/^Meta cumplida: /u', '', $label) : $label;

            UserNotification::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $encounter->user_id, 'source_type' => self::SOURCE, 'source_id' => self::sourceId(),
                'type' => self::TYPE, 'occurrence_key' => 'ball:'.$encounter->id,
            ], [
                'title' => $special ? '¡Meta cumplida!' : '¡Ganaste una ball!',
                'body' => mb_strimwidth((string) $detail, 0, 120, '…'),
                'scheduled_for' => now('UTC'),
            ]);
        } catch (Throwable $exception) {
            Log::warning('game.ball_notice_failed', ['encounter' => $encounter->id, 'error' => $exception->getMessage()]);
        }
    }

    /** Al abrir la ball el aviso deja de estar pendiente. */
    public function opened(int $userId, int $encounterId): void
    {
        UserNotification::withoutGlobalScopes()->where('user_id', $userId)
            ->where('source_type', self::SOURCE)->where('type', self::TYPE)
            ->where('occurrence_key', 'ball:'.$encounterId)->whereNull('read_at')
            ->update(['read_at' => now('UTC')]);
    }
}
