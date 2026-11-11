<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameSpecies;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Álbumes de la colección por tipo (CHG-078). Se calculan en memoria sobre el catálogo activo
 * (`game_species.types`) y lo descubierto por el usuario. Una especie con dos tipos cuenta en
 * ambos. Completar un álbum paga una ball extra una sola vez por tipo (`game_album_awards`);
 * si el catálogo crece, el álbum vuelve a mostrar pendientes y la recompensa no se repite.
 */
class Albums
{
    public const LABELS = [
        'acero' => 'Acero', 'agua' => 'Agua', 'bicho' => 'Bicho', 'dragon' => 'Dragón', 'electrico' => 'Eléctrico',
        'fantasma' => 'Fantasma', 'fuego' => 'Fuego', 'hada' => 'Hada', 'hielo' => 'Hielo', 'lucha' => 'Lucha',
        'normal' => 'Normal', 'planta' => 'Planta', 'psiquico' => 'Psíquico', 'roca' => 'Roca', 'siniestro' => 'Siniestro',
        'tierra' => 'Tierra', 'veneno' => 'Veneno', 'volador' => 'Volador',
    ];

    public function __construct(private readonly RewardGranter $granter) {}

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst($type);
    }

    /**
     * Un álbum por tipo con al menos una especie activa, en el orden de `game.type_palette`.
     *
     * @return array<int, array{type: string, label: string, found: int, total: int, complete: bool}>
     */
    public function overview(int $userId): array
    {
        $found = array_flip($this->discoveredIds($userId));
        $totals = [];
        $hits = [];
        foreach ($this->catalog() as $id => $types) {
            foreach ($types as $type) {
                $totals[$type] = ($totals[$type] ?? 0) + 1;
                $hits[$type] = ($hits[$type] ?? 0) + (isset($found[$id]) ? 1 : 0);
            }
        }

        $order = array_flip((array) config('game.type_palette'));
        uksort($totals, fn (string $a, string $b) => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);

        $albums = [];
        foreach ($totals as $type => $total) {
            $albums[] = ['type' => $type, 'label' => self::label($type), 'found' => $hits[$type], 'total' => $total, 'complete' => $hits[$type] >= $total];
        }

        return $albums;
    }

    /** Tras un descubrimiento: paga los álbumes de los tipos de esa especie que queden completos. */
    public function awardIfComplete(int $userId, int $speciesId): void
    {
        $types = (array) (GameSpecies::query()->whereKey($speciesId)->value('types') ?? []);
        if (is_string($types)) {
            $types = (array) json_decode($types, true);
        }
        if ($types === []) {
            return;
        }

        foreach ($this->overview($userId) as $album) {
            if (! $album['complete'] || ! in_array($album['type'], $types, true)) {
                continue;
            }
            try {
                DB::table('game_album_awards')->insert([
                    'user_id' => $userId, 'album_key' => $album['type'], 'species_total' => $album['total'],
                    'awarded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (QueryException) {
                continue; // Ya se entregó antes (único por usuario y álbum).
            }
            $this->granter->grantBonusBall($userId, 'album:'.$album['type'], 'Álbum '.$album['label'].' completo', [
                'icon' => 'bi-collection-fill',
                'title' => '¡Álbum '.$album['label'].' completo!',
                'detail' => 'Ganaste una ball.',
                'album' => $album['type'],
            ]);
        }
    }

    /** @return array<int, int> */
    private function discoveredIds(int $userId): array
    {
        return GameDiscovery::withoutGlobalScopes()->where('user_id', $userId)->pluck('species_id')
            ->merge(GameCompanion::withoutGlobalScopes()->where('user_id', $userId)->pluck('species_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** @return array<int, array<int, string>> species_id => tipos, del catálogo activo */
    private function catalog(): array
    {
        return GameSpecies::query()->where('active', true)->whereNotNull('types')->get(['id', 'types'])
            ->mapWithKeys(fn (GameSpecies $species) => [(int) $species->id => array_values(array_unique(array_filter((array) $species->types, 'is_string')))])
            ->all();
    }
}
