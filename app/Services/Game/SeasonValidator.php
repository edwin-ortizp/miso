<?php

namespace App\Services\Game;

use App\Models\Game\GameSpecies;
use App\Models\TaskCategory;

/**
 * Valida de forma estricta una propuesta de temporada del director. Nada se
 * recorta en silencio: cualquier valor fuera de rango, clave desconocida o
 * referencia inexistente rechaza la propuesta con un motivo legible.
 */
class SeasonValidator
{
    private const KEYS = ['name', 'narrative', 'notes', 'weeks', 'missions', 'team_bonus', 'rarity_weights', 'featured_line'];
    private const MISSION_KEYS = ['types', 'target', 'focus'];

    /**
     * @param  array<string, mixed>  $proposal
     * @return array{errors: array<int, string>, config: array<string, mixed>}
     */
    public function validate(array $proposal, int $userId): array
    {
        $rules = config('game.season');
        $errors = [];
        $config = [];

        foreach (array_diff(array_keys($proposal), self::KEYS) as $unknown) {
            $errors[] = "Campo no permitido: {$unknown}.";
        }

        $name = $proposal['name'] ?? null;
        if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 60) {
            $errors[] = 'El nombre es obligatorio y tiene máximo 60 caracteres.';
        }
        $narrative = $proposal['narrative'] ?? null;
        if (! is_string($narrative) || trim($narrative) === '' || mb_strlen($narrative) > 400) {
            $errors[] = 'La narrativa es obligatoria y tiene máximo 400 caracteres.';
        }
        if (isset($proposal['notes']) && (! is_string($proposal['notes']) || mb_strlen($proposal['notes']) > 1000)) {
            $errors[] = 'Las notas tienen máximo 1000 caracteres.';
        }

        [$minWeeks, $maxWeeks] = $rules['weeks'];
        $weeks = $proposal['weeks'] ?? $rules['default_weeks'];
        if (! is_int($weeks) || $weeks < $minWeeks || $weeks > $maxWeeks) {
            $errors[] = "La duración debe ser de {$minWeeks} a {$maxWeeks} semanas.";
        }
        $config['weeks'] = is_int($weeks) ? $weeks : $rules['default_weeks'];

        $categories = TaskCategory::withoutGlobalScopes()->where('user_id', $userId)->pluck('key')->map(fn ($k) => (string) $k)->all();
        $scopes = [...$categories, ...array_keys(config('game.scopes'))];

        $missions = $proposal['missions'] ?? [];
        if (! is_array($missions)) {
            $errors[] = 'Misiones debe ser un objeto.';
            $missions = [];
        }
        foreach (array_diff(array_keys($missions), self::MISSION_KEYS) as $unknown) {
            $errors[] = "Campo de misiones no permitido: {$unknown}.";
        }
        if (isset($missions['types'])) {
            $allowed = ['category_tasks', 'habit_groups', 'variety'];
            if (! is_array($missions['types']) || $missions['types'] === [] || array_diff($missions['types'], $allowed) !== []) {
                $errors[] = 'Tipos de misión permitidos: '.implode(', ', $allowed).'.';
            } else {
                $config['mission_types'] = array_values(array_unique($missions['types']));
            }
        }
        if (isset($missions['target'])) {
            [$minTarget, $maxTarget] = $rules['mission_target'];
            $target = $missions['target'];
            if (! is_array($target) || count($target) !== 2 || ! is_int($target[0] ?? null) || ! is_int($target[1] ?? null)
                || $target[0] < $minTarget || $target[1] > $maxTarget || $target[0] > $target[1]) {
                $errors[] = "Las metas de misión deben estar entre {$minTarget} y {$maxTarget}.";
            } else {
                $config['mission_target'] = [$target[0], $target[1]];
            }
        }
        if (isset($missions['focus'])) {
            $focus = $missions['focus'];
            if (! is_array($focus) || count($focus) > $rules['max_focus'] || array_diff($focus, $categories) !== []) {
                $errors[] = "El enfoque admite hasta {$rules['max_focus']} categorías existentes.";
            } else {
                $config['mission_focus'] = array_values(array_unique($focus));
            }
        }

        if (isset($proposal['team_bonus'])) {
            $bonus = $proposal['team_bonus'];
            [$minBonus, $maxBonus] = $rules['team_bonus'];
            if (! is_array($bonus) || count($bonus) > $rules['max_bonus_scopes']) {
                $errors[] = "Los bonos admiten hasta {$rules['max_bonus_scopes']} ámbitos.";
            } else {
                foreach ($bonus as $scope => $multiplier) {
                    if (! in_array((string) $scope, $scopes, true)) {
                        $errors[] = "El ámbito «{$scope}» no existe.";
                    } elseif (! is_numeric($multiplier) || $multiplier < $minBonus || $multiplier > $maxBonus) {
                        $errors[] = "El bono de «{$scope}» debe estar entre ×{$minBonus} y ×{$maxBonus}.";
                    } else {
                        $config['team_bonus'][(string) $scope] = round((float) $multiplier, 2);
                    }
                }
            }
        }

        if (isset($proposal['rarity_weights'])) {
            $weights = $proposal['rarity_weights'];
            $rarities = config('game.rarities');
            if (! is_array($weights) || array_diff(array_keys($weights), $rarities) !== []) {
                $errors[] = 'Rarezas permitidas: '.implode(', ', $rarities).'.';
            } else {
                $clean = [];
                foreach ($weights as $rarity => $weight) {
                    if (! is_int($weight) || $weight < 0 || $weight > $rules['rarity_max']) {
                        $errors[] = "El peso de {$rarity} debe ser un entero de 0 a {$rules['rarity_max']}.";
                    }
                    $clean[$rarity] = (int) $weight;
                }
                if (($clean['legendary'] ?? 0) > $rules['legendary_max']) {
                    $errors[] = "El peso de legendary tiene máximo {$rules['legendary_max']}.";
                }
                if (array_sum($clean) <= 0) {
                    $errors[] = 'La suma de pesos debe ser mayor que cero.';
                }
                $config['rarity_weights'] = $clean;
            }
        }

        if (isset($proposal['featured_line'])) {
            $line = $proposal['featured_line'];
            if (! is_string($line) || ! GameSpecies::query()->where('line_key', $line)->where('active', true)->exists()) {
                $errors[] = 'La línea destacada no existe en el catálogo.';
            } else {
                $config['featured_line'] = $line;
                $config['featured_weight'] = (int) $rules['featured_weight_max'];
            }
        }

        return ['errors' => $errors, 'config' => $errors === [] ? $config : []];
    }
}
