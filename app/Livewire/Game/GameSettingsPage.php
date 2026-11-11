<?php

namespace App\Livewire\Game;

use App\Services\Game\GameSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Todos los ajustes del juego en un solo lugar. Desactivar no borra balls, ejemplares ni experiencia. */
#[Layout('layouts.app')]
#[Title('Ajustes de la colección')]
class GameSettingsPage extends Component
{
    public bool $enabled = true;

    public bool $tasks = true;

    public bool $habits = true;

    public bool $focus = true;

    public bool $goals = true;

    public bool $health = true;

    /** Constancia (CHG-042): fuente => activa. @var array<string, bool> */
    public array $consistency = [];

    public int $dailyLimit = 5;

    public bool $directorEnabled = true;

    public bool $seasonsEnabled = true;

    public bool $celebrations = true;

    public bool $sounds = true;

    /** Racha (CHG-076). */
    public bool $streakVisible = true;

    public int $streakThreshold = 3;

    /** Reto del día (CHG-077). */
    public bool $dailyChallenge = true;

    /** @var array<int, string> */
    public array $celebrationSet = [];

    public string $message = '';

    public function mount(GameSettings $settings): void
    {
        $current = $settings->for((int) auth()->id());
        $this->enabled = $current['enabled'];
        $this->tasks = (bool) $current['sources']['tasks'];
        $this->habits = (bool) $current['sources']['habits'];
        $this->focus = (bool) $current['sources']['focus'];
        $this->goals = (bool) $current['sources']['goals'];
        $this->health = (bool) $current['sources']['health'];
        foreach (array_keys(config('game.consistency_sources')) as $source) {
            $this->consistency[$source] = (bool) ($current['sources'][$source] ?? true);
        }
        $this->dailyLimit = $current['daily_limit'];
        $this->directorEnabled = $current['director_enabled'];
        $this->seasonsEnabled = $current['seasons_enabled'];
        $this->celebrations = $current['celebrations'];
        $this->sounds = $current['sounds'];
        $this->streakVisible = $current['streak_visible'];
        $this->streakThreshold = $current['streak_threshold'];
        $this->dailyChallenge = $current['daily_challenge'];
        $this->celebrationSet = $current['celebration_set'];
    }

    public function save(GameSettings $settings): void
    {
        [$min, $max] = config('game.limits.daily_limit');
        [$streakMin, $streakMax] = config('game.limits.streak_threshold');
        $this->validate([
            'dailyLimit' => ['required', 'integer', "between:{$min},{$max}"],
            'streakThreshold' => ['required', 'integer', "between:{$streakMin},{$streakMax}"],
        ], [], ['streakThreshold' => 'acciones para cumplir el día']);

        $settings->save((int) auth()->id(), [
            'enabled' => $this->enabled,
            'sources' => ['tasks' => $this->tasks, 'habits' => $this->habits, 'focus' => $this->focus, 'goals' => $this->goals, 'health' => $this->health]
                + array_map('boolval', array_intersect_key($this->consistency, config('game.consistency_sources'))),
            'daily_limit' => $this->dailyLimit,
            'director_enabled' => $this->directorEnabled,
            'seasons_enabled' => $this->seasonsEnabled,
            'celebrations' => $this->celebrations,
            'sounds' => $this->sounds,
            'streak_visible' => $this->streakVisible,
            'streak_threshold' => $this->streakThreshold,
            'daily_challenge' => $this->dailyChallenge,
            'celebration_set' => array_values(array_intersect(array_keys(config('game.celebration_catalog')), $this->celebrationSet)),
        ]);

        $this->dispatch('game-settings-saved');
        $this->message = 'Ajustes guardados.';
    }

    public function render()
    {
        return view('livewire.game.game-settings');
    }
}
