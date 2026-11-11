<div class="md-card-elevated h-100">
    <x-home.card-head icon="bi-check2-square" tone="success" title="Hábitos" :count="$completedHabits.'/'.$totalHabits"
        :href="route('habits', ['date' => $selectedDate])" action="Ver todos" />
    <div class="md-progress-linear md-progress-linear--success mb-2">
        <div class="md-progress-linear-bar" style="width: {{ $totalHabits > 0 ? ($completedHabits / $totalHabits * 100) : 0 }}%"></div>
    </div>

    @forelse ($pendingHabits as $habit)
        <button type="button"
                wire:click="toggleHabit({{ $habit->id }})"
                wire:loading.attr="disabled"
                wire:target="toggleHabit({{ $habit->id }})"
                class="md-list-item habit-list-action w-100 text-start"
                wire:key="home-habit-{{ $selectedDate }}-{{ $habit->id }}">
            <div class="md-list-item-leading">
                <div class="md-list-checkbox"></div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <span style="font-size: 1.25rem;">{{ $habit->icon }}</span>
                <div class="md-list-item-content">
                    <div class="md-list-item-headline">{{ $habit->name }}</div>
                    <div class="md-list-item-supporting">{{ $habit->goal_duration }} · {{ $habit->base_time }}</div>
                </div>
            </div>
        </button>
    @empty
        <p class="home-muted md-body-small mb-0">
            @if ($totalHabits > 0 && $completedHabits >= $totalHabits)
                <i class="home-tone-text--success bi bi-stars"></i> ¡Día completo! Todos tus hábitos están hechos.
            @elseif ($totalHabits === 0)
                Aún no tienes hábitos definidos.
            @else
                Nada pendiente en este momento del día. 🎉
            @endif
        </p>
    @endforelse
</div>
