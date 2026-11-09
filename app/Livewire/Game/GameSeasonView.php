<?php

namespace App\Livewire\Game;

use App\Models\Game\GameSeason;
use App\Models\Game\GameSpecies;
use App\Models\TaskCategory;
use App\Services\Game\GameRules;
use App\Services\Game\SeasonManager;
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

    public function render(GameRules $rules)
    {
        $userId = (int) auth()->id();
        $active = $rules->activeSeason($userId, now());
        $scheduled = GameSeason::query()->where('status', GameSeason::ACTIVE)->where('starts_at', '>', now())->orderBy('starts_at')->first();
        $categories = TaskCategory::query()->pluck('name', 'key')->put('habits', config('game.habits_ability.label'));

        return view('livewire.game.game-season', [
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
