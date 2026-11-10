<?php

namespace App\Livewire\Game;

use App\Models\Game\GameEvent;
use App\Services\Game\GameFeed;
use App\Services\Game\GameSettings;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Notificador global del juego (CHG-034; reemplaza al aviso de CHG-023).
 * Lee los eventos nuevos y muestra avisos apilados. En los momentos
 * importantes pide una celebración aleatoria del catálogo. Se entera:
 *  - de inmediato tras cualquier acción en la app (hook de Livewire → `game-check`);
 *  - con los eventos de tareas y hábitos que ya existen;
 *  - cada minuto, para lo que llega por MCP.
 */
class GameNotifier extends Component
{
    public const MAX_TOASTS = 3;

    public int $lastSeenEventId = 0;

    /** @var array<int, array<string, mixed>> */
    public array $toasts = [];

    public int $toastSequence = 0;

    public string $lastCelebration = '';

    public function mount(): void
    {
        // No repite lo ocurrido antes de cargar la página.
        $this->lastSeenEventId = (int) GameEvent::query()->max('id');
    }

    #[On('game-check')]
    #[On('task-completed')]
    #[On('habit-feedback')]
    public function check(GameFeed $feed, GameSettings $settings): void
    {
        $result = $feed->since((int) auth()->id(), $this->lastSeenEventId);
        $this->lastSeenEventId = $result['last_id'];
        if ($result['toasts'] === []) {
            return;
        }

        foreach ($result['toasts'] as $toast) {
            $this->toasts[] = $toast + ['id' => ++$this->toastSequence];
        }
        $this->toasts = array_slice($this->toasts, -self::MAX_TOASTS);

        if ($result['celebrate']) {
            $this->celebrate($settings);
        }
    }

    public function dismiss(int $id): void
    {
        $this->toasts = array_values(array_filter($this->toasts, fn ($toast) => $toast['id'] !== $id));
    }

    private function celebrate(GameSettings $settings): void
    {
        $current = $settings->for((int) auth()->id());
        $options = $current['celebrations'] ? $current['celebration_set'] : [];
        if ($options === []) {
            return;
        }

        // Al azar, sin repetir la anterior si hay más de una opción.
        $pool = count($options) > 1 ? array_values(array_diff($options, [$this->lastCelebration])) : $options;
        $this->lastCelebration = $pool[array_rand($pool)];
        $this->dispatch('game-celebrate', name: $this->lastCelebration);
    }

    public function render()
    {
        return view('livewire.game.game-notifier');
    }
}
