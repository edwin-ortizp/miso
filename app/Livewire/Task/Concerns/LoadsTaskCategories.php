<?php

namespace App\Livewire\Task\Concerns;

use App\Models\Task;
use App\Models\TaskCategory;
use App\Support\DefaultTaskCategories;

/**
 * Expone el catálogo de categorías del usuario (Tareas › Ajustes) como `$categories` (key => nombre).
 * Incluye al final las categorías que tienen tareas pero ya no están en el catálogo, para no ocultarlas.
 * Se recarga en cada petición para que las vistas reflejen los cambios hechos en Ajustes o por MCP.
 */
trait LoadsTaskCategories
{
    public array $categories = [];

    protected array $categoryColors = [];

    public function categoryColor(?string $key): ?string
    {
        return $this->categoryColors[$key] ?? null;
    }

    public function bootLoadsTaskCategories(): void
    {
        $this->categories = $this->taskCategoryOptions();
    }

    public function hydrateLoadsTaskCategories(): void
    {
        $this->categories = $this->taskCategoryOptions();
    }

    private function taskCategoryOptions(): array
    {
        $this->categoryColors = auth()->id()
            ? TaskCategory::where('user_id', auth()->id())->pluck('color', 'key')->all() : [];
        $options = TaskCategory::options();

        Task::query()->whereNotNull('category')->where('category', '!=', '')
            ->whereNotIn('category', array_keys($options))
            ->distinct()->orderBy('category')->pluck('category')
            ->each(function (string $key) use (&$options) {
                $options[$key] = DefaultTaskCategories::labelFor($key);
            });

        return $options;
    }
}
