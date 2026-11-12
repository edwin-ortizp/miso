<x-module-shell module="water">
    <x-slot:controls>
        <x-date-navigator previous="previousMonth" next="nextMonth" previous-label="Mes anterior" next-label="Mes siguiente" today-label="Este mes"
                          nav-label="Navegación por mes" :label="$monthData['label']" />
    </x-slot:controls>
    <div class="md-module-workspace">
        <section class="md-module-primary md-card-elevated water-calendar-card">
            @include('livewire.water.partials.month-calendar')
        </section>
        <aside class="md-context-rail">
            <x-context-widget title="Cumplimiento" icon="bi-check2-circle" tone="success">
                <div class="water-kpi"><strong>{{ $monthData['completed_days'] }}</strong><span>días con meta alcanzada</span></div>
            </x-context-widget>
            <x-context-widget title="Ritmo del mes" icon="bi-activity">
                <dl class="md-context-list"><div><dt>Días registrados</dt><dd>{{ $monthData['tracked_days'] }}</dd></div><div><dt>Promedio diario</dt><dd>{{ number_format($monthData['average']) }} ml</dd></div></dl>
            </x-context-widget>
        </aside>
    </div>
</x-module-shell>
