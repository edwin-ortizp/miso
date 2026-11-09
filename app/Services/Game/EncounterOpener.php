<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use Illuminate\Support\Facades\DB;

/**
 * Abre una ball. Idempotente: si ya estaba abierta devuelve el mismo resultado.
 * Una línea nueva crea el ejemplar; una repetida (en cualquier etapa) suma experiencia.
 */
class EncounterOpener
{
    public function __construct(
        private readonly SpeciesImageProvider $images,
        private readonly CompanionProgress $progress,
    ) {}

    /** @return array<string, mixed> Resultado guardado en la ball; se repite tal cual si se abre de nuevo. */
    public function open(int $encounterId, int $userId): array
    {
        return DB::transaction(function () use ($encounterId, $userId): array {
            $encounter = GameEncounter::withoutGlobalScopes()
                ->where('user_id', $userId)->whereKey($encounterId)->lockForUpdate()->firstOrFail();

            if ($encounter->opened_at && $encounter->result) {
                return $encounter->result;
            }

            $species = $encounter->species()->firstOrFail();
            $companion = GameCompanion::withoutGlobalScopes()
                ->where('user_id', $userId)->where('line_key', $species->line_key)->lockForUpdate()->first();
            $isNew = $companion === null;
            $xp = (int) config($isNew ? 'game.welcome_xp' : 'game.duplicate_xp');

            if ($isNew) {
                $companion = GameCompanion::withoutGlobalScopes()->create([
                    'user_id' => $userId,
                    'line_key' => $species->line_key,
                    'species_id' => $species->id,
                    'xp' => 0,
                    'level' => 1,
                    'first_met_at' => now(),
                ]);
            }
            $this->progress->discover($userId, $species->id);

            $origin = $encounter->event()->withoutGlobalScopes()->first(['source_type', 'source_id', 'source_label']);
            $source = ['type' => $origin?->source_type, 'id' => $origin?->source_id, 'label' => $origin?->source_label];
            GameEvent::withoutGlobalScopes()->create([
                'user_id' => $userId,
                // El origen real es la actividad de la ball; el ejemplar va en el efecto.
                'source_type' => $source['type'],
                'source_id' => $source['id'],
                'source_label' => $source['label'],
                'kind' => $isNew ? GameEvent::KIND_DISCOVERED : GameEvent::KIND_XP_GAINED,
                'effect' => ['encounter_id' => $encounter->id, 'companion_id' => $companion->id, 'species_id' => $species->id, 'xp' => $xp],
                'idempotency_key' => 'open:'.$encounter->id,
                'occurred_at' => now(),
            ]);

            $currentName = $companion->species()->value('name');
            $change = $this->progress->addXp($companion, $xp, $source, 'open:'.$encounter->id);
            $evolvedFrom = $change['evolved_from'] ? GameSpecies::find($change['evolved_from']) : null;
            $evolvedTo = $change['evolved_to'] ? GameSpecies::find($change['evolved_to']) : null;

            $result = [
                'species_id' => $species->id,
                'number' => $species->number,
                'name' => $species->name,
                'image' => $this->images->url($species),
                'is_new' => $isNew,
                'xp_gained' => $xp,
                'companion_id' => $companion->id,
                'companion_name' => $currentName,
                'companion_xp' => (int) $companion->xp,
                'level_from' => $change['level_from'],
                'level_to' => $change['level_to'],
                'evolved' => $evolvedTo !== null,
                'evolved_from_name' => $evolvedFrom?->name,
                'evolved_from_image' => $evolvedFrom ? $this->images->url($evolvedFrom) : null,
                'evolved_to_name' => $evolvedTo?->name,
                'evolved_to_image' => $evolvedTo ? $this->images->url($evolvedTo) : null,
                'ready_to_evolve' => $change['ready_to_evolve'],
                'source_label' => $source['label'],
            ];
            $encounter->update(['opened_at' => now(), 'result' => $result]);

            return $result;
        });
    }
}
