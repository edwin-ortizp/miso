{{-- Fila de plan. Con $relationship, las visitas cuentan solo las compartidas con esa persona. --}}
@php
    $days = $plan->daysUntil();
    $people = $relationship ? collect() : $plan->relationships;
@endphp
<article class="plan-row" wire:key="plan-row-{{ $plan->id }}">
    <span class="plan-row__media" aria-hidden="true">
        <i class="bi {{ $plan->icon() }}"></i>
        @if ($image = $plan->mainImageUrl())
            {{-- Se muestra desde su URL; si no carga, queda el ícono del tipo. --}}
            <img src="{{ $image }}" alt="" loading="lazy" referrerpolicy="no-referrer" x-data x-on:error="$el.remove()">
        @endif
    </span>

    <div class="plan-row__body">
        <a class="plan-row__title" href="{{ route('plans.show', $plan) }}" wire:navigate>{{ $plan->title }}</a>
        <span class="plan-row__meta">
            @if ($plan->city)<i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $plan->city }} · @endif{{ $plan->typeLabel() }}@if ($plan->category) · {{ $plan->category }}@endif
        </span>
        @if ($people->isNotEmpty() || (! $relationship && $plan->circles->isNotEmpty()))
            <span class="plan-row__people">
                <i class="bi bi-people" aria-hidden="true"></i>
                @foreach ($people->take(2) as $person)
                    <x-ui.chip :href="route('relationships.show', $person)" wire:navigate class="plan-row__person"><span class="plan-row__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->full_name, 0, 1)) }}</span>{{ $person->nickname ?: \Illuminate\Support\Str::before($person->full_name.' ', ' ') }}</x-ui.chip>
                @endforeach
                @if ($people->count() > 2)
                    <span class="plan-row__tag">+{{ $people->count() - 2 }}</span>
                @endif
                @foreach ($plan->circles as $circle)
                    <span class="plan-row__tag">{{ $circle->name }}</span>
                @endforeach
            </span>
        @endif
    </div>

    <div class="plan-row__aside">
        @if ($plan->status === 'scheduled' && ($plan->ends_on ?? $plan->scheduled_on)?->lt(today()))<x-ui.chip tone="warning" variant="tonal" icon="bi-exclamation-circle">Vencido</x-ui.chip>@endif
        <div class="plan-row__line">
            @if ($plan->scheduled_on)
                <span class="plan-row__date"><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ \Illuminate\Support\Str::ucfirst($plan->scheduled_on->translatedFormat('D, j M Y')) }}</span>
            @endif
            <span class="plan-status plan-status--{{ $plan->status }}">{{ $plan->statusLabel() }}</span>
        </div>
        @if ($days !== null && $plan->status === 'scheduled')
            <small class="plan-row__left">{{ $days === 0 ? 'Es hoy' : 'Faltan '.$days.' '.($days === 1 ? 'día' : 'días') }}</small>
        @endif
        <small class="plan-row__stats"><i class="bi bi-bar-chart" aria-hidden="true"></i> {{ \App\Models\Plan::visitsLabel((int) $plan->visits_count) }}</small>
        <small class="plan-row__stats"><i class="bi bi-calendar3" aria-hidden="true"></i> Última vez: {{ $plan->lastVisitLabel() }}</small>
    </div>

    <x-ui.menu :label="'Más opciones de '.$plan->title">
        <x-ui.menu-item icon="bi-check2-circle" wire:click="openVisitForm('{{ $plan->id }}')">Registrar que lo hicimos</x-ui.menu-item>
        <x-ui.menu-item icon="bi-eye" :href="route('plans.show', $plan)">Ver detalle</x-ui.menu-item>
        <x-ui.menu-item icon="bi-pencil" wire:click="openPlanForm('{{ $plan->id }}')">Editar</x-ui.menu-item>
        @if (in_array($plan->status, ['done', 'archived'], true))
            <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="setPlanStatus('{{ $plan->id }}', 'pending')">Volver a pendiente</x-ui.menu-item>
        @else
            <x-ui.menu-item icon="bi-archive" wire:click="setPlanStatus('{{ $plan->id }}', 'archived')">Archivar</x-ui.menu-item>
        @endif
        <x-ui.menu-divider />
        <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deletePlan('{{ $plan->id }}')" wire:confirm="¿Eliminar este plan y todo su historial?">Eliminar</x-ui.menu-item>
    </x-ui.menu>
</article>
