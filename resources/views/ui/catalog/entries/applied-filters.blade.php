<x-catalog.example title="Filtros aplicados" description="Franja de chips removibles con «Limpiar filtros» dentro de una card de gestión; un filtro por defecto como «Fecha: Hoy» se muestra igual.">
    <div class="md-mcard__strip" role="group" aria-label="Filtros aplicados">
        <x-ui.applied-filters :filters="[
            ['key' => 'date', 'value' => null, 'label' => 'Fecha: Hoy', 'icon' => 'bi-calendar-event'],
            ['key' => 'drink', 'value' => null, 'label' => 'Bebida: Agua', 'icon' => 'bi-cup-straw'],
        ]" />
        <button type="button" class="md-btn-text md-btn--sm md-applied-clear">Limpiar filtros</button>
    </div>
</x-catalog.example>
