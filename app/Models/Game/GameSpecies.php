<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

/** Catálogo global de especies. Se carga con `game:import-species`; el repo no trae datos de terceros. */
class GameSpecies extends Model
{
    protected $table = 'game_species';

    protected $fillable = [
        'number', 'name', 'line_key', 'stage', 'rarity', 'image_key', 'active',
        'description', 'types', 'stats', 'evolves_from_number', 'evolve_level',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'stage' => 'integer',
            'active' => 'boolean',
            'types' => 'array',
            'stats' => 'array',
            'evolves_from_number' => 'integer',
            'evolve_level' => 'integer',
        ];
    }

    /** Nivel que el ejemplar necesita para llegar a esta especie. */
    public function requiredLevel(): int
    {
        return $this->evolve_level ?: (int) config("game.stage_evolve_levels.{$this->stage}", 0);
    }
}
