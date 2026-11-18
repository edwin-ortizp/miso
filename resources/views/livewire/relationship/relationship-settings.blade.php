@php
    $createAction = ['label' => 'Crear categoría', 'icon' => 'bi-plus-lg', 'action' => 'openCategoryForm'];
@endphp

<x-module-shell module="relationships" title="Ajustes de relaciones" archetype="settings">
    <x-slot:actions>
        <x-module-actions :primary="$createAction" />
    </x-slot:actions>

    @if ($categoryMessage)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $categoryMessage }}</div>
    @endif

    <div class="module-settings-workspace">
        <div>
            @include('livewire.category.partials.section')
        </div>
        <x-settings-sections label="Secciones de Ajustes de relaciones" :items="$sections" :current="$section" />
    </div>
</x-module-shell>
