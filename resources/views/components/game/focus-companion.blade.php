{{-- Compañero de foco (CHG-059): el guardián de Pomodoro, quieto junto al reloj, dice su grito y una frase
     que cambia solo al cambiar de etapa del ciclo. Va dentro de x-data="pomodoroTimer(...)" y lee su estado.
     `wall`: "Foco de hoy" (al lado del globo); `dial`: bajo el dial de la página Pomodoro. --}}
@props(['companion' => null, 'variant' => 'wall'])
@if ($companion)
    <div wire:ignore
         x-data="focusCompanion({ lines: @js(config('game.focus_companion')), cry: @js($companion['cry'] ?? ''), storageKey: 'life-tracker:focus-companion-hidden:{{ auth()->id() }}' })"
         x-effect="sync({ mode, isRunning, isCompleting, pendingCompletion, startedAt, timeLeft, totalTime })"
         {{ $attributes->class(['focus-companion', 'focus-companion--'.$variant, 'game-stage--'.($companion['stage'] ?? 'cielo')]) }}
         :class="{ 'is-hidden': hidden, 'is-cheer': cheer, 'is-running': isRunning && mode === 'work' }"
         :data-stage="stage">
        <span class="focus-companion__art" x-show="!hidden">
            @if ($companion['image'])
                <img src="{{ $companion['image'] }}" width="112" height="112" alt="{{ $companion['name'] }}">
            @else
                <span class="game-placeholder" role="img" aria-label="{{ $companion['name'] }}"><i class="bi bi-stars" aria-hidden="true"></i></span>
            @endif
        </span>
        <div class="focus-companion__talk">
            <p class="focus-companion__bubble" aria-live="polite" x-show="!hidden"><strong x-text="cry"></strong> <span x-text="line"></span></p>
            <div class="focus-companion__meta">
                <span x-show="!hidden">{{ $companion['name'] }} · Nv {{ $companion['level'] }}</span>
                <button type="button" class="focus-companion__toggle" @click="toggleHidden()"
                        :aria-label="hidden ? 'Mostrar compañero' : 'Ocultar compañero'" :title="hidden ? 'Mostrar compañero' : 'Ocultar compañero'">
                    <i class="bi" :class="hidden ? 'bi-eye' : 'bi-eye-slash'" aria-hidden="true"></i>
                    <span x-show="hidden" x-cloak>Mostrar compañero</span>
                </button>
            </div>
        </div>
    </div>
@endif
