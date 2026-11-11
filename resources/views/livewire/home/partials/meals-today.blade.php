<div class="md-card-elevated h-100">
    <x-home.card-head icon="bi-egg-fried" tone="tertiary" title="Comidas de hoy"
        :href="route('meals.weekly', ['date' => $selectedDate])" action="Ver semana" />

    @forelse ($meals as $meal)
        <div class="d-flex align-items-center gap-2 py-1" wire:key="home-meal-{{ $meal->id }}">
            <span class="md-chip-tonal" style="min-width: 92px; justify-content: center;">{{ $mealTypes[$meal->meal_type] ?? ucfirst($meal->meal_type) }}</span>
            <span class="md-label-small">{{ $meal->status === 'consumed' ? ($meal->consumption_mode === 'outside' ? 'Por fuera' : 'Consumida') : 'Planeada' }}</span>
            <span class="md-body-small flex-grow-1">{{ $meal->items->map(fn ($item) => $item->recipe?->name ?? $item->name)->filter()->implode(' + ') }}</span>
            @php $displayCalories = $meal->status === 'consumed' ? ($meal->consumption['calories'] ?? null) : $meal->effective_calories; @endphp
            @if ($displayCalories > 0)
                <span class="home-muted md-label-small">{{ number_format($displayCalories) }} kcal</span>
            @endif
        </div>
    @empty
        <p class="home-muted md-body-small mb-0">
            No hay comidas planificadas para este día.
            <a href="{{ route('meals.weekly', ['date' => $selectedDate]) }}">Planificar menú</a>
        </p>
    @endforelse
</div>
