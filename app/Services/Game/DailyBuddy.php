<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameQuote;
use App\Support\CompanionVoice;

/**
 * Integrante del día (CHG-043): un ejemplar al azar del usuario y una frase
 * vigente. Se calcula al cargar la página; el resultado es un arreglo simple
 * para guardarlo en una propiedad de Livewire y no cambiar con los refrescos.
 */
class DailyBuddy
{
    public function __construct(
        private readonly GameSettings $settings,
        private readonly SpeciesImageProvider $images,
    ) {}

    /** @return array<string, mixed>|null null = juego apagado; ['empty' => true] = sin ejemplares */
    public function pick(int $userId): ?array
    {
        if (! $this->settings->for($userId)['enabled']) {
            return null;
        }

        $companion = GameCompanion::withoutGlobalScopes()->where('user_id', $userId)
            ->with('species')->inRandomOrder()->first();
        if (! $companion || ! $companion->species) {
            return ['empty' => true];
        }

        $species = $companion->species;
        $types = $species->types ?? [];

        return [
            'empty' => false,
            'species_id' => $species->id,
            'name' => $species->name,
            'level' => (int) $companion->level,
            'image' => $this->images->url($species),
            'type_class' => CompanionProgress::typeClass($types[0] ?? null),
            'stage' => self::stageFamily($types[0] ?? null),
            'quote' => $this->quote($userId),
        ];
    }

    /** Frase vigente al azar; `$except` evita repetir la actual si hay otra disponible (CHG-049). */
    public function quote(int $userId, ?string $except = null): string
    {
        $since = now()->subDays((int) config('game.quotes.max_age_days'));

        $pool = config('companion_voice.A', []);
        $director = GameQuote::query()->where('user_id', $userId)
            ->where('source', GameQuote::SOURCE_DIRECTOR)->where('created_at', '>=', $since)->pluck('text');
        foreach ($director as $text) {
            if (! in_array($text, $pool, true)) {
                $pool['director_'.hash('sha256', $text)] = $text;
            }
        }
        $exceptId = $except === null ? false : array_search($except, $pool, true);

        return CompanionVoice::fromPool($userId, 'A', $pool, [], $exceptId === false ? null : $exceptId)['text'];
    }

    /** Familia de escenario del panel según el tipo principal (CHG-049); sin familia: cielo. */
    public static function stageFamily(?string $type): string
    {
        $slug = $type ? \Illuminate\Support\Str::slug($type) : '';

        return (string) (config("game.stage_families.{$slug}") ?? 'cielo');
    }

    /**
     * Reacción al avance del día (CHG-049): hábitos y tareas hechos sobre el total de hoy.
     *
     * @return array{key: string, text: string}
     */
    public static function mood(int $done, int $total, \Carbon\CarbonInterface $now): array
    {
        $moods = config('game.buddy_moods');
        $key = match (true) {
            $total <= 0 => 'free',
            $done * 100 >= $moods['celebrate']['min'] * $total => 'celebrate',
            $done * 100 >= $moods['happy']['min'] * $total => 'happy',
            (int) $now->format('G') < $moods['greet']['before_hour'] => 'greet',
            default => 'cheer',
        };

        return ['key' => $key, 'text' => $moods[$key]['text']];
    }
}
