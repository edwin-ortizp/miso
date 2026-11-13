<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;

/**
 * Compañero que acompaña el chat de creación (CHG-100).
 * Favorito guardado → compañero del día (estable durante el día) → null.
 * null también cuando el juego está apagado o el usuario prefiere el botón tradicional.
 */
class FavoriteCompanion
{
    /** @var array<int, array<string, mixed>|null> */
    private array $resolved = [];

    public function __construct(
        private readonly GameSettings $settings,
        private readonly SpeciesImageProvider $images,
    ) {}

    /** Compañero para el chat o null si debe mostrarse el botón tradicional. */
    public function forChat(?int $userId): ?array
    {
        if (! $userId) {
            return null;
        }
        $settings = $this->settings->for($userId);
        if (! $settings['enabled'] || $settings['creation_style'] !== 'pokemon') {
            return null;
        }

        return $this->resolve($userId);
    }

    /** @return array<string, mixed>|null */
    public function resolve(int $userId): ?array
    {
        if (array_key_exists($userId, $this->resolved)) {
            return $this->resolved[$userId];
        }

        $favoriteId = $this->settings->for($userId)['favorite_companion_id'];
        $query = GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->with('species');
        $companion = $favoriteId ? (clone $query)->whereKey($favoriteId)->first() : null;
        $isFavorite = (bool) $companion;

        if (! $companion) {
            $ids = (clone $query)->orderBy('id')->pluck('id');
            if ($ids->isNotEmpty()) {
                $index = crc32($userId.'|'.now()->toDateString()) % $ids->count();
                $companion = (clone $query)->whereKey($ids[$index])->first();
            }
        }

        if (! $companion || ! $companion->species) {
            return $this->resolved[$userId] = null;
        }

        $species = $companion->species;
        $type = ($species->types ?? [])[0] ?? null;

        return $this->resolved[$userId] = [
            'companion_id' => $companion->id,
            'name' => $species->name,
            'level' => (int) $companion->level,
            'type' => $type,
            'type_class' => CompanionProgress::typeClass($type),
            'image' => $this->images->url($species),
            'favorite' => $isFavorite,
            'greeting' => self::greeting(now()),
            'salute' => self::greeting(now(), 'salute'),
        ];
    }

    public function flush(): void
    {
        $this->resolved = [];
    }

    /** Saludo breve según la hora del día. */
    public static function greeting(\Carbon\CarbonInterface $now, string $set = 'greeting'): string
    {
        $hour = (int) $now->format('G');

        $key = match (true) {
            $hour < 12 => 'morning',
            $hour < 19 => 'afternoon',
            default => 'night',
        };

        return (string) config("companion_chat.{$set}.{$key}");
    }
}
