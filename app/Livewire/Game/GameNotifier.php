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

    /** Sonidos activos (CHG-044); el navegador lo lee de `data-game-sound`. */
    public bool $sounds = true;

    public function mount(GameSettings $settings): void
    {
        $this->sounds = (bool) $settings->for((int) auth()->id())['sounds'];
        // No repite lo ocurrido antes de cargar la página.
        $this->lastSeenEventId = (int) GameEvent::query()->max('id');
    }

    #[On('game-check')]
    #[On('task-completed')]
    #[On('habit-feedback')]
    public function check(GameFeed $feed, GameSettings $settings): void
    {
        // CHG-079: paradas del camino alcanzadas fuera de la pestaña Temporada (máx. 1 vez por minuto).
        $userId = (int) auth()->id();
        if (\Illuminate\Support\Facades\Cache::add("season-path:{$userId}", true, 60)) {
            app(\App\Services\Game\SeasonPath::class)->awardPending($userId);
        }

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

        if ($sound = $this->soundFor($result['toasts'])) {
            if ($settings->for((int) auth()->id())['sounds']) {
                $this->dispatch('game-sound', name: $sound);
            }
        }
    }

    /** Ajustes guardados en Colección → Ajustes: actualiza el ajuste de sonido sin recargar. */
    #[On('game-settings-saved')]
    public function refreshSettings(GameSettings $settings): void
    {
        $this->sounds = (bool) $settings->for((int) auth()->id())['sounds'];
    }

    /**
     * Sonido del momento más importante de la tanda (CHG-044): misión o meta
     * cumplida > nivel o evolución > ball. La experiencia sola no suena.
     *
     * @param  array<int, array<string, mixed>>  $toasts
     */
    private function soundFor(array $toasts): ?string
    {
        $types = array_column($toasts, 'type');
        $missionDone = array_filter($toasts, fn ($toast) => ($toast['type'] === 'mission' && str_starts_with((string) $toast['title'], '¡')) || $toast['type'] === 'goal');

        return match (true) {
            $missionDone !== [] => 'mission',
            in_array('level', $types, true) || in_array('evolution', $types, true) => 'level',
            in_array('ball', $types, true) => 'ball',
            default => null,
        };
    }

    public function dismiss(int $id): void
    {
        $this->toasts = array_values(array_filter($this->toasts, fn ($toast) => $toast['id'] !== $id));
    }

    private function celebrate(GameSettings $settings): void
    {
        $current = $settings->for((int) auth()->id());
        if (! $current['celebrations']) {
            return;
        }
        $options = $current['celebration_set'];
        // Temporada temática (CHG-045): su celebración se suma al sorteo mientras dure.
        $theme = app(\App\Services\Game\GameRules::class)->for((int) auth()->id(), now())['theme'];
        if ($themeCelebration = config("game.season_themes.{$theme}.celebration")) {
            $options = array_values(array_unique([...$options, $themeCelebration]));
        }
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
