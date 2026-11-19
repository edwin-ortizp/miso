@php
    $createAction = ['label' => 'Registrar mood', 'icon' => 'bi-emoji-smile', 'event' => 'mood-editor', 'detail' => ['mode' => 'create'], 'create' => true];
    $activeFilters = count(array_filter([$emotionFilter, $from, $to]));
@endphp

<x-module-shell module="relationships" :title="$relationship->full_name"
                :tabs="\App\Support\Ui\Tabs\PersonTabs::for($relationship)" :back="\App\Support\Ui\Tabs\PersonTabs::back()">
    <x-slot:actions>
        <x-module-actions :primary="$createAction" />
    </x-slot:actions>

    <x-ui.management-card id="relationship-mood" title="Mood" icon="bi-emoji-smile"
                          :create-actions="[$createAction]" :count="'('.$entries->total().')'"
                          search="search" search-placeholder="Buscar registros" :active-filters="$activeFilters"
                          :paginator="$entries" noun="registros">
        <x-slot:filters>
            <x-ui.select name="emotionFilter" label="Emoción" placeholder="Todas las emociones"
                         :options="$emotions" :selected="$emotionFilter" wire:model.live="emotionFilter" />
            <x-ui.field name="from" label="Desde" type="date" wire:model.live="from" />
            <x-ui.field name="to" label="Hasta" type="date" wire:model.live="to" />
        </x-slot:filters>

        @if ($entries->isEmpty())
            @if ($search !== '' || $activeFilters)
                <x-ui.state variant="empty" icon="bi-search" title="Sin resultados" message="Prueba con otra búsqueda o ajusta los filtros." />
            @else
                <x-ui.state variant="empty" icon="bi-emoji-smile" title="Sin registros de mood">
                    <x-slot:actions>
                        <x-ui.action variant="outlined" x-on:click="$dispatch('mood-editor', {mode: 'create'})">Registrar mood</x-ui.action>
                    </x-slot:actions>
                </x-ui.state>
            @endif
        @else
            <table class="md-table md-table--stack">
                <thead><tr>
                    <th scope="col">Fecha</th><th scope="col">Emoción</th><th scope="col">Detalle</th>
                    <th scope="col">Contactos</th><th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr></thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr wire:key="contact-mood-{{ $entry->id }}">
                            <td class="md-table__date">{{ $entry->date->format('d/m/Y') }}</td>
                            <td class="md-table__title">{{ $entry->emoji }} {{ $entry->text }}</td>
                            <td class="md-table__notes">{{ $entry->situation ?: '—' }}</td>
                            <td>{{ $entry->relationships->map->displayName()->join(', ') }}</td>
                            <td class="md-table__actions">
                                <x-ui.row-actions :label="'Acciones de '.$entry->text">
                                    <x-slot:primary :data-record-id="$entry->id" x-on:click="$dispatch('mood-editor', {mode: 'view', id: $el.dataset.recordId})">Ver detalle</x-slot:primary>
                                    <x-ui.menu-item icon="bi-pencil" :data-record-id="$entry->id" x-on:click="$dispatch('mood-editor', {mode: 'edit', id: $el.dataset.recordId})">Editar</x-ui.menu-item>
                                    <x-ui.menu-item icon="bi-trash" tone="danger" :data-record-id="$entry->id" x-on:click="$dispatch('mood-editor-delete', {id: $el.dataset.recordId})">Eliminar</x-ui.menu-item>
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.management-card>
    <livewire:mood.mood-editor :contact-id="$relationship->id" />
</x-module-shell>
