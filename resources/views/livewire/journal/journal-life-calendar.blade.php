<x-module-shell module="journal" class="life-calendar">
    @if ($isConfigured)
        <x-slot:controls><x-ui.action variant="tonal" icon="bi-crosshair" :href="'#life-year-'.$currentYear">Ir a hoy</x-ui.action></x-slot:controls>
    @endif

    @if (! $isConfigured)
        <x-ui.state variant="empty" icon="bi-hourglass-split" title="Tu calendario todavía no tiene límites"
                    message="Agrega tu fecha de nacimiento y una expectativa de vida personal para dibujar las semanas de tu vida.">
            <x-slot:actions>
                <x-ui.action variant="filled" icon="bi-gear" :href="route('settings')" wire:navigate>Configurar perfil</x-ui.action>
            </x-slot:actions>
        </x-ui.state>
    @else
        <section class="life-calendar-summary mb-3" aria-label="Resumen de semanas">
            <div><span>Por vivir</span><strong>{{ number_format($remainingWeeks) }}</strong><small>semanas</small></div>
            <div><span>Vividas</span><strong>{{ number_format($livedWeeks) }}</strong><small>semanas</small></div>
            <div><span>Total</span><strong>{{ number_format($totalWeeks) }}</strong><small>semanas</small></div>
        </section>

        <section class="md-card-outlined life-calendar-card" aria-label="Calendario completo de vida">
            <div class="life-calendar-card__heading">
                <div>
                    <span class="life-calendar-eyebrow"><i class="bi bi-map" aria-hidden="true"></i> MAPA COMPLETO</span>
                    <h2 class="md-title-medium mb-0">Tus semanas, de principio a fin</h2>
                </div>
                <div class="life-calendar-legend" aria-label="Leyenda">
                    <span><i class="life-calendar-cell level-0"></i> Sin entradas</span>
                    <span><i class="life-calendar-cell level-1"></i> 1–3</span>
                    <span><i class="life-calendar-cell level-2"></i> 4–6</span>
                    <span><i class="life-calendar-cell level-3"></i> 7</span>
                    <span><i class="life-calendar-cell is-future"></i> Por vivir</span>
                </div>
            </div>

            <div class="life-calendar-map" role="grid" aria-label="Semanas desde el nacimiento hasta la expectativa de vida">
                <div class="life-calendar-map__inner">
                    @foreach ($weekRows as $year => $weeks)
                        <div id="life-year-{{ $year }}" class="life-calendar-row {{ $year === $currentYear ? 'is-current-year' : '' }}" role="row">
                            <span class="life-calendar-year" role="rowheader">{{ $year }}</span>
                            <div class="life-calendar-cells">
                                @foreach ($weeks as $cell)
                                    @if ($cell['is_future'])
                                        <span class="life-calendar-cell level-{{ $cell['level'] }} is-future" role="gridcell" aria-label="{{ $cell['label'] }}" title="{{ $cell['label'] }}"></span>
                                    @else
                                        <a href="{{ $cell['href'] }}" class="life-calendar-cell level-{{ $cell['level'] }} {{ $cell['is_current'] ? 'is-current' : '' }}" role="gridcell" aria-label="{{ $cell['label'] }}" title="{{ $cell['label'] }}"></a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="life-calendar-caption mb-0"><i class="bi bi-cursor" aria-hidden="true"></i> Selecciona una semana disponible para abrir su vista detallada.</p>
        </section>
    @endif
</x-module-shell>
