<?php

namespace App\Livewire\Game;

use App\Services\Game\Guardian\GuardianAdvisor;
use App\Services\Game\ModuleGuardian;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Guardián de módulo con consejo (CHG-055/057). Componente hijo aislado: el
 * botón "Otro consejo" y el refresco cada 5 min solo re-renderizan la franja.
 */
class GuardianCard extends Component
{
    public string $module = 'tasks';

    /** `top`: arriba de la lista en pantallas angostas; `rail`: dentro del panel lateral. */
    public string $variant = 'rail';

    public ?array $guardian = null;

    public string $adviceKey = '';

    public string $advice = '';

    /** Acción opcional del consejo (CHG-058), p. ej. "Ir al hábito sugerido". */
    public ?array $action = null;

    /** CHG-062: ficha del guardián en un modal (se arma solo cuando está abierto). */
    public bool $showSheet = false;

    public function mount(): void
    {
        $this->guardian = app(ModuleGuardian::class)->pick((int) auth()->id(), $this->module);
        if ($this->guardian) {
            $this->advise(null);
        }
    }

    public function openSheet(): void
    {
        $this->showSheet = $this->guardian !== null;
    }

    public function another(): void
    {
        $this->advise($this->adviceKey);
    }

    #[On('task-records-changed')]
    public function refreshAdvice(): void
    {
        $this->advise(null);
    }

    private function advise(?string $except): void
    {
        if (! $this->guardian) {
            return;
        }
        $advice = app(GuardianAdvisor::class)->advise($this->module, now(), $except, $this->progress());
        $this->adviceKey = $advice['key'];
        $this->advice = $advice['html'];
        $this->action = $advice['action'] ?? null;
    }

    /** @return array{done: int, total: int, care: bool} Datos de la frase por avance (CHG-055). */
    private function progress(): array
    {
        return app(\App\Services\Game\Guardian\ModuleProgress::class)->for($this->module);
    }

    /** Si el usuario tiene guardián en este módulo (para ocultar el coach antiguo de Hábitos). */
    public static function visibleFor(int $userId, string $module): bool
    {
        return app(ModuleGuardian::class)->pick($userId, $module) !== null;
    }

    public function render()
    {
        return view('livewire.game.guardian-card', [
            'refresh' => (int) config('game.guardian_advice.refresh_seconds', 300),
            'sheet' => $this->showSheet && $this->guardian
                ? app(\App\Services\Game\SpeciesSheet::class)->build((int) $this->guardian['species_id'])
                : null,
        ]);
    }
}
