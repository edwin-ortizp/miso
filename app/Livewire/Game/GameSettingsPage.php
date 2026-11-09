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

    public int $dailyLimit = 5;

    public bool $directorEnabled = true;

    public bool $seasonsEnabled = true;

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
        $this->dailyLimit = $current['daily_limit'];
        $this->directorEnabled = $current['director_enabled'];
        $this->seasonsEnabled = $current['seasons_enabled'];
    }

    public function save(GameSettings $settings): void
    {
        [$min, $max] = config('game.limits.daily_limit');
        $this->validate(['dailyLimit' => ['required', 'integer', "between:{$min},{$max}"]]);

        $settings->save((int) auth()->id(), [
            'enabled' => $this->enabled,
            'sources' => ['tasks' => $this->tasks, 'habits' => $this->habits, 'focus' => $this->focus, 'goals' => $this->goals, 'health' => $this->health],
            'daily_limit' => $this->dailyLimit,
            'director_enabled' => $this->directorEnabled,
            'seasons_enabled' => $this->seasonsEnabled,
        ]);

        $this->message = 'Ajustes guardados.';
    }

    public function render()
    {
        return view('livewire.game.game-settings');
    }
}
