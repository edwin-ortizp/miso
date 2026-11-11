@php($chartVersion = md5(json_encode($weeklyTrend)))
<div class="md-card-elevated h-100">
    <x-home.card-head icon="bi-graph-up" tone="primary" title="Tendencia semanal" hint="Hidratación y hábitos de los últimos 7 días (%)" />
    <div wire:ignore
         wire:key="dashboard-chart-{{ $chartVersion }}"
         data-dashboard-chart='@json($weeklyTrend)'
         aria-label="Tendencia semanal de hidratación y hábitos"></div>
</div>
