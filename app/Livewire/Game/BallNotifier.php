<?php

namespace App\Livewire\Game;

use App\Models\Game\GameEncounter;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Aviso global "¡Ganaste una ball!". Reacciona a los eventos de completar
 * tareas y hábitos que ya emite la app, y además consulta cada minuto para
 * cubrir vías sin evento (editor, CalDAV, MCP). No revela la especie.
 */
class BallNotifier extends Component
{
    public int $lastSeenId = 0;

    /** Contador que cambia con cada aviso nuevo para reiniciar la animación. */
    public int $notice = 0;

    public function mount(): void
    {
        $this->lastSeenId = (int) GameEncounter::query()->max('id');
    }

    #[On('task-completed')]
    #[On('habit-feedback')]
    public function check(): void
    {
        $latest = (int) GameEncounter::query()->whereNull('opened_at')->where('id', '>', $this->lastSeenId)->max('id');
        if ($latest > 0) {
            $this->lastSeenId = $latest;
            $this->notice++;
        }
    }

    public function render()
    {
        return view('livewire.game.ball-notifier');
    }
}
