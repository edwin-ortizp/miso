<x-catalog.example title="Estados" description="Sin marcar y marcada; el nombre accesible incluye el elemento y la acción disponible.">
    <div class="d-flex gap-2">
        <x-ui.favorite-toggle :favorite="false" label="Agua" />
        <x-ui.favorite-toggle :favorite="true" label="Café" />
    </div>
</x-catalog.example>
