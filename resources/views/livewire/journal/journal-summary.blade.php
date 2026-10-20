<x-module-shell module="journal">

    <x-ui.management-card id="journal-summary" title="Resumen del diario" icon="bi-card-list"
                          :count="'('.$entries->total().' / '.$totalCount.')'" search="search" search-placeholder="Buscar en los resúmenes"
                          :active-filters="$period !== '1m' ? 1 : 0" :paginator="$entries" noun="días" flush
                          alpine="draftPeriod: '1m', draftFrom: '', draftTo: ''"
                          on-filters-open="draftPeriod = $wire.period; draftFrom = $wire.from; draftTo = $wire.to">
        <x-slot:filters>
            <x-ui.select name="period" label="Periodo" :options="$periods" :selected="$period" icon="bi-calendar-range" x-model="draftPeriod" />
            <div class="journal-summary__range" x-show="draftPeriod === 'custom'" x-cloak>
                <x-ui.field name="from" label="Desde" type="date" x-model="draftFrom" />
                <x-ui.field name="to" label="Hasta" type="date" x-model="draftTo" />
            </div>
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftPeriod, draftFrom, draftTo); filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>

        @if ($period !== 'all')
            <x-slot:strip>
                <x-ui.applied-filters :filters="[['key' => 'period', 'value' => null, 'label' => $periodLabel, 'icon' => 'bi-calendar-range']]" />
            </x-slot:strip>
        @endif

        @if ($entries->isNotEmpty())
            <div class="md-list journal-summary__list" role="list" aria-label="Resúmenes por día, del más reciente al más antiguo">
                @foreach ($entries as $entry)
                    <a href="{{ route('journal', ['date' => $entry->date->toDateString()]) }}" wire:navigate
                       class="md-list-item journal-summary__row" role="listitem" wire:key="journal-summary-{{ $entry->id }}"
                       aria-label="{{ $entry->date->translatedFormat('l j \d\e F \d\e Y') }}: {{ $entry->summary }}">
                        <div class="md-list-item-leading journal-summary__date" aria-hidden="true">
                            <strong>{{ $entry->date->format('d') }}</strong>
                            <span>{{ $entry->date->translatedFormat('M Y') }}</span>
                            <small>{{ $entry->date->translatedFormat('D') }}</small>
                        </div>
                        <div class="md-list-item-content">
                            <div class="md-list-item-headline journal-summary__text">{{ $entry->summary }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @elseif ($totalCount === 0)
            <x-ui.state variant="empty" icon="bi-card-list" title="Aún no hay resúmenes"
                        message="Escribe un resumen de una línea al guardar tu entrada del día y aparecerá aquí." />
        @else
            <x-ui.state variant="filtered-empty" title="Sin resúmenes en este periodo"
                        message="Prueba con otro periodo o cambia la búsqueda." />
        @endif
    </x-ui.management-card>
</x-module-shell>
