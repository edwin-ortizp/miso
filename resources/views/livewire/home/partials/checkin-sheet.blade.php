{{-- CHG-069: check-in de 20 s. Reutiliza los métodos de Inicio (saveMood, saveEnergy, quickAddWater, toggleHabit). --}}
<div x-data="{ checkinOpen: $wire.entangle('checkin').live }">
    <x-ui.sheet state="checkinOpen" title="Check-in" persistent class="home-checkin">
        <section class="home-checkin__block" aria-label="¿Cómo te sientes?">
            <h3 class="md-title-small">¿Cómo te sientes?</h3>
            @if ($moodStates->isEmpty())
                <x-mood-empty-catalog />
            @else
                <div class="home-quick-row">
                    @foreach ($moodStates as $state)
                        <button type="button" wire:key="checkin-mood-{{ $state->id }}"
                                class="home-mood-btn {{ $lastMood?->mood_state_id === $state->id ? 'is-selected' : '' }}"
                                wire:click="saveMood('{{ $state->id }}')" wire:loading.attr="disabled"
                                aria-label="{{ $state->text }}" title="{{ $state->text }}">{{ $state->emoji }}</button>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="home-checkin__block" aria-label="Energía">
            <h3 class="md-title-small">Energía</h3>
            <div class="home-quick-row">
                @foreach (range(1, 5) as $level)
                    <button type="button" wire:key="checkin-energy-{{ $level }}"
                            class="home-mood-btn {{ (int) ($lastEnergy->level ?? 0) === $level ? 'is-selected' : '' }}"
                            wire:click="saveEnergy({{ $level }})" wire:loading.attr="disabled"
                            aria-label="Energía {{ $level }} de 5">{{ $level }}</button>
                @endforeach
            </div>
        </section>

        <section class="home-checkin__block" aria-label="Agua">
            <h3 class="md-title-small">Agua <span class="md-label-small">{{ number_format($waterTotal) }} / {{ number_format($waterGoal) }} ml</span></h3>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($drinkTypes->take(2) as $type)
                    <x-ui.action type="button" variant="tonal" wire:key="checkin-drink-{{ $type->id }}"
                                 wire:click="quickAddWater('{{ $type->id }}', 250)" wire:loading.attr="disabled">{{ $type->icon }} {{ $type->name }} +250 ml</x-ui.action>
                @endforeach
            </div>
        </section>

        <section class="home-checkin__block" aria-label="Hábitos de hoy">
            <h3 class="md-title-small">Hábitos de hoy</h3>
            @forelse ($pendingHabits as $habit)
                <button type="button" wire:key="checkin-habit-{{ $habit->id }}"
                        wire:click="toggleHabit({{ $habit->id }})" wire:loading.attr="disabled" wire:target="toggleHabit({{ $habit->id }})"
                        class="md-list-item habit-list-action w-100 text-start">
                    <div class="md-list-item-leading"><div class="md-list-checkbox"></div></div>
                    <span class="me-2" aria-hidden="true">{{ $habit->icon }}</span>
                    <span class="md-list-item-headline">{{ $habit->name }}</span>
                </button>
            @empty
                <p class="md-body-small mb-0">Hábitos de hoy completos.</p>
            @endforelse
        </section>

        <x-slot:actions>
            <x-ui.action type="button" wire:click="closeCheckin">Listo</x-ui.action>
        </x-slot:actions>
    </x-ui.sheet>
</div>
