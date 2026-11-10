<?php

namespace App\Livewire\Game;

use App\Models\Game\GameCompanion;
use App\Models\Game\GameEncounter;
use App\Models\Game\GameSeason;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameWeek;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Services\Game\GameRules;
use App\Services\Game\SeasonManager;
use App\Services\Game\SpeciesImageProvider;
use App\Services\Game\WeekPlanner;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/** Temporada activa, historial y revertir. Los interruptores viven en Ajustes. */
#[Layout('layouts.app')]
#[Title('Temporada · Colección')]
class GameSeasonView extends Component
{
    public bool $confirmRevert = false;

    public ?int $revertId = null;

    public string $message = '';

    public function askRevert(int $seasonId): void
    {
        $this->revertId = $seasonId;
        $this->confirmRevert = true;
    }

    public function revert(SeasonManager $seasons): void
    {
        try {
            $seasons->revert((int) auth()->id(), (int) $this->revertId);
            $this->message = 'Temporada revertida. No perdiste nada de lo que ganaste.';
        } catch (Throwable) {
            $this->message = 'No pudimos revertir esa temporada.';
        }
        $this->confirmRevert = false;
        $this->revertId = null;
    }

    public function render(GameRules $rules, SpeciesImageProvider $images)
    {
        $userId = (int) auth()->id();
        $active = $rules->activeSeason($userId, now());
        $scheduled = GameSeason::query()->where('status', GameSeason::ACTIVE)->where('starts_at', '>', now())->orderBy('starts_at')->first();
        $categories = TaskCategory::query()->pluck('name', 'key')->put('habits', config('game.habits_ability.label'));

        // Read existing weekly state: viewing a season never creates or replaces missions.
        $week = GameWeek::query()->where('user_id', $userId)
            ->whereDate('week_start', WeekPlanner::weekStart(now())->toDateString())->first();
        $missions = $week?->missions()->get() ?? collect();
        $members = $week?->members()->with('companion.species')->get() ?? collect();
        $owned = $members->pluck('companion')->filter(fn ($c) => $c?->species);
        if ($owned->isEmpty()) {
            $owned = GameCompanion::query()->where('user_id', $userId)
                ->with('species')->orderBy('id')->limit(3)->get()->filter(fn ($c) => $c->species);
        }
        $companions = $owned->take(3)->map(fn ($c) => [
            'name' => $c->species->name, 'image' => $images->url($c->species),
        ]);
        $stages = collect();
        if ($active) {
            $count = max(1, (int) ceil($active->starts_at->diffInDays($active->ends_at) / 7));
            for ($i = 0; $i < $count; $i++) {
                $anchor = WeekPlanner::weekStart($active->starts_at);
                $start = $i === 0 ? $active->starts_at->copy() : $anchor->addWeeks($i);
                $end = $anchor->addWeeks($i + 1)->min($active->ends_at);
                $stages->push(['number' => $i + 1, 'start' => $start, 'current' => now()->gte($start) && now()->lt($end), 'past' => now()->gte($end)]);
            }
        }

        return view('livewire.game.game-season', [
            'missions' => $missions,
            'companions' => $companions,
            'stages' => $stages,
            'daysLeft' => $active ? max(0, (int) ceil(now()->diffInDays($active->ends_at, false))) : 0,
            'bonuses' => collect($active?->config['team_bonus'] ?? [])->map(fn ($m, $k) => [
                'label' => $categories[$k] ?? $k, 'percent' => (int) round(($m - 1) * 100),
                'href' => $k === 'habits' ? route('habits') : route('tasks.list', ['category' => [$k]]),
            ]),
            'pendingBalls' => GameEncounter::query()->where('user_id', $userId)->whereNull('opened_at')->count(),
            'doneTasks' => DB::table('game_mission_contributions')
                ->whereIn('game_mission_id', $missions->pluck('id'))->where('contribution_key', 'like', 'task:%')
                ->get(['game_mission_id', 'contribution_key'])->groupBy('game_mission_id')
                ->map(fn ($rows) => $rows->map(fn ($r) => substr($r->contribution_key, 5))->all()),
            'existingTasks' => Task::query()->where('user_id', $userId)
                ->whereIn('id', $missions->where('type', 'specific_tasks')->flatMap(fn ($m) => collect($m->params['tasks'] ?? [])->pluck('id')))
                ->pluck('completed', 'id'),
            'active' => $active,
            'activeRules' => $active ? $this->describe($active, $categories) : [],
            'scheduled' => $scheduled,
            'history' => GameSeason::query()->latest('id')->limit(20)->get(),
        ]);
    }

    /** Reglas de la temporada en lenguaje simple. @return array<int, string> */
    private function describe(GameSeason $season, $categories): array
    {
        $config = $season->config ?? [];
        $lines = [];
        if ($config['mission_focus'] ?? []) {
            $lines[] = 'Misiones: impulsa '.collect($config['mission_focus'])->map(fn ($k) => $categories[$k] ?? $k)->join(', ', ' y ');
        }
        if ($config['team_bonus'] ?? []) {
            $lines[] = 'Bonos: '.collect($config['team_bonus'])->map(fn ($m, $k) => ($categories[$k] ?? $k).' ×'.str_replace('.', ',', (string) $m))->join(' · ');
        }
        if ($config['featured_line'] ?? null) {
            $name = GameSpecies::query()->where('line_key', $config['featured_line'])->orderBy('stage')->value('name');
            $lines[] = 'Destacada: línea '.($name ?? $config['featured_line']);
        }
        if ($config['rarity_weights'] ?? null) {
            $lines[] = 'Rarezas: '.collect($config['rarity_weights'])->map(fn ($w, $r) => "{$r} {$w}")->join(' · ');
        }

        return $lines;
    }
}
