<?php

namespace App\Livewire\Category;

use App\Livewire\Concerns\ManagesUserCategories;
use App\Support\CategoryCatalog;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Página independiente de categorías de un catálogo (`/categories/{dominio}`). Se conserva por
 * compatibilidad con enlaces antiguos; cada módulo también la ofrece como sección de sus ajustes.
 * La lógica vive en `ManagesUserCategories` y la tabla en el parcial `category/partials/section`.
 */
#[Layout('layouts.app')]
class CategorySettings extends Component
{
    use ManagesUserCategories;

    public function mount(string $domain): void
    {
        $this->useCategoryDomain($domain);
    }

    public function render()
    {
        return view('livewire.category.category-settings', $this->categoryViewData() + [
            'domain' => $this->categoryDomain,
            'module' => CategoryCatalog::DOMAINS[$this->categoryDomain][2],
        ]);
    }
}
