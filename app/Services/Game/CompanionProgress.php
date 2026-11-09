<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Experiencia, niveles y evolución de los ejemplares. La xp y el nivel solo
 * suben. Una línea sin ramas evoluciona sola al llegar al nivel; con ramas,
 * el usuario elige en la ficha (`evolveTo`).
 */
class CompanionProgress
{
    public const KIND_LEVEL_UP = 'level_up';
    public const KIND_EVOLVED = 'evolved';

    public function levelFor(int $xp): int
    {
        return min((int) config('game.max_level'), 1 + intdiv(max(0, $xp), max(1, (int) config('game.xp_per_level'))));
    }

    /** Poder: crece con el nivel y nunca baja (el nivel nunca baja). */
    public function power(GameCompanion $companion, ?GameSpecies $species = null): int
    {
        $species ??= $companion->species;
        $base = $species && is_array($species->stats) && $species->stats !== []
            ? array_sum(array_map('intval', $species->stats))
            : (int) config('game.power_default_base');

        return (int) round($base * (1 + (max(1, (int) $companion->level) - 1) * (float) config('game.power_level_factor')));
    }

    /** Clase CSS del tipo (`game-type--fuego`) o la neutra si el tipo no tiene color. */
    public static function typeClass(?string $type): string
    {
        $slug = $type ? \Illuminate\Support\Str::slug($type) : '';

        return in_array($slug, config('game.type_palette'), true) ? "game-type--{$slug}" : 'game-type--neutral';
    }

    /**
     * Suma xp dentro de la transacción del llamador. Devuelve qué cambió.
     *
     * @param  array{type: ?string, id: ?string, label: ?string}  $source
     * @return array{level_from: int, level_to: int, evolved_from: ?int, evolved_to: ?int, ready_to_evolve: bool}
     */
    public function addXp(GameCompanion $companion, int $xp, array $source, string $key): array
    {
        $from = (int) $companion->level;
        $companion->xp = (int) $companion->xp + max(0, $xp);
        $companion->level = max($from, $this->levelFor($companion->xp));
        $companion->save();

        if ($companion->level > $from) {
            $this->record($companion, self::KIND_LEVEL_UP, $source, "levelup:{$key}", ['from' => $from, 'to' => $companion->level]);
        }

        $originalSpecies = $companion->species_id;
        $evolvedTo = null;
        // Avanza etapas mientras haya un único destino y el nivel alcance.
        for ($guard = 0; $guard < 9; $guard++) {
            $options = $this->options($companion);
            if ($options->count() !== 1 || $companion->level < $options->first()->requiredLevel()) {
                break;
            }
            $this->evolve($companion, $options->first(), $source);
            $evolvedTo = $companion->species_id;
        }

        $options = $this->options($companion);

        return [
            'level_from' => $from,
            'level_to' => (int) $companion->level,
            'evolved_from' => $evolvedTo ? $originalSpecies : null,
            'evolved_to' => $evolvedTo,
            'ready_to_evolve' => $options->count() > 1 && $options->contains(fn (GameSpecies $s) => $companion->level >= $s->requiredLevel()),
        ];
    }

    /** Destinos posibles de la siguiente etapa. @return Collection<int, GameSpecies> */
    public function options(GameCompanion $companion): Collection
    {
        $current = $companion->species()->first();
        if (! $current) {
            return collect();
        }

        $next = GameSpecies::query()->where('active', true)
            ->where('line_key', $current->line_key)->where('stage', $current->stage + 1)
            ->orderBy('number')->get();
        $direct = $next->where('evolves_from_number', $current->number);

        return ($direct->isNotEmpty() ? $direct : $next->whereNull('evolves_from_number'))->values();
    }

    /** Elección de rama desde la ficha. Valida línea, etapa y nivel. */
    public function evolveTo(int $companionId, int $speciesId, int $userId): GameSpecies
    {
        return DB::transaction(function () use ($companionId, $speciesId, $userId): GameSpecies {
            $companion = GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->whereKey($companionId)->lockForUpdate()->firstOrFail();
            $target = $this->options($companion)->firstWhere('id', $speciesId);
            if (! $target || $companion->level < $target->requiredLevel()) {
                throw new InvalidArgumentException('Esa evolución no está disponible.');
            }

            $this->evolve($companion, $target, ['type' => null, 'id' => null, 'label' => 'Elegiste su evolución']);

            return $target;
        });
    }

    public function discover(int $userId, int $speciesId): void
    {
        GameDiscovery::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $userId, 'species_id' => $speciesId],
            ['discovered_at' => now()],
        );
    }

    /** @param array{type: ?string, id: ?string, label: ?string} $source */
    private function evolve(GameCompanion $companion, GameSpecies $target, array $source): void
    {
        $from = $companion->species_id;
        $companion->species_id = $target->id;
        $companion->save();
        $this->discover((int) $companion->user_id, $target->id);
        $this->record($companion, self::KIND_EVOLVED, $source, "evolve:{$companion->id}:{$target->id}", ['from_species_id' => $from, 'to_species_id' => $target->id]);
    }

    /** @param array{type: ?string, id: ?string, label: ?string} $source */
    private function record(GameCompanion $companion, string $kind, array $source, string $key, array $effect): void
    {
        GameEvent::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $companion->user_id, 'idempotency_key' => $key],
            [
                'source_type' => $source['type'],
                'source_id' => $source['id'],
                'source_label' => $source['label'],
                'kind' => $kind,
                'effect' => ['companion_id' => $companion->id] + $effect,
                'occurred_at' => now(),
            ],
        );
    }
}
