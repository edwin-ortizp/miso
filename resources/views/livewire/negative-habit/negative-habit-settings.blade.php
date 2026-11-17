<x-module-shell module="negative-habits" title="Ajustes de hábitos a evitar" archetype="settings">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Crear categoría', 'icon' => 'bi-plus-lg', 'action' => 'openCategoryForm']" />
    </x-slot:actions>

    @if ($categoryMessage)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $categoryMessage }}</div>
    @endif

    @include('livewire.category.partials.section')
</x-module-shell>
