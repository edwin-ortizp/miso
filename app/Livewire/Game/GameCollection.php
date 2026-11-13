<?php

namespace App\Livewire\Game;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameDiscovery;
use App\Models\Game\GameEvent;
use App\Models\Game\GameSpecies;
use App\Models\Game\GameTeamMember;
use App\Models\Game\GameWeek;
use App\Services\Game\CompanionProgress;
use App\Services\Game\SpeciesImageProvider;
use App\Services\Game\WeekPlanner;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Catálogo con búsqueda, filtros y paginación; la ficha de cada especie se abre
 * en un modal, también desde un enlace (`?species={id}`).
 */
#[Layout('layouts.app')]
#[Title('Colección')]
class GameCollection extends Component
{
    use WithManagementCard;

    public const STATES = ['all' => 'Todas', 'found' => 'Descubiertas', 'missing' => 'Por descubrir', 'team' => 'Mi equipo'];

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'state', history: true, except: 'all')]
    public string $state = 'all';

    #[Url(as: 'type', history: true, except: '')]
    public string $type = '';

    #[Url(as: 'rarity', history: true, except: '')]
    public string $rarity = '';

    #[Url(as: 'species', except: null)]
    public ?int $selectedId = null;

    public bool $showSheet = false;

    public int $historyLimit = 20;

    public string $sheetMessage = '';

    public static function perPageOptions(): array
    {
        return [24, 60, 120];
    }

    public function mount(): void
    {
        if (! in_array($this->perPage, static::perPageOptions(), true)) {
            $this->perPage = 60;
        }
        if (! array_key_exists($this->state, self::STATES)) {
            $this->state = 'all';
        }
        $this->showSheet = $this->selectedId !== null;
    }

    protected function filterProperties(): array
    {
        return ['search', 'state', 'type', 'rarity'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, $this->filterProperties(), true)) {
            $this->resetPage();
        }
    }

    public function updatedShowSheet(bool $open): void
    {
        if (! $open) {
            $this->selectedId = null;
        }
    }

    public function showSpecies(int $speciesId): void
    {
        $this->selectedId = $speciesId;
        $this->historyLimit = 20;
        $this->sheetMessage = '';
        $this->showSheet = true;
    }

    public function moreHistory(): void
    {
        $this->historyLimit += 20;
    }

    /** Chat del compañero (CHG-100): este ejemplar aparece en lugar del botón de crear. */
    public function makeFavorite(int $companionId, \App\Services\Game\GameSettings $settings): void
    {
        $companion = \App\Models\Game\GameCompanion::query()->with('species')->find($companionId);
        if (! $companion) {
            return;
        }
        $settings->save((int) auth()->id(), ['favorite_companion_id' => $companion->id, 'creation_style' => 'pokemon']);
        $this->sheetMessage = "{$companion->species?->name} es tu favorito. Te acompañará al crear.";
    }

    public function evolve(int $companionId, int $speciesId, CompanionProgress $progress): void
    {
        try {
            $target = $progress->evolveTo($companionId, $speciesId, (int) auth()->id());
            $this->selectedId = $target->id;
            $this->sheetMessage = "¡Evolucionó a {$target->name}!";
        } catch (Throwable) {
            $this->sheetMessage = 'Esa evolución aún no está disponible.';
        }
    }

    public function render(SpeciesImageProvider $images, CompanionProgress $progress)
    {
        $discoveredIds = GameDiscovery::query()->pluck('species_id')
            ->merge(GameCompanion::query()->pluck('species_id'))->unique()->values();
        $teamIds = $this->teamSpeciesIds();

        $query = GameSpecies::query()->where('active', true)->orderBy('number');
        match ($this->state) {
            'found' => $query->whereIn('id', $discoveredIds),
            'missing' => $query->whereNotIn('id', $discoveredIds),
            'team' => $query->whereIn('id', $teamIds),
            default => null,
        };
        if (in_array($this->rarity, config('game.rarities'), true)) {
            $query->where('rarity', $this->rarity);
        }
        if ($this->type !== '') {
            $query->whereJsonContains('types', $this->type);
        }
        $term = trim($this->search);
        if ($term !== '') {
            $number = ltrim($term, '#');
            $query->where(function ($inner) use ($term, $number, $discoveredIds): void {
                // Por nombre solo entre descubiertas: buscar no revela especies ocultas.
                $inner->where(fn ($q) => $q->whereIn('id', $discoveredIds)->where('name', 'like', '%'.$term.'%'));
                if ($number !== '' && ctype_digit($number)) {
                    $inner->orWhere('number', (int) $number);
                }
            });
        }

        $page = $query->paginate($this->perPage());
        $found = $discoveredIds->flip();
        $teamLookup = $teamIds->flip();
        $page->setCollection($page->getCollection()->map(fn (GameSpecies $item) => [
            'species' => $item,
            'discovered' => $found->has($item->id),
            'inTeam' => $teamLookup->has($item->id),
            'image' => $images->url($item),
        ]));

        return view('livewire.game.game-collection', [
            'entries' => $page,
            'total' => GameSpecies::query()->where('active', true)->count(),
            'discoveredCount' => $discoveredIds->count(),
            'activeFilters' => collect([$this->state !== 'all', $this->type !== '', $this->rarity !== ''])->filter()->count(),
            'types' => $this->typeOptions(),
            // Álbumes por tipo (CHG-078): miden el set completo del tipo, sin búsqueda ni rareza.
            'albums' => app(\App\Services\Game\Albums::class)->overview((int) auth()->id()),
            'rarities' => config('game.rarities'),
            'states' => self::STATES,
            'favoriteCompanionId' => app(\App\Services\Game\GameSettings::class)->for((int) auth()->id())['favorite_companion_id'],
            'sheet' => $this->showSheet && $this->selectedId
                ? $this->sheet($this->selectedId, $found, $teamLookup)
                : null,
        ]);
    }

    /** @return Collection<int, int> especies actuales de los integrantes del equipo de esta semana */
    private function teamSpeciesIds(): Collection
    {
        $week = GameWeek::query()->whereDate('week_start', WeekPlanner::weekStart(now())->toDateString())->first();
        if (! $week) {
            return collect();
        }

        return GameCompanion::query()
            ->whereIn('id', GameTeamMember::query()->where('game_week_id', $week->id)->pluck('companion_id'))
            ->pluck('species_id');
    }

    /** @return array<int, string> tipos de las especies descubiertas (no revela tipos ocultos) */
    private function typeOptions(): array
    {
        return GameSpecies::query()
            ->whereIn('id', GameDiscovery::query()->pluck('species_id'))
            ->whereNotNull('types')->pluck('types')->flatten()->unique()->sort()->values()->all();
    }

    /** @return array<string, mixed>|null Lógica compartida con los guardianes (CHG-062). */
    private function sheet(int $speciesId, Collection $discoveredIds, Collection $teamLookup): ?array
    {
        return app(\App\Services\Game\SpeciesSheet::class)->build($speciesId, $discoveredIds, $teamLookup, $this->historyLimit);
    }
}
