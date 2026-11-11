@if ($routine)
    @php
        $period = $timeLabels[$routine['period']] ?? $timeLabels['anytime'];
        $queue = $routine['queue'];
        $total = count($queue);
        $status = $routine['status'];
        $doneCount = count(array_filter($status, fn ($s) => $s === 'done'));
        $skippedCount = count(array_filter($status, fn ($s) => $s === 'skipped'));
        $seconds = $routineHabit ? \App\Livewire\Habit\HabitTracker::routineSeconds($routineHabit->goal_duration) : null;
        $companion = $routine['companion'];
        $stateLabels = ['done' => 'hecho', 'skipped' => 'saltado', 'pending' => 'pendiente'];
    @endphp
    <div x-data="{ init() { $store.routineMusic.start(@js(asset('audio/routine'))); }, destroy() { $store.routineMusic.stop(); } }" wire:key="habit-routine-shell">
        <template x-teleport="body">
            {{-- Raíz estable: si su wire:key cambia, Livewire reemplaza el nodo teletransportado y la pantalla queda congelada. --}}
            <div class="habit-routine habit-routine--{{ $routine['period'] }}"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="habit-routine-title"
                 wire:key="habit-routine-root">
              <div class="habit-routine__scene" aria-hidden="true">
                  <span class="habit-routine__sky-body"></span>
                  <span class="habit-routine__clouds"></span>
                  <span class="habit-routine__stars"></span>
                  <svg class="habit-routine__hills" viewBox="0 0 400 120" preserveAspectRatio="none">
                      <path class="habit-routine__hill-back" d="M0 70 Q60 30 120 60 T240 50 T400 55 V120 H0 Z" />
                      <path class="habit-routine__hill-front" d="M0 95 Q80 60 170 88 T330 80 T400 90 V120 H0 Z" />
                  </svg>
              </div>
              <div class="habit-routine__step"
                 wire:key="habit-routine-step-{{ $routine['index'] }}-{{ implode('-', $queue) }}-{{ $routineHabit ? 'h' : 'end' }}"
                 x-data="{
                     total: @js($seconds),
                     left: @js($seconds),
                     paused: false,
                     cheer: false,
                     timer: null,
                     habit: @js($routineHabit?->name),
                     init() {
                         this.$nextTick(() => this.$refs.done?.focus());
                         if (this.total) this.timer = setInterval(() => { if (!this.paused && this.left > 0) this.left--; }, 1000);
                         $store.routineMusic.step();
                         this.$watch('paused', (p) => p ? $store.routineMusic.pause() : $store.routineMusic.resume());
                         this.$watch('left', (v) => { if (this.total && v === 0) $store.routineMusic.timeUp(); });
                     },
                     destroy() { clearInterval(this.timer); },
                     get clock() {
                         const m = Math.floor(this.left / 60), s = this.left % 60;
                         return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                     },
                     get ring() { return this.total ? 565.49 * (1 - this.left / this.total) : 0; },
                     get phrase() {
                         if (this.cheer) return '¡Bien hecho!';
                         if (!this.total) return '¡Vamos con ' + this.habit + '!';
                         if (this.left === 0) return '¡Tiempo! Márcalo cuando termines';
                         if (this.left <= 15) return '¡Casi!';
                         if (this.left <= this.total / 2) return '¡Ya vas por la mitad!';
                         return '¡Vamos con ' + this.habit + '!';
                     },
                     finish() { this.cheer = true; clearInterval(this.timer); setTimeout(() => $wire.routineDone(), 450); },
                 }"
                 @keydown.escape.window="$wire.closeRoutine()">
                <div class="habit-routine__inner">
                    <header class="habit-routine__header">
                        @if ($routineHabit)
                            <x-ui.icon-action icon="bi-chevron-left" label="Hábito anterior" wire:click="routinePrevious" :disabled="$routine['index'] === 0" />
                        @endif
                        <span class="habit-routine__period habit-routine__period--{{ $routine['period'] }}">
                            <i class="bi {{ $period['icon'] }}" aria-hidden="true"></i>
                            @if ($routineHabit)
                                {{ $period['label'] }} · {{ $routine['index'] + 1 }} de {{ $total }}
                            @else
                                {{ $period['label'] }}
                            @endif
                        </span>
                        <span class="habit-routine__music">
                            <x-ui.icon-action icon="bi-volume-up" label="Silenciar música" x-show="!$store.routineMusic.muted" x-on:click="$store.routineMusic.toggle()" />
                            <x-ui.icon-action icon="bi-volume-mute" label="Activar música" x-show="$store.routineMusic.muted" x-cloak x-on:click="$store.routineMusic.toggle()" />
                            <x-ui.icon-action icon="bi-x-lg" label="Cerrar rutina" wire:click="closeRoutine" />
                        </span>
                    </header>

                    @if ($routineHabit)
                        <main class="habit-routine__body">
                            @if ($companion)
                                <p class="habit-routine__bubble md-body-large" aria-live="polite" x-text="phrase">¡Vamos con {{ $routineHabit->name }}!</p>
                            @endif

                            <div class="habit-routine__buddy" :class="{ 'habit-routine__buddy--cheer': cheer }">
                                @if ($seconds)
                                    <svg class="habit-routine__ring" viewBox="0 0 200 200" aria-hidden="true">
                                        <circle class="habit-routine__ring-track" cx="100" cy="100" r="90" />
                                        <circle class="habit-routine__ring-fill" cx="100" cy="100" r="90" :stroke-dashoffset="ring" />
                                    </svg>
                                @endif
                                <span class="habit-routine__buddy-art">
                                    @if ($companion && $companion['image'])
                                        <img src="{{ $companion['image'] }}" width="140" height="140" alt="{{ $companion['name'] }}">
                                    @else
                                        <span class="habit-routine__buddy-icon" aria-hidden="true">{{ $routineHabit->icon }}</span>
                                    @endif
                                </span>
                            </div>

                            @if ($seconds)
                                <div class="habit-routine__clock md-display-medium" :class="{ 'is-over': left === 0 }" x-text="clock">{{ sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60) }}</div>
                            @endif

                            <h2 id="habit-routine-title" class="habit-routine__name md-headline-small">
                                <span class="habit-routine__habit-chip" aria-hidden="true">{{ $routineHabit->icon }}</span>
                                {{ $routineHabit->name }}
                            </h2>
                            @if (! $companion && $seconds)
                                <p class="habit-routine__over md-body-large" aria-live="polite" x-show="left === 0" x-cloak>¡Tiempo! Márcalo cuando termines</p>
                            @endif

                            <nav class="habit-routine__dots" aria-label="Hábitos de la rutina">
                                @foreach ($queue as $i => $id)
                                    @php $state = $status[(string) $id] ?? 'pending'; @endphp
                                    <button type="button"
                                            wire:click="routineGoTo({{ $i }})"
                                            @class(['habit-routine__dot', 'is-'.$state, 'is-current' => $i === $routine['index']])
                                            aria-label="Ir a {{ $routineHabits[$id]->name ?? 'hábito' }}, {{ $stateLabels[$state] }}"
                                            @if ($i === $routine['index']) aria-current="step" @endif></button>
                                @endforeach
                            </nav>
                        </main>

                        <footer class="habit-routine__actions">
                            <x-ui.action variant="text" wire:click="routineSkip" wire:loading.attr="disabled">Saltar</x-ui.action>
                            @if ($seconds)
                                <x-ui.action variant="tonal" x-on:click="paused = !paused" x-show="left > 0"><span x-text="paused ? 'Seguir' : 'Pausar'">Pausar</span></x-ui.action>
                            @endif
                            <x-ui.action size="lg" icon="bi-check-lg" x-ref="done" class="habit-routine__primary" x-bind:class="{ 'is-ready': left === 0 }" x-on:click="finish()" x-bind:disabled="cheer">Hecho</x-ui.action>
                        </footer>
                    @else
                        <main class="habit-routine__body">
                            @if ($companion && $companion['image'])
                                <span class="habit-routine__buddy habit-routine__buddy--cheer"><span class="habit-routine__buddy-art"><img src="{{ $companion['image'] }}" width="140" height="140" alt="{{ $companion['name'] }}"></span></span>
                            @else
                                <div class="habit-routine__buddy-icon" aria-hidden="true"><i class="bi {{ $period['icon'] }}"></i></div>
                            @endif
                            <h2 id="habit-routine-title" class="md-headline-medium">Rutina terminada</h2>
                            <p class="md-title-medium">Hiciste {{ $doneCount }} de {{ count($status) }}</p>
                        </main>
                        <footer class="habit-routine__actions">
                            @if ($skippedCount > 0)
                                <x-ui.action variant="tonal" wire:click="routineRetrySkipped">Retomar saltados ({{ $skippedCount }})</x-ui.action>
                            @endif
                            <x-ui.action size="lg" x-ref="done" class="habit-routine__primary" wire:click="closeRoutine">Cerrar</x-ui.action>
                        </footer>
                    @endif
                </div>
              </div>
            </div>
        </template>
    </div>
@endif
