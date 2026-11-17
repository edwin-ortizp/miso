<x-module-shell :module="$module" :title="'Categorías · '.$categoryLabel" archetype="settings">
    <x-slot:actions><x-ui.fab label="Crear categoría" icon="bi-plus-lg" action="openCategoryForm" :module="$module" /></x-slot:actions>
    <nav aria-label="Catálogos de categorías">
        @foreach (\App\Support\CategoryCatalog::DOMAINS as $key => $definition)
            @if ($definition[2] === $module)
                <x-ui.action variant="text" :href="route('categories.settings', $key)" :aria-current="$key === $domain ? 'page' : null">{{ $definition[1] }}</x-ui.action>
            @endif
        @endforeach
    </nav>
    @if ($categoryMessage)<p role="status">{{ $categoryMessage }}</p>@endif
    @include('livewire.category.partials.section')
</x-module-shell>
