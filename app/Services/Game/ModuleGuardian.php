<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Guardián de módulo (CHG-055): el ejemplar del usuario con mejor afinidad de
 * tipo para un módulo y una frase según el avance del día en ese módulo.
 */
class ModuleGuardian
{
    public function __construct(
        private readonly GameSettings $settings,
        private readonly SpeciesImageProvider $images,
    ) {}

    /** @return array<string, mixed>|null null = juego apagado o sin ejemplares */
    public function pick(int $userId, string $module): ?array
    {
        if (! $this->settings->for($userId)['enabled']) {
            return null;
        }

        $companions = GameCompanion::withoutGlobalScopes()->where('user_id', $userId)
            ->whereHas('species')->with('species')
            ->orderByDesc('level')->orderBy('created_at')->orderBy('id')->get();
        if ($companions->isEmpty()) {
            return null;
        }

        $affinity = config("game.module_guardians.{$module}", []);
        $companion = $companions->first(fn ($c) => collect($c->species->types ?? [])
            ->contains(fn ($type) => in_array(Str::slug((string) $type), $affinity, true)))
            ?? $companions->first();

        $species = $companion->species;
        $types = $species->types ?? [];

        return [
            'species_id' => $species->id,
            'name' => $species->name,
            'level' => (int) $companion->level,
            'image' => $this->images->url($species),
            'type_class' => CompanionProgress::typeClass($types[0] ?? null),
            'stage' => DailyBuddy::stageFamily($types[0] ?? null),
        ];
    }

    /** Frase según el avance; `$care` (evento de salud en seguimiento) tiene prioridad. */
    public static function line(string $module, int $done, int $total, bool $care, CarbonInterface $now): string
    {
        $lines = config("game.guardian_lines.{$module}", []);
        $key = $care && isset($lines['care']) ? 'care' : DailyBuddy::mood($done, $total, $now)['key'];

        return (string) ($lines[$key] ?? config("game.buddy_moods.{$key}.text"));
    }
}
