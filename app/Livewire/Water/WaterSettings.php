<?php

namespace App\Livewire\Water;

use App\Livewire\Concerns\ManagesUserCategories;
use App\Livewire\Concerns\WithManagementCard;
use App\Models\DrinkLog;
use App\Models\DrinkType;
use App\Support\WaterGoal;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ajustes de hidratación')]
class WaterSettings extends Component
{
    use ManagesUserCategories;
    use WithManagementCard;

    public const SECTIONS = [
        'drinks' => ['label' => 'Bebidas', 'icon' => 'bi-cup-straw', 'route' => 'water.settings'],
        'goal' => ['label' => 'Meta diaria', 'icon' => 'bi-bullseye', 'route' => 'water.settings.goal'],
        'categories' => ['label' => 'Categorías', 'icon' => 'bi-diagram-3', 'route' => 'water.settings.categories'],
    ];

    public const FACTORS = [
        'all' => 'Todos',
        'low' => 'Hidrata menos (< 1)',
        'neutral' => 'Igual al agua (1)',
        'high' => 'Hidrata más (> 1)',
    ];

    public const USAGES = [
        'all' => 'Todos',
        'used' => 'Con registros',
        'unused' => 'Sin registros',
    ];

    public const SORTS = [
        'name' => 'Nombre (A–Z)',
        'factor' => 'Mayor factor',
        'logs' => 'Más registros',
    ];

    #[Locked]
    public string $section = 'drinks';

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'factor', history: true, except: 'all')]
    public string $factor = 'all';

    #[Url(as: 'usage', history: true, except: 'all')]
    public string $usage = 'all';

    #[Url(as: 'sort', history: true, except: 'name')]
    public string $sort = 'name';

    public ?int $dailyWaterGoal = null;

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $category = '';

    public string $name = '';

    public string $icon = '💧';

    public string $hydrationFactor = '1.00';

    public string $message = '';

    public function mount(string $section = 'drinks'): void
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $this->section = $section;
        $this->useCategoryDomain('drinks');
        $this->dailyWaterGoal = auth()->user()->daily_water_goal;
        $this->normalizeFilters();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function saveGoal(): void
    {
        $data = $this->validate(['dailyWaterGoal' => ['nullable', 'integer', 'between:500,10000']]);
        auth()->user()->update(['daily_water_goal' => $data['dailyWaterGoal']]);
        $this->message = 'Meta diaria actualizada.';
    }

    public function openForm(?string $id = null): void
    {
        $this->resetForm();

        if ($id) {
            $type = DrinkType::find($id);

            if (! $type) {
                $this->message = 'La bebida ya no está disponible.';

                return;
            }

            $this->editingId = $type->id;
            $this->name = $type->name;
            $this->category = $type->category ?? '';
            $this->icon = $type->icon ?: '💧';
            $this->hydrationFactor = number_format((float) $type->hydration_factor, 2, '.', '');
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->icon = trim($this->icon);

        $type = $this->editingId ? DrinkType::find($this->editingId) : new DrinkType();

        if (! $type) {
            $this->message = 'La bebida ya no está disponible.';
            $this->closeForm();

            return;
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('drink_types', 'name')->where(fn ($query) => $query->where('user_id', auth()->id()))->ignore($type->id)],
            'icon' => ['required', 'string', 'max:40'],
            'category' => ['nullable', Rule::in(array_keys(\App\Support\CategoryCatalog::options('drinks')))],
            'hydrationFactor' => ['required', 'numeric', 'min:0', 'max:9.99'],
        ], [], [
            'name' => 'nombre',
            'icon' => 'ícono',
            'hydrationFactor' => 'factor de hidratación',
        ]);

        $type->fill(['category' => $this->category ?: null, 'name' => $data['name'], 'icon' => $data['icon'], 'hydration_factor' => $data['hydrationFactor']])->save();

        $this->message = $this->editingId ? 'Bebida actualizada.' : 'Bebida creada.';
        $this->closeForm();
    }

    public function delete(string $id): void
    {
        $type = DrinkType::find($id);

        if (! $type) {
            $this->message = 'La bebida ya no está disponible.';

            return;
        }

        if (DrinkLog::where('drink_type_id', $type->id)->exists()) {
            $this->message = 'No se puede eliminar una bebida con registros históricos.';

            return;
        }

        $type->delete();
        $this->message = 'Bebida eliminada.';
        $this->resetPage();
    }

    public function applyFilters(string $factor, string $usage): void
    {
        $this->factor = $factor;
        $this->usage = $usage;
        $this->normalizeFilters();
        $this->resetPage();
    }

    public function removeFilter(string $key, ?string $value = null): void
    {
        $this->resetPage();
        match ($key) {
            'factor' => $this->factor = 'all',
            'usage' => $this->usage = 'all',
            default => null,
        };
    }

    public function clearFilters(): void
    {
        $this->resetPage();
        $this->factor = 'all';
        $this->usage = 'all';
    }

    /**
     * @return list<array{key: string, value: ?string, label: string, icon: string}>
     */
    public function activeFilters(): array
    {
        $filters = [];

        if ($this->factor !== 'all') {
            $filters[] = ['key' => 'factor', 'value' => null, 'label' => 'Factor: '.self::FACTORS[$this->factor], 'icon' => 'bi-droplet-half'];
        }

        if ($this->usage !== 'all') {
            $filters[] = ['key' => 'usage', 'value' => null, 'label' => 'Uso: '.self::USAGES[$this->usage], 'icon' => 'bi-clock-history'];
        }

        return $filters;
    }

    public function render()
    {
        $search = trim($this->search);

        $query = DrinkType::query()
            ->withCount('logs')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($this->factor === 'low', fn ($q) => $q->where('hydration_factor', '<', 1))
            ->when($this->factor === 'neutral', fn ($q) => $q->where('hydration_factor', 1))
            ->when($this->factor === 'high', fn ($q) => $q->where('hydration_factor', '>', 1))
            ->when($this->usage === 'used', fn ($q) => $q->has('logs'))
            ->when($this->usage === 'unused', fn ($q) => $q->doesntHave('logs'));

        match ($this->sort) {
            'factor' => $query->orderByDesc('hydration_factor')->orderBy('name'),
            'logs' => $query->orderByDesc('logs_count')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $onDrinks = $this->section === 'drinks';

        return view('livewire.water.water-settings', [
            'drinkTypes' => $onDrinks ? $query->paginate($this->perPage()) : null,
            'totalTypes' => $onDrinks ? DrinkType::count() : 0,
            'activeFilters' => $this->activeFilters(),
            'factors' => self::FACTORS,
            'usages' => self::USAGES,
            'sorts' => self::SORTS,
            'goal' => WaterGoal::forUser(auth()->user()),
            'sections' => self::SECTIONS,
        ] + ($this->section === 'categories' ? $this->categoryViewData() : []));
    }

    private function normalizeFilters(): void
    {
        $this->factor = array_key_exists($this->factor, self::FACTORS) ? $this->factor : 'all';
        $this->usage = array_key_exists($this->usage, self::USAGES) ? $this->usage : 'all';
        $this->sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'name';
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->category = '';
        $this->name = '';
        $this->icon = '💧';
        $this->hydrationFactor = '1.00';
    }
}
