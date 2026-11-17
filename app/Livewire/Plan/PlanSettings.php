<?php

namespace App\Livewire\Plan;

use App\Livewire\Concerns\WithManagementCard;
use App\Models\PlanCategory;
use App\Models\PlanTag;
use App\Support\Ui\BootstrapIcons;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ajustes de planes')]
class PlanSettings extends Component
{
    use WithManagementCard;

    public const SECTIONS = [
        'tags' => ['label' => 'Tags de planes', 'icon' => 'bi-tags', 'route' => 'plans.settings'],
        'categories' => ['label' => 'Categorías de planes', 'icon' => 'bi-grid', 'route' => 'plans.settings.categories'],
    ];

    #[Locked]
    public string $section = 'tags';

    #[Url(history: true)]
    public string $q = '';

    public bool $showForm = false;

    #[Locked]
    public ?string $editingId = null;

    public string $parent_id = '';

    public string $name = '';

    public string $icon = 'bi-tag';

    public string $message = '';

    private function model(): string
    {
        return $this->section === 'categories' ? PlanCategory::class : PlanTag::class;
    }

    public function mount(string $section = 'tags'): void
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $this->section = $section;
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function openForm(?string $id = null): void
    {
        $this->resetValidation();
        $this->parent_id = '';
        $this->message = '';
        $this->editingId = null;
        $this->name = '';
        $this->icon = $this->section === 'categories' ? 'bi-grid' : 'bi-tag';
        if ($id) {
            $record = $this->model()::query()->findOrFail($id);
            $this->editingId = $record->id;
            $this->name = $record->name;
            $this->parent_id = $record->parent_id ?? '';
            $this->icon = $record->icon;
        }
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $category = $this->section === 'categories';
        $this->name = preg_replace('/\s+/u', ' ', trim($this->name));
        $this->validate([
            'name' => ['required', 'string', 'max:'.($category ? 80 : 60), Rule::unique($category ? 'plan_categories' : 'plan_tags', $category ? 'normalized_name' : 'name')->where('user_id', auth()->id())->ignore($this->editingId)],
            'icon' => ['required', Rule::in(array_keys(BootstrapIcons::options()))],
        ], ['name.unique' => 'Ya tienes una etiqueta con ese nombre.']);
        if ($category && PlanCategory::query()->where('normalized_name', PlanCategory::normalized($this->name))->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists()) {
            $this->addError('name', 'Ya tienes una categoría con ese nombre.');

            return;
        }
        $model = $this->model();
        $record = $this->editingId ? $model::query()->findOrFail($this->editingId) : new $model;
        DB::transaction(function () use ($record) {
            if ($this->section === 'categories') { $record->parent_id = $this->parent_id ?: null; }
            $record->fill(['name' => $this->name, 'icon' => $this->icon]);
            $record->save();
        });
        $this->message = 'Guardado correctamente.';
        $this->closeForm();
    }

    public function delete(string $id): void
    {
        DB::transaction(function () use ($id) {
            $record = $this->model()::query()->lockForUpdate()->withCount('plans')->findOrFail($id);
            if ($this->section === 'categories' && $record->children()->exists()) {
                $this->message = 'Mueve primero las subcategorías antes de eliminar esta categoría.';
                return;
            }
            if ($record->plans_count > 0) {
                $this->message = "No se puede eliminar «{$record->name}»: está asociada a {$record->plans_count} ".($record->plans_count === 1 ? 'plan.' : 'planes.');

                return;
            }
            $record->delete();
            $this->message = 'Eliminado correctamente.';
            $this->resetPage();
        });
    }

    public function render()
    {
        return view('livewire.plan.plan-settings', [
            'records' => $this->model()::query()->when($this->section === 'categories', fn ($q) => $q->with('parent')->orderByRaw('COALESCE((SELECT parent.name FROM plan_categories AS parent WHERE parent.id = plan_categories.parent_id), name)')->orderByRaw('parent_id IS NOT NULL'))->withCount('plans')->when(trim($this->q) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->q).'%'))->orderBy('name')->paginate($this->perPage()),
            'icons' => BootstrapIcons::options(),
        ]);
    }
}
