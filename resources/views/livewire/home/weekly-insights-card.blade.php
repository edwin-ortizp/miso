<div>
    @if ($insight)
        <section class="md-card-elevated mb-3" aria-labelledby="weekly-insights-title">
            <div class="d-flex align-items-start gap-2 mb-2">
                <h2 id="weekly-insights-title" class="md-title-medium mb-0 flex-grow-1">Lo que dicen tus datos <span class="md-label-small">· últimas 8 semanas</span></h2>
                <x-ui.icon-action icon="bi-x-lg" label="Cerrar" wire:click="dismiss" />
            </div>
            <x-insights.findings :findings="$insight->findings" />
            <div class="text-end mt-2">
                <a href="{{ route('statistics') }}#insights" wire:navigate class="md-btn-text">Ver en Estadísticas</a>
            </div>
        </section>
    @endif
</div>
