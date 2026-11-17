<div>
    @if ($open && $task)
        <div wire:key="find-slot-{{ $task->id }}">
            <x-ui.form-dialog :open="$open" close="close" module="tasks" icon="bi-calendar2-check" id="find-slot-dialog"
                              title="Buscar un hueco" :submit="$selectedLabel ? 'Agendar '.$selectedLabel : 'Agendar aquí'"
                              submit-action="schedule" :protect-changes="false">
                <div class="find-slot">
                    <p class="find-slot__task md-title-medium">{{ $task->title }}</p>

                    <div class="find-slot__filters">
                        <x-ui.choice-chips name="minutes" label="Duración" :multiple="false" :live="true" :options="\App\Livewire\Task\FindSlot::DURATIONS" />
                        <div class="md-field-pair find-slot__row">
                            <x-ui.select name="range" label="Rango" :options="\App\Livewire\Task\FindSlot::RANGES" :selected="$range" wire:model.live="range" />
                            <x-ui.select name="part" label="Momento" :options="\App\Livewire\Task\FindSlot::PARTS" :selected="$part" wire:model.live="part" />
                        </div>
                        <x-ui.field name="minutes" label="Otra duración (min)" type="number" inputmode="numeric" min="5" max="480" step="5"
                                    wire:model.live.debounce.600ms="minutes" />
                    </div>

                    @if ($window)
                        <p class="md-body-small find-slot__note">Según tu horario de {{ $window['start']->format('H:i') }} a {{ $window['end']->format('H:i') }}.
                            <a href="{{ route('tasks.settings.planning') }}" wire:navigate>Cambiar</a></p>
                    @else
                        <p class="md-body-small find-slot__note">Según tu horario para planificar. <a href="{{ route('tasks.settings.planning') }}" wire:navigate>Cambiar</a></p>
                    @endif

                    <div wire:loading.delay wire:target="minutes,range,part" class="find-slot__loading" role="status">Buscando huecos…</div>

                    <section aria-labelledby="find-slot-suggestions" wire:loading.remove wire:target="minutes,range,part">
                        <h3 class="md-title-small" id="find-slot-suggestions">Horarios sugeridos</h3>
                        @forelse ($groups as $date => $daySlots)
                            @php $day = \Carbon\Carbon::parse($date, config('app.timezone')); @endphp
                            <div class="find-slot__day" wire:key="slots-{{ $date }}">
                                <h4 class="md-label-large">{{ $day->isToday() ? 'Hoy' : ($day->isTomorrow() ? 'Mañana' : ucfirst($day->translatedFormat('l'))) }}, {{ $day->translatedFormat('j \d\e F') }}</h4>
                                <div class="md-choice-chips__rail" role="group" aria-label="Horarios del {{ $day->translatedFormat('l j \d\e F') }}">
                                    @foreach ($daySlots as $slot)
                                        @php $key = $slot['start']->format('Y-m-d H:i'); $on = $selected === $key; @endphp
                                        <button type="button" class="md-choice-chip {{ $on ? 'is-selected' : '' }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                                aria-label="{{ ucfirst($slot['start']->translatedFormat('l j \d\e F')) }}, {{ $slot['start']->format('H:i') }} a {{ $slot['end']->format('H:i') }}"
                                                wire:click="select('{{ $key }}')" wire:key="slot-{{ $key }}">
                                            @if ($on)<i class="bi bi-check2 md-choice-chip__check" aria-hidden="true"></i>@endif
                                            <span>{{ $slot['start']->format('H:i') }}–{{ $slot['end']->format('H:i') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <x-ui.state variant="empty" icon="bi-calendar-x" title="No encontré huecos"
                                        message="No encontré huecos de {{ max(5, min(480, (int) $minutes)) }} min en este rango. Prueba con otro rango o una duración menor.">
                                @if ($range === 'this')
                                    <x-slot:actions>
                                        <x-ui.action variant="tonal" type="button" wire:click="$set('range', 'next')">Ver la próxima semana</x-ui.action>
                                    </x-slot:actions>
                                @endif
                            </x-ui.state>
                        @endforelse
                        @error('selected')<p class="md-field-error" role="alert">{{ $message }}</p>@enderror
                    </section>

                    @if ($dayBlocks)
                        <section class="find-slot__timeline" aria-labelledby="find-slot-timeline">
                            <h3 class="md-title-small" id="find-slot-timeline">Tu día</h3>
                            <ol class="find-slot__blocks">
                                @foreach ($dayBlocks as $block)
                                    <li class="find-slot__block {{ $block['kind'] === 'slot' ? 'is-proposal' : '' }}">
                                        <span class="find-slot__time">{{ $block['start']->format('H:i') }}–{{ $block['end']->format('H:i') }}</span>
                                        <span class="find-slot__title"><i class="bi {{ $block['kind'] === 'slot' ? 'bi-calendar2-check' : (['task' => 'bi-list-check', 'meal' => 'bi-egg-fried', 'medication' => 'bi-capsule'][$block['source']] ?? 'bi-dot') }}" aria-hidden="true"></i> {{ $block['title'] }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif
                </div>
            </x-ui.form-dialog>
        </div>
    @endif

    @if ($feedback)
        <x-ui.snackbar wire:key="find-slot-feedback">
            {{ $feedback }}
            <x-slot:actions>
                @if ($undo)
                    <button type="button" class="md-btn-text" wire:click="undoSchedule">Deshacer</button>
                @endif
                <x-ui.icon-action icon="bi-x-lg" size="sm" label="Cerrar aviso" wire:click="dismissFeedback" />
            </x-slot:actions>
        </x-ui.snackbar>
    @endif
</div>
