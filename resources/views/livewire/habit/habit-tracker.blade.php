<x-module-shell module="habits" active-route="habits" class="habit-tracker">
    <x-slot:controls>
        <div class="habit-date-navigation" role="group" aria-label="Navegación por fecha">
            <x-ui.icon-action icon="bi-chevron-left" label="Día anterior" wire:click="previousDay" />
            <time datetime="{{ $selectedDate }}"><i class="bi bi-calendar4" aria-hidden="true"></i><span>{{ ucfirst(\Carbon\Carbon::parse($selectedDate)->translatedFormat('l, d M')) }}</span></time>
            <x-ui.icon-action icon="bi-chevron-right" label="Día siguiente" wire:click="nextDay" />
        </div>
    </x-slot:controls>

    @if ($hasGuardian)
        <livewire:game.guardian-card module="habits" variant="top" :display-date="$selectedDate" :display-progress="['done' => $completedCount, 'total' => $totalCount]" />
    @else
    <section class="habit-coach-card habit-coach-card--{{ $coachCard['tone'] }} mb-3" aria-labelledby="habit-coach-title">
        <div class="habit-coach-card__glow" aria-hidden="true"></div>
        <div class="habit-coach-card__icon" aria-hidden="true"><i class="bi {{ $coachCard['icon'] }}"></i></div>
        <div class="habit-coach-card__content">
            <div class="habit-coach-card__eyebrow">{{ $coachCard['eyebrow'] }}</div>
            <h2 id="habit-coach-title" class="habit-coach-card__title">{{ $coachCard['title'] }}</h2>
            <p class="habit-coach-card__message">{{ $coachCard['message'] }}</p>
            @if ($coachCard['suggestedHabitId'])
                <button type="button"
                        class="habit-coach-card__cue"
                        x-data
                        @click="document.getElementById('habit-item-{{ $coachCard['suggestedHabitId'] }}')?.focus()">
                    Ir al hábito sugerido <i class="bi bi-arrow-down" aria-hidden="true"></i>
                </button>
            @endif
        </div>
        <div class="habit-coach-card__progress">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="md-label-medium">Progreso del día</span>
                <strong>{{ $completedCount }}/{{ $totalCount }}</strong>
            </div>
            <div class="md-progress-linear md-progress-linear--success" role="progressbar" aria-label="Progreso de hábitos del día" aria-valuenow="{{ $completedCount }}" aria-valuemin="0" aria-valuemax="{{ max($totalCount, 1) }}">
                <div class="md-progress-linear-bar" style="width: {{ $totalCount > 0 ? ($completedCount / $totalCount * 100) : 0 }}%"></div>
            </div>
        </div>
    </section>
    @endif

    {{-- Habits grouped by time of day --}}
    @php
        $timeLabels = [
            'morning' => ['label' => 'Mañana', 'icon' => 'bi-sunrise', 'container' => 'var(--md-custom-color-warning-container)', 'onContainer' => 'var(--md-custom-color-on-warning-container)'],
            'afternoon' => ['label' => 'Tarde', 'icon' => 'bi-sun', 'container' => 'var(--md-custom-color-info-container)', 'onContainer' => 'var(--md-custom-color-on-info-container)'],
            'night' => ['label' => 'Noche', 'icon' => 'bi-moon-stars', 'container' => 'var(--md-sys-color-tertiary-container)', 'onContainer' => 'var(--md-sys-color-on-tertiary-container)'],
            'anytime' => ['label' => 'Cualquier momento', 'icon' => 'bi-clock', 'container' => 'var(--md-sys-color-secondary-container)', 'onContainer' => 'var(--md-sys-color-on-secondary-container)'],
        ];
    @endphp

    @foreach (['morning', 'afternoon', 'night', 'anytime'] as $timeOfDay)
        @if (isset($groupedHabits[$timeOfDay]) && $groupedHabits[$timeOfDay]->count() > 0)
            @php
                $sectionId = "habit-time-{$timeOfDay}-{$selectedDate}";
                $headingId = "{$sectionId}-heading";
            @endphp
            <div wire:key="habit-section-{{ $timeOfDay }}-{{ $selectedDate }}"
                 class="md-card-elevated habit-period habit-period--{{ $timeOfDay }} mb-3"
                 x-data="{ open: @js(in_array($timeOfDay, $initiallyOpenTimeOfDay, true)) }">
                <div class="habit-period__heading">
                    <button type="button"
                            id="{{ $headingId }}"
                            class="habit-period__toggle"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-controls="{{ $sectionId }}">
                        <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: var(--md-sys-shape-corner-full); background: {{ $timeLabels[$timeOfDay]['container'] }}; color: {{ $timeLabels[$timeOfDay]['onContainer'] }};">
                            <i class="bi {{ $timeLabels[$timeOfDay]['icon'] }}"></i>
                        </div>
                        <span class="md-title-small" style="color: var(--md-sys-color-on-surface);">{{ $timeLabels[$timeOfDay]['label'] }}</span>
                        <span class="md-chip-tonal" style="background: {{ $timeLabels[$timeOfDay]['container'] }}; color: {{ $timeLabels[$timeOfDay]['onContainer'] }};">
                            {{ $groupedHabits[$timeOfDay]->filter(fn($h) => !empty($completions[$h->id]))->count() }}/{{ $groupedHabits[$timeOfDay]->count() }}
                        </span>
                        <i class="bi ms-auto" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
                    </button>
                </div>
                <div id="{{ $sectionId }}" role="region" aria-labelledby="{{ $headingId }}" class="habit-period__body" x-cloak x-show="open">
                    @if ($isToday && $groupedHabits[$timeOfDay]->contains(fn ($h) => empty($completions[$h->id])))
                        <x-ui.action variant="tonal" size="sm" icon="bi-play-fill" class="habit-routine-start" wire:click="startRoutine('{{ $timeOfDay }}')" wire:loading.attr="disabled">Empezar rutina</x-ui.action>
                    @endif

                    @foreach ($groupedHabits[$timeOfDay] as $habit)
                        @php $isCompleted = !empty($completions[$habit->id]); @endphp
                        <button type="button"
                             id="habit-item-{{ $habit->id }}"
                             wire:click="toggleHabit({{ $habit->id }})"
                             wire:loading.attr="disabled"
                             wire:target="toggleHabit({{ $habit->id }})"
                             aria-pressed="{{ $isCompleted ? 'true' : 'false' }}"
                             x-optimistic-toggle
                             class="md-list-item habit-list-action w-100 text-start {{ $isCompleted ? 'md-list-item--completed' : '' }}"
                             style="{{ $isCompleted ? 'background: color-mix(in srgb, var(--md-custom-color-success) 8%, transparent);' : '' }}"
                             wire:key="habit-{{ $selectedDate }}-{{ $habit->id }}">
                            <div class="md-list-item-leading">
                                <div class="md-list-checkbox {{ $isCompleted ? 'checked' : '' }}" style="{{ $isCompleted ? 'background: var(--md-custom-color-success); border-color: var(--md-custom-color-success);' : '' }}">
                                    @if ($isCompleted)
                                        <i class="bi bi-check-lg" style="color: var(--md-custom-color-on-success);"></i>
                                    @endif
                                </div>
                            </div>
                            <div class="habit-list-action__content">
                                <span style="font-size: 1.25rem;">{{ $habit->icon }}</span>
                                <div class="md-list-item-content">
                                    <div class="md-list-item-headline">{{ $habit->name }}</div>
                                    <div class="md-list-item-supporting">{{ $habit->goal_duration }} · {{ $habit->base_time }}</div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    @include('livewire.habit.partials.routine-overlay')

    @include('livewire.habit.partials.completion-feedback')

    <x-slot:rail>
        <x-context-widget title="Constancia" icon="bi-fire" tone="success">
            @if ($hasGuardian)
                <livewire:game.guardian-card module="habits" variant="rail" :display-date="$selectedDate" :display-progress="['done' => $completedCount, 'total' => $totalCount]" />
            @endif
            <dl class="md-context-list">
                <div><dt>Mejor racha activa</dt><dd>{{ $monthData['best_streak'] }} días</dd></div>
                <div><dt>Progreso del día</dt><dd>{{ $completedCount }}/{{ $totalCount }}</dd></div>
            </dl>
            <a href="{{ route('habits.weekly', ['date' => $selectedDate]) }}" class="md-btn-text w-100 mt-2">Abrir resumen semanal</a>
        </x-context-widget>
        <x-context-widget title="{{ $monthData['label'] }}" icon="bi-calendar3">
            @include('livewire.habit.partials.month-calendar')
        </x-context-widget>
    </x-slot:rail>

    @include('livewire.habit.partials.action-prompt-dialog')
</x-module-shell>
