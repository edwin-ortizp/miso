{{-- Tabla de historial de una persona. Requiere un componente con ManagesRelationshipEvents. --}}
@php
    $toneClass = fn (string $tone) => 'md-chip-tonal'.($tone === 'neutral' ? '' : ' md-chip-tonal--'.($tone === 'danger' ? 'error' : $tone));
@endphp

<table class="md-table md-table--stack">
    <thead>
        <tr>
            <th scope="col">Fecha</th>
            <th scope="col">Título</th>
            <th scope="col">Tipo</th>
            <th scope="col">Notas</th>
            <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr wire:key="history-{{ $row['kind'] }}-{{ $row['id'] }}" @class(['is-archived' => $row['archived']])>
                <td class="md-table__date">{{ $row['dateLabel'] }}</td>
                <td class="md-table__title">
                    @if ($row['url'])
                        <a href="{{ $row['url'] }}" wire:navigate>{{ $row['title'] }}</a>
                    @else
                        {{ $row['title'] }}
                    @endif
                    <span class="md-table__meta">
                        {{ $row['category'] }}@if ($row['sensitive']) · Sensible @endif @if ($row['archived']) · Archivado @endif
                    </span>
                </td>
                <td class="md-table__nowrap"><span class="{{ $toneClass($row['typeTone']) }}"><i class="bi {{ $row['icon'] }}" aria-hidden="true"></i> {{ $row['type'] }}</span></td>
                <td class="md-table__notes" @if ($row['notes']) title="{{ $row['notes'] }}" @endif>{{ $row['notes'] ?: '—' }}</td>
                <td class="md-table__actions">
                    @if ($row['kind'] === 'event')
                        <x-ui.row-actions :label="'Más acciones de '.$row['title']">
                            <x-slot:primary wire:click="openEventForm('{{ $row['id'] }}')">Editar</x-slot:primary>
                            <x-ui.menu-item icon="bi-archive" wire:click="toggleEventArchive('{{ $row['id'] }}')">{{ $row['archived'] ? 'Desarchivar' : 'Archivar' }}</x-ui.menu-item>
                            <x-ui.menu-divider />
                            <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteEvent('{{ $row['id'] }}')"
                                            wire:confirm="¿Eliminar este acontecimiento?">Eliminar</x-ui.menu-item>
                        </x-ui.row-actions>
                    @elseif ($row['kind'] === 'mood')
                        <x-ui.action variant="outlined" size="sm" :data-record-id="$row['id']" x-on:click="$dispatch('mood-editor', {mode: 'view', id: $el.dataset.recordId})">Ver detalle</x-ui.action>
                    @else
                        <x-ui.action variant="outlined" size="sm" :href="$row['url']" wire:navigate>Ver plan</x-ui.action>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
