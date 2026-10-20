<?php

namespace App\Livewire\Meal;

use App\Livewire\Concerns\WithManagementCard;
use App\Services\Meal\MealNeeds;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Qué cocinar')]
class MealSuggestions extends Component
{
    use WithManagementCard;

    public const AVAILABILITY = ['ready' => 'Puedo cocinar', 'missing' => 'Me faltan ingredientes', 'incomplete' => 'Datos incompletos', 'all' => 'Todas'];

    public $portions = 1;
    public $days = 7;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'disponibilidad', except: 'ready')]
    public string $availability = 'ready';

    public ?string $detailId = null;

    public function updatedPortions(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAvailability(): void
    {
        if (! array_key_exists($this->availability, self::AVAILABILITY)) {
            $this->availability = 'ready';
        }
        $this->resetPage();
    }

    public function showDetail(string $recipeId): void
    {
        $this->detailId = $recipeId;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        if ($key === 'availability') {
            $this->availability = 'all';
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->availability = 'all';
        $this->resetPage();
    }

    public function render()
    {
        $suggestions = app(MealNeeds::class)->suggestions((int) auth()->id(), min(999999, max(0.01, (float) $this->portions)), min(365, max(0, (int) $this->days)));
        $search = mb_strtolower(trim($this->search));

        $recipes = collect($suggestions['recipes'])
            ->when($search !== '', fn ($recipes) => $recipes->filter(fn ($recipe) => str_contains(mb_strtolower($recipe['name']), $search)))
            ->filter(fn ($recipe) => match ($this->availability) {
                'ready' => $recipe['can_cook'],
                'missing' => ! $recipe['can_cook'] && ! $recipe['incomplete'],
                'incomplete' => $recipe['incomplete'],
                default => true,
            })
            ->values();

        return view('livewire.meal.meal-suggestions', [
            'suggestions' => $suggestions,
            'recipes' => $this->paginateCollection($recipes),
            'detail' => $this->detailId ? collect($suggestions['recipes'])->firstWhere('id', $this->detailId) : null,
        ]);
    }
}
