<x-module-shell module="plans" active-route="plans.history" :navigation-query="['q'=>$q,'people'=>$people]">
    <x-ui.management-card id="plan-history" title="Historial" icon="bi-clock-history" search="q" search-placeholder="Buscar plan, persona o comentario" :paginator="$visits" noun="visitas" :count="$visits->total()" sort-model="sort" :sort-options="['recent'=>'Más recientes','oldest'=>'Más antiguos']" :sort-value="$sort" :active-filters="(int) filled($from) + (int) filled($to) + count($people)">
        <x-slot:filters>
            <x-ui.field name="from" type="date" label="Desde" wire:model.live="from" />
            <x-ui.field name="to" type="date" label="Hasta" wire:model.live="to" />
            <x-ui.multi-select name="people" label="Acompañantes" :options="$peopleOptions" />
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $from !== '' ? ['key' => 'from', 'value' => null, 'label' => 'Desde: '.\Carbon\Carbon::parse($from)->translatedFormat('j M Y'), 'icon' => 'bi-calendar-event'] : null,
                $to !== '' ? ['key' => 'to', 'value' => null, 'label' => 'Hasta: '.\Carbon\Carbon::parse($to)->translatedFormat('j M Y'), 'icon' => 'bi-calendar-event'] : null,
                ...array_map(fn ($person) => ['key' => 'people', 'value' => $person, 'label' => 'Acompañante: '.($peopleOptions[$person] ?? '—'), 'icon' => 'bi-person'], $people),
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        <x-slot:filterActions><x-ui.action variant="outlined" wire:click="clearFilters">Limpiar</x-ui.action><x-ui.action x-on:click="filtersOpen = false">Filtrar</x-ui.action></x-slot:filterActions>
        @if ($visits->isEmpty())
            <x-ui.state variant="empty" title="No hay visitas" message="Registra una visita desde un plan o prueba otros filtros." icon="bi-clock-history" />
        @else
        <x-ui.list label="Visitas realizadas">
        @foreach ($visits as $visit)
            <x-ui.list-item :headline="$visit->plan->title" :supporting="$visit->visited_on->translatedFormat('j M Y').' · '.$visit->relationships->pluck('full_name')->join(', ')" wire:key="visit-{{ $visit->id }}">
                @if ($visit->comment)<p class="plans-notice">{{ $visit->comment }}</p>@endif
                <x-slot:trailing><x-ui.row-actions :label="'Acciones de visita a '.$visit->plan->title">
                    <x-slot:primary wire:click="showVisit('{{ $visit->id }}')">Ver detalle</x-slot:primary>
                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteVisit('{{ $visit->id }}')" wire:confirm="¿Eliminar esta visita del historial? El plan se conservará.">Eliminar del historial</x-ui.menu-item>
                </x-ui.row-actions></x-slot:trailing>
            </x-ui.list-item>
        @endforeach
        </x-ui.list>
        @endif
    </x-ui.management-card>
    <div x-data="{ open: $wire.entangle('visitOpen') }">
        <x-ui.dialog state="open" title="Detalle de visita" icon="bi-clock-history">
            @if ($selectedVisit)
                <h3>{{ $selectedVisit->plan->title }}</h3>
                <p>{{ $selectedVisit->visited_on->translatedFormat('j M Y') }}</p>
                <div class="md-chip-rail">@foreach ($selectedVisit->relationships as $person)<x-ui.chip :href="route('relationships.show',$person)" wire:navigate>{{ $person->full_name }}</x-ui.chip>@endforeach</div>
                <p>{{ $selectedVisit->comment ?: 'Sin comentario' }}</p>
                <x-ui.action :href="route('plans.show',$selectedVisit->plan)" wire:navigate>Ver plan</x-ui.action>
            @endif
            <x-slot:actions><x-ui.action wire:click="closeVisit">Cerrar</x-ui.action></x-slot:actions>
        </x-ui.dialog>
    </div>
</x-module-shell>
