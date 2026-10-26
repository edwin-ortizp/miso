@props(['name', 'selected' => '', 'preview' => 'Vista previa', 'icon' => null])
<div class="d-flex flex-column gap-2">
    <x-ui.select :name="$name" label="Color" :selected="$selected"
                 :options="\App\Support\Ui\CatalogColors::OPTIONS" placeholder="Predeterminado"
                 wire:model.live="{{ $name }}" />
    <div aria-live="polite" aria-atomic="true">
        <span class="md-body-small">Vista previa:</span>
        <x-ui.catalog-chip :color="$selected" :icon="$icon">{{ $preview ?: 'Vista previa' }}</x-ui.catalog-chip>
    </div>
</div>
