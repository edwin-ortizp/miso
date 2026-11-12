{{-- CHG-070: cierre del día. Arriba el compañero se va a dormir (con racha y reto);
     debajo, en escritorio, las metas del día y lo pendiente lado a lado. --}}
<x-module-shell module="home" title="Cerrar el día" archetype="guided-flow" :back="['href' => route('home'), 'label' => 'Volver a Inicio']">
<div class="lt-page day-close">
    <x-game.daily-buddy variant="hero" :buddy="$buddy" :date="$date" :streak="$streak" :challenge="$challenge"
        night :closing="$closing" />

    {{-- En escritorio lo pendiente (textos largos) ocupa la columna ancha; sin pendientes, el resumen. --}}
    <div @class(['day-close__grid', 'day-close__grid--pending' => $tasks->isNotEmpty()])>
        <section class="md-card-elevated day-close__card day-close__summary" aria-labelledby="day-close-summary">
            <x-home.card-head icon="bi-moon-stars" tone="tertiary" title="Así te fue hoy" id="day-close-summary"
                :count="$goals ? $metCount.' de '.count($goals).' metas' : null" />
            @if ($goals === [])
                <p class="home-muted md-body-medium mb-0">Hoy no tenías metas medibles.</p>
            @else
                <ul class="day-close__goals">
                    @foreach ($goals as $goal)
                        <li wire:key="goal-{{ $goal['key'] }}">
                            <a href="{{ $goal['href'] }}" wire:navigate @class(['day-close__goal', 'is-met' => $goal['met']])>
                                <span class="day-close__avatar" data-module="{{ $goal['key'] }}" aria-hidden="true">
                                    @if ($goal['guardian']['image'] ?? null)
                                        <img src="{{ $goal['guardian']['image'] }}" width="48" height="48" loading="lazy" alt="">
                                        <i class="bi {{ $goal['icon'] }} day-close__badge"></i>
                                    @else
                                        <i class="bi {{ $goal['icon'] }}"></i>
                                    @endif
                                </span>
                                <div class="day-close__goal-body">
                                    <div class="day-close__goal-top">
                                        <span class="md-title-small">{{ $goal['label'] }}</span>
                                        <span class="day-close__state md-label-medium">
                                            <i class="bi {{ $goal['met'] ? 'bi-check-circle-fill' : 'bi-circle' }}" aria-hidden="true"></i>
                                            {{ $goal['met'] ? 'Cumplida' : 'Pendiente' }}
                                        </span>
                                    </div>
                                    @php($amount = number_format($goal['done'], 0, ',', '.').'/'.number_format($goal['total'], 0, ',', '.').($goal['unit'] ? ' '.$goal['unit'] : ''))
                                    <x-ui.progress class="day-close__bar" :value="min($goal['done'], $goal['total'])" :max="$goal['total']"
                                        :label="$amount" :value-text="$goal['guardian']['name'] ?? null" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="md-card-elevated day-close__card day-close__pending" aria-labelledby="day-close-pending">
            <x-home.card-head icon="bi-calendar-plus" tone="secondary" id="day-close-pending"
                :title="$tasks->isNotEmpty() ? 'Quedó pendiente ('.$tasks->count().')' : 'Quedó pendiente'" />

            @if ($message !== '')
                <div class="day-close__message md-body-medium" role="status">
                    <span>{{ $message }}</span>
                    @if ($undo !== [])
                        <button type="button" class="md-btn-text" wire:click="undoLast">Deshacer</button>
                    @endif
                </div>
            @endif

            @if ($tasks->isNotEmpty())
                <ul class="day-close__tasks">
                    @foreach ($tasks as $task)
                        <li class="day-close__task" wire:key="close-task-{{ $task->id }}">
                            <span class="day-close__task-title md-body-large">{{ $task->title }}</span>
                            <button type="button" class="md-btn-text" wire:click="moveOne('{{ $task->id }}')" wire:loading.attr="disabled"
                                    aria-label="Pasar {{ $task->title }} a mañana">Mañana</button>
                        </li>
                    @endforeach
                </ul>
                <button type="button" class="md-btn-tonal day-close__all" wire:click="moveAll" wire:loading.attr="disabled">
                    <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> Pasar todo a mañana
                </button>
            @else
                <p class="day-close__empty md-body-medium home-muted mb-0"><i class="bi bi-check2-all" aria-hidden="true"></i> Nada pendiente para mañana.</p>
            @endif
        </section>
    </div>
</div>
</x-module-shell>
