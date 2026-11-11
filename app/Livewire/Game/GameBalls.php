<?php

namespace App\Livewire\Game;

use App\Models\Game\GameEncounter;
use App\Models\Game\GameSpecies;
use App\Services\Game\EncounterOpener;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Balls pendientes. La recompensa ya está guardada antes de animar: si la
 * página se recarga, la ball sigue pendiente o ya aparece abierta.
 */
#[Layout('layouts.app')]
#[Title('Colección')]
class GameBalls extends Component
{
    /** @var array<string, mixed>|null Resultado de la última ball abierta (diálogo de revelación). */
    public ?array $revealed = null;

    public bool $showReveal = false;

    public int $revealKey = 0;

    public string $error = '';

    public function open(int $encounterId, EncounterOpener $opener): void
    {
        $this->error = '';
        try {
            $this->revealed = $opener->open($encounterId, (int) auth()->id());
            $this->revealKey++;
            $this->showReveal = true;
        } catch (Throwable $exception) {
            Log::warning('game.open_failed', ['encounter' => $encounterId, 'error' => $exception->getMessage()]);
            $this->revealed = null;
            $this->showReveal = false;
            $this->error = 'No pudimos abrirla. Tu ball sigue guardada.';
        }
    }

    public function openNext(EncounterOpener $opener): void
    {
        $next = GameEncounter::query()->whereNull('opened_at')->orderBy('granted_at')->orderBy('id')->value('id');
        if ($next) {
            $this->open((int) $next, $opener);
        } else {
            $this->showReveal = false;
        }
    }

    public function render()
    {
        $pending = GameEncounter::query()
            ->whereNull('opened_at')
            ->with(['event' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderByDesc('granted_at')->orderByDesc('id')
            ->get();

        return view('livewire.game.game-balls', [
            'pending' => $pending,
            'streak' => app(\App\Services\Game\Streak::class)->summary((int) auth()->id()),
            'challenge' => app(\App\Services\Game\DailyChallenge::class)->forToday((int) auth()->id()),
            'revealedSpecies' => $this->revealed ? GameSpecies::find($this->revealed['species_id']) : null,
        ]);
    }
}
