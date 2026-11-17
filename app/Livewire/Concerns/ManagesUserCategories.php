<?php

namespace App\Livewire\Concerns;

use App\Models\UserCategory;
use App\Support\CategoryCatalog;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * CRUD de categorías del usuario (`user_categories`) para incrustarlo como sección «Categorías»
 * dentro de los ajustes de un módulo. Las propiedades llevan prefijo `category` para no chocar
 * con las del catálogo principal del componente. Las reglas (dos niveles, duplicados, en uso)
 * viven en `UserCategory` y `CategoryCatalog`.
 */
trait ManagesUserCategories
{
    #[Locked]
    public string $categoryDomain = '';

    #[Locked]
    public ?string $categoryEditingId = null;

    public string $categoryName = '';

    public string $categoryParentId = '';

    public string $categorySearch = '';

    public bool $showCategoryForm = false;

    public string $categoryMessage = '';

    protected function useCategoryDomain(string $domain): void
    {
        abort_unless(isset(CategoryCatalog::DOMAINS[$domain]), 404);
        $this->categoryDomain = $domain;
    }

    private function categoryQuery()
    {
        return UserCategory::where('user_id', auth()->id())->where('domain', $this->categoryDomain);
    }

    public function openCategoryForm(?string $id = null): void
    {
        $this->resetValidation();
        $this->reset(['categoryName', 'categoryParentId', 'categoryEditingId']);

        if ($id) {
            $category = $this->categoryQuery()->findOrFail($id);
            $this->categoryEditingId = $category->id;
            $this->categoryName = $category->name;
            $this->categoryParentId = $category->parent_id ?? '';
        }

        $this->showCategoryForm = true;
    }

    public function closeCategoryForm(): void
    {
        $this->showCategoryForm = false;
        $this->resetValidation();
    }

    public function saveCategory(): void
    {
        $this->categoryName = trim($this->categoryName);
        $this->validate([
            'categoryName' => ['required', 'string', 'max:100'],
            'categoryParentId' => ['nullable', 'uuid'],
        ], [], ['categoryName' => 'nombre']);

        $duplicate = $this->categoryQuery()->where('normalized_name', mb_strtolower($this->categoryName))
            ->when($this->categoryEditingId, fn ($q) => $q->whereKeyNot($this->categoryEditingId))->exists();

        if ($duplicate) {
            $this->addError('categoryName', 'Ya tienes una categoría con ese nombre.');

            return;
        }

        $category = $this->categoryEditingId
            ? $this->categoryQuery()->findOrFail($this->categoryEditingId)
            : new UserCategory(['user_id' => auth()->id(), 'domain' => $this->categoryDomain, 'key' => 'cat_'.Str::uuid()]);

        $category->fill(['name' => $this->categoryName, 'parent_id' => $this->categoryParentId ?: null])->save();
        $this->categoryMessage = 'Categoría guardada.';
        $this->closeCategoryForm();
    }

    public function deleteCategory(string $id): void
    {
        try {
            $this->categoryQuery()->findOrFail($id)->delete();
            $this->categoryMessage = 'Categoría eliminada.';
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->categoryMessage = $e->validator->errors()->first();
        }
    }

    /**
     * Datos para el parcial `livewire.category.partials.section`.
     *
     * @return array<string, mixed>
     */
    protected function categoryViewData(): array
    {
        $records = $this->categoryQuery()->with('parent')->orderBy('name')->get();
        $ordered = $records->whereNull('parent_id')
            ->flatMap(fn ($root) => collect([$root])->concat($records->where('parent_id', $root->id)));
        $search = mb_strtolower(trim($this->categorySearch));

        return [
            'categoryRecords' => $ordered->filter(fn ($c) => $search === '' || Str::contains(mb_strtolower($c->path_label), $search)),
            'categoryParents' => $this->categoryEditingId && $records->where('parent_id', $this->categoryEditingId)->isNotEmpty()
                ? [] : $records->whereNull('parent_id')->where('id', '!=', $this->categoryEditingId)->pluck('name', 'id')->all(),
            'categoryLabel' => CategoryCatalog::DOMAINS[$this->categoryDomain][1],
        ];
    }
}
