<div class="md-card-elevated">
    <x-home.card-head icon="bi-egg-fried" tone="tertiary" title="Comidas de hoy"
        :href="route('meals.weekly', ['date' => $selectedDate])" action="Ver semana" />

    @forelse ($meals as $meal)
        <div class="home-meal" wire:key="home-meal-{{ $meal->id }}">
            <span class="home-meal__type md-label-medium">{{ $mealTypes[$meal->meal_type] ?? ucfirst($meal->meal_type) }}</span>
            <span class="home-meal__name md-body-medium">{{ $meal->items->map(fn ($item) => $item->recipe?->name ?? $item->name)->filter()->implode(' + ') }}</span>
            <span class="home-meal__status md-label-small {{ $meal->status === 'consumed' ? 'home-tone-text--success' : 'home-muted' }}">
                @if ($meal->status === 'consumed')<i class="bi bi-check2-circle" aria-hidden="true"></i>@endif
                {{ $meal->status === 'consumed' ? ($meal->consumption_mode === 'outside' ? 'Por fuera' : 'Consumida') : 'Planeada' }}
            </span>
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
