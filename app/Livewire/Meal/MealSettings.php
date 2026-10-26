<?php

namespace App\Livewire\Meal;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\MealCategory;
use App\Models\ShoppingItem;
use App\Models\StorageLocation;
use App\Models\Store;
use App\Services\Meal\CatalogNames;
use App\Services\Meal\StoreCatalogManager;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ajustes de Comidas')]
class MealSettings extends Component
{
    use WithManagementCard;

    #[Locked]
    public string $section = 'stores';

    #[Url(as: 'q')]
    public string $search = '';

    public array $schedules = [];

    public bool $showForm = false;
    public ?string $editingId = null;
    public string $parent_id = '';

    public string $name = '';
    public bool $confirmSimilar = false;
    public string $message = '';
    public bool $showMerge = false;
    public string $mergeSourceId = '';
    public string $mergeTargetId = '';

    #[Url(as: 'archived')]
    public bool $showArchived = false;

    public function mount(string $section = 'stores'): void
    {
        abort_unless(in_array($section, ['stores', 'categories', 'locations', 'schedules'], true), 404);
        $this->section = $section;
        $this->schedules = app(\App\Services\Meal\MealSchedule::class)->profiles((int) auth()->id());
    }

    public function saveSchedules(): void
    {
        abort_unless(auth()->check() && $this->section === 'schedules', 403);
        $rules = [];
        foreach (['weekday', 'weekend'] as $profile) {
            foreach (array_keys(\App\Services\Meal\MealSchedule::TYPES) as $type) {
                $rules["schedules.$profile.$type.time"] = ['required', 'date_format:H:i'];
                $rules["schedules.$profile.$type.duration"] = ['required', 'integer', 'between:1,1440'];
            }
        }
        $validated = $this->validate($rules);
        $setting = \App\Models\ModuleSetting::where('user_id', auth()->id())->firstOrCreate(['module' => 'meals']);
        $setting->update(['settings' => array_merge($setting->settings ?? [], ['schedules' => $validated['schedules']])]);
        $this->message = 'Horarios guardados.';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(): void
    {
        $this->confirmSimilar = false;
    }

    public function openForm(?string $id = null): void
    {
        $this->resetValidation();
        $this->parent_id = '';
        $this->reset(['editingId', 'name', 'confirmSimilar', 'message']);
        if ($id) {
            $record = $this->model()::where('user_id', auth()->id())->findOrFail($id);
            $this->editingId = $record->id;
            $this->name = $record->name;
            $this->parent_id = $record->parent_id ?? '';
        }
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:255']]);
        if ($this->section === 'stores') {
            try {
                if ($this->editingId) {
                    StoreCatalogManager::rename($this->editingId, $this->name);
                } else {
                    CatalogNames::createStore($this->name, $this->confirmSimilar);
                }
            } catch (\InvalidArgumentException $exception) {
                $this->addError('name', $exception->getMessage());
                $this->confirmSimilar = str_starts_with($exception->getMessage(), 'Ya hay tiendas parecidas');

                return;
            }
            $this->dispatch('stores-updated');
        } elseif ($this->section === 'locations') {
            $location = $this->editingId ? StorageLocation::where('user_id', auth()->id())->findOrFail($this->editingId) : new StorageLocation;
            try {
                $location->fill(['parent_id' => $this->parent_id ?: null, 'name' => $this->name])->save();
            } catch (ValidationException $exception) {
                $this->setErrorBag($exception->validator->errors());

                return;
            }
        } else {
            $normalized = CatalogNames::normalize($this->name);
            if ($normalized === '') {
                $this->addError('name', 'Escribe un nombre válido.');
                return;
            }
            if (MealCategory::where('user_id', auth()->id())->where('normalized_name', $normalized)->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))->exists()) {
                $this->addError('name', 'Ya existe una categoría con ese nombre.');
                return;
            }
            $category = $this->editingId ? MealCategory::where('user_id', auth()->id())->findOrFail($this->editingId) : new MealCategory;
            if (! $category->exists) {
                $category->key = 'cat_'.Str::uuid();
            }
            $category->fill(['parent_id' => $this->parent_id ?: null, 'name' => $this->name, 'normalized_name' => $normalized])->save();
        }
        $this->message = 'Cambios guardados.';
        $this->closeForm();
    }

    public function delete(string $id): void
    {
        abort_if($this->section === 'locations', 404);
        $this->resetValidation();
        if ($this->section === 'stores') {
            try {
                StoreCatalogManager::delete($id);
            } catch (ValidationException $exception) {
                $this->message = $exception->getMessage();
                return;
            }
            $this->dispatch('stores-updated');
        } else {
            $category = MealCategory::where('user_id', auth()->id())->findOrFail($id);
            if ($category->children()->exists()) {
                $this->message = 'Mueve primero las subcategorías antes de eliminar esta categoría.';
                return;
            }
            if (ShoppingItem::where('user_id', auth()->id())->where('category_id', $category->id)->exists()) {
                $this->message = 'Esta categoría tiene ingredientes asociados y no se puede eliminar.';
                return;
            }
            $category->delete();
        }
        $this->message = 'Registro eliminado.';
        $this->resetPage();
    }

    public function archive(string $id): void
    {
        abort_unless($this->section === 'locations', 404);
        StorageLocation::where('user_id', auth()->id())->findOrFail($id)->archive();
        $this->message = 'Ubicación archivada. Los productos asignados la conservan.';
    }

    public function restore(string $id): void
    {
        abort_unless($this->section === 'locations', 404);
        try {
            StorageLocation::where('user_id', auth()->id())->findOrFail($id)->restore();
        } catch (ValidationException $exception) {
            $this->message = collect($exception->errors())->flatten()->first();

            return;
        }
        $this->message = 'Ubicación restaurada.';
    }

    public function updatedShowArchived(): void
    {
        $this->resetPage();
    }

    /** Active root locations; empty when the edited location already has children (it must stay a root). */
    private function locationParents(): array
    {
        $locations = StorageLocation::where('user_id', auth()->id());
        if ($this->editingId && (clone $locations)->where('parent_id', $this->editingId)->exists()) {
            return [];
        }

        return $locations->active()->whereNull('parent_id')->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    private function model(): string
    {
        return match ($this->section) {
            'stores' => Store::class,
            'locations' => StorageLocation::class,
            default => MealCategory::class,
        };
    }

    public function openMerge(string $id): void
    {
        abort_unless($this->section === 'stores', 404);
        Store::where('user_id', auth()->id())->findOrFail($id);
        $this->resetValidation();
        $this->mergeSourceId = $id;
        $this->mergeTargetId = '';
        $this->showMerge = true;
    }

    public function closeMerge(): void
    {
        $this->showMerge = false;
        $this->resetValidation();
    }

    public function merge(): void
    {
        abort_unless($this->section === 'stores', 404);
        $this->validate(['mergeTargetId' => ['required', 'different:mergeSourceId', Rule::exists('stores', 'id')->where('user_id', auth()->id())]]);
        $source = Store::where('user_id', auth()->id())->findOrFail($this->mergeSourceId);
        $target = Store::where('user_id', auth()->id())->findOrFail($this->mergeTargetId);
        $source->mergeInto($target);
        $this->showMerge = false;
        $this->message = 'Tiendas fusionadas.';
        $this->dispatch('stores-updated');
    }

    public function render()
    {
        if ($this->section === 'schedules') {
            return view('livewire.meal.meal-schedules');
        }
        $query = $this->model()::where('user_id', auth()->id())->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'));
        if ($this->section === 'stores') {
            $query->withCount(['prices', 'purchases']);
        }
        if ($this->section === 'categories') {
            $query->with('parent')->orderByRaw('COALESCE((SELECT parent.name FROM meal_categories AS parent WHERE parent.id = meal_categories.parent_id), name)')->orderByRaw('parent_id IS NOT NULL');
        }
        if ($this->section === 'locations') {
            $query->with('parent')->when($this->showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
                ->orderByRaw('COALESCE((SELECT parent.name FROM storage_locations AS parent WHERE parent.id = storage_locations.parent_id), name)')->orderByRaw('parent_id IS NOT NULL');
        }
        return view('livewire.meal.meal-settings', [
            'records' => $query->orderBy('name')->paginate($this->perPage()),
            'categoryCounts' => ShoppingItem::where('user_id', auth()->id())->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id'),
            'locationCounts' => $this->section === 'locations' ? ShoppingItem::where('user_id', auth()->id())->whereNotNull('storage_location_id')->selectRaw('storage_location_id, count(*) as total')->groupBy('storage_location_id')->pluck('total', 'storage_location_id') : collect(),
            'locationParents' => $this->section === 'locations' ? $this->locationParents() : [],
            'storeOptions' => Store::where('user_id', auth()->id())->where('id', '!=', $this->mergeSourceId)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
