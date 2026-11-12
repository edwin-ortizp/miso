<?php

namespace App\Services\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameTeamMember;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Guardián de módulo (CHG-055): el ejemplar del usuario con mejor afinidad de
 * tipo para un módulo y una frase según el avance del día en ese módulo.
 * CHG-066: se elige primero entre los integrantes del equipo de la semana.
 */
class ModuleGuardian
{
    public function __construct(
        private readonly GameSettings $settings,
        private readonly SpeciesImageProvider $images,
        private readonly WeekPlanner $planner,
    ) {}

    /** @var array<int, \Illuminate\Support\Collection> Ejemplares por usuario, dentro de la petición (CHG-095). */
    private array $rosters = [];

    /** @var array<int, \Illuminate\Support\Collection> Equipo de la semana por usuario, dentro de la petición. */
    private array $teams = [];

    /** Olvida lo leído; lo llaman los eventos de ejemplares, especies, semana y equipo. */
    public function flush(): void
    {
        $this->rosters = [];
        $this->teams = [];
    }

    /** @return array<string, mixed>|null null = juego apagado o sin ejemplares */
    public function pick(int $userId, string $module): ?array
    {
        if (! $this->settings->for($userId)['enabled']) {
            return null;
        }

        // Una página pide el guardián varias veces (franja, panel, visibilidad): se lee una sola.
        $companions = $this->rosters[$userId] ??= $this->companions($userId);
        if ($companions->isEmpty()) {
            return null;
        }

        $affinity = config("game.module_guardians.{$module}", []);
        $matches = fn ($c) => collect($c->species->types ?? [])
            ->contains(fn ($type) => in_array(Str::slug((string) $type), $affinity, true));

        // CHG-066: el equipo de la semana tiene prioridad; sin equipo, toda la colección.
        // Se calcula antes de guardarlo: armar la semana por primera vez dispara `flush()`.
        $team = $this->teams[$userId] ?? $this->teamMembers($userId);
        $this->teams[$userId] = $team;
        $pool = $team->isNotEmpty()
            ? $companions->filter(fn ($c) => $team->has($c->id))->values()
            : $companions;
        if ($pool->isEmpty()) {
            $pool = $companions;
        }
        $companion = $pool->first($matches) ?? $pool->first();
        $member = $team->get($companion->id);

        $species = $companion->species;
        $types = $species->types ?? [];

        return [
            'species_id' => $species->id,
            'name' => $species->name,
            'level' => (int) $companion->level,
            'image' => $this->images->url($species),
            'type_class' => CompanionProgress::typeClass($types[0] ?? null),
            'stage' => DailyBuddy::stageFamily($types[0] ?? null),
            'team_ability' => $member?->ability_label,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, GameCompanion> */
    private function companions(int $userId): \Illuminate\Support\Collection
    {
        return GameCompanion::withoutGlobalScopes()->where('user_id', $userId)
            ->whereHas('species')->with('species')
            ->orderByDesc('level')->orderBy('created_at')->orderBy('id')->get();
    }

    /** @return \Illuminate\Support\Collection<int, GameTeamMember> integrantes por companion_id */
    private function teamMembers(int $userId): \Illuminate\Support\Collection
    {
        try {
            $week = $this->planner->forWeek($userId, now());
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }

        // `forWeek` ya deja los integrantes cargados cuando no tuvo que crear nada.
        $members = $week->relationLoaded('members')
            ? $week->members
            : GameTeamMember::withoutGlobalScopes()->where('game_week_id', $week->id)->get();

        return $members->keyBy('companion_id');
    }

    /** Frase según el avance; `$care` (evento de salud en seguimiento) tiene prioridad. */
    public static function line(string $module, int $done, int $total, bool $care, CarbonInterface $now): string
    {
        $lines = config("game.guardian_lines.{$module}", []);
        if ($lines === [] && ! in_array($module, ['tasks', 'health', 'habits', 'water', 'exercise', 'pomodoro'], true)) {
            return (string) config('game.guardian_default_line');
        }
        $key = $care && isset($lines['care']) ? 'care' : DailyBuddy::mood($done, $total, $now)['key'];

        return (string) ($lines[$key] ?? config("game.buddy_moods.{$key}.text"));
    }
}
