@props([
    'companion',
    'label',
    'moduleKey' => null,
    'items' => [],
    'quick' => [],
    'greeting' => null,
    'binding',
    'tag',
])

@php
    /*
     * Chat del compañero (CHG-100). Reemplaza al FAB cuando el usuario prefiere su Pokémon.
     * - `quick`: acciones rápidas del módulo.
     *   compose = escribir y crear (opcional `fields`: category, when);
     *   tap = registro de un toque (opcional `group`, `then`, `thenSay`);
     *   step = solo cambia de paso de la conversación.
     * - `items`: las acciones de creación de siempre; abren su formulario como el FAB.
     * El estado vive en el navegador (wire:ignore): los renders de Livewire no lo cierran.
     */
    $quick = collect($quick)->filter()->values()->map(fn ($item, $index) => $item + ['id' => $item['id'] ?? 'q'.$index, 'group' => $item['group'] ?? 'start'])->all();
    $covered = collect($quick)->pluck('more.event')->filter()->all();
    $items = collect($items)->reject(fn ($item) => ! empty($item['event']) && in_array($item['event'], $covered, true))->values()->all();
    $fields = collect($quick)->pluck('fields')->flatten()->filter()->unique()->values()->all();
    $voice = config('companion_chat');
    $config = [
        'module' => $moduleKey,
        'greeting' => $greeting ? ($companion['salute'] ?? '').' '.$greeting : $companion['greeting'],
        'followUp' => $voice['follow_up'],
        'error' => $voice['error'],
        'missingResult' => $voice['tasks']['missing_result'],
        'quick' => $quick,
    ];
    $panelId = 'companion-chat-'.($moduleKey ?: 'app');
    $name = $companion['name'];
    $categories = collect($quick)->pluck('options')->filter()->first() ?? [];
    $when = $voice['tasks']['when'];
@endphp

<div class="lt-chat {{ $companion['type_class'] }}" x-data="companionChat(@js($config))" wire:ignore wire:key="{{ $panelId }}"
     :class="{ 'is-open': open }" data-module="{{ $moduleKey }}"
     @keydown.escape.window="if (open) close()" @click.outside="close({ restoreFocus: false })">

    <section class="lt-chat__panel" id="{{ $panelId }}" x-show="open" x-cloak role="dialog" aria-modal="false"
             aria-label="Chat con {{ $name }}"
             x-transition:enter="lt-chat-enter" x-transition:enter-start="lt-chat-enter-start" x-transition:enter-end="lt-chat-enter-end"
             x-transition:leave="lt-chat-leave" x-transition:leave-start="lt-chat-enter-end" x-transition:leave-end="lt-chat-enter-start">
        <header class="lt-chat__head">
            <span class="lt-chat__avatar" aria-hidden="true">
                @if ($companion['image'])
                    <img src="{{ $companion['image'] }}" alt="" width="56" height="56" loading="lazy">
                @else
                    <i class="bi bi-stars"></i>
                @endif
            </span>
            <div class="lt-chat__who">
                <strong class="lt-chat__name">{{ $name }}</strong>
                <span class="lt-chat__status">
                    <span class="lt-chat__dot" aria-hidden="true"></span>
                    {{ $companion['type'] ? ucfirst($companion['type']).' · ' : '' }}Nv. {{ $companion['level'] }}
                </span>
            </div>
            <button type="button" class="lt-chat__close" @click="close()" aria-label="Cerrar chat" title="Cerrar">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <div class="lt-chat__thread" x-ref="thread" role="log" aria-live="polite" aria-label="Mensajes de {{ $name }}">
            <template x-for="message in messages" :key="message.id">
                <div class="lt-chat__row" :class="'lt-chat__row--' + message.from"><p class="lt-chat__bubble" :class="message.tone ? 'lt-chat__bubble--' + message.tone : ''"><span class="lt-chat__typing" x-show="message.typing" role="img" aria-label="{{ $name }} está escribiendo"><i></i><i></i><i></i></span><span class="lt-chat__text" x-show="!message.typing" x-text="message.text"></span></p></div>
            </template>
        </div>

        <div class="lt-chat__chips" :class="{ 'is-ready': showChips }" role="group" aria-label="Acciones rápidas">
            @foreach ($quick as $index => $item)
                @php $type = $item['type'] ?? 'compose'; @endphp
                <button type="button" class="lt-chat__chip" data-chat-chip style="--i: {{ $loop->index }}"
                        :disabled="busy"
                        x-show="step === @js($item['group'])"
                        @if ($type === 'compose')
                            :class="{ 'is-active': composer === {{ $index }} }" :aria-pressed="(composer === {{ $index }}).toString()" @click="startCompose({{ $index }})"
                        @elseif ($type === 'step')
                            @click="goTo({{ $index }})"
                        @else
                            @click="tap({{ $index }})" aria-label="{{ $item['aria'] ?? $item['label'] }}"
                        @endif>
                    @isset($item['emoji'])<span aria-hidden="true">{{ $item['emoji'] }}</span>@else<i class="bi {{ $item['icon'] ?? 'bi-lightning-charge' }}" aria-hidden="true"></i>@endisset
                    <span>{{ $item['label'] }}</span>
                </button>
            @endforeach
            @foreach ($items as $item)
                <{{ $tag($item) }} @if ($tag($item) === 'button') type="button" @endif {!! $binding($item) !!}
                    class="lt-chat__chip lt-chat__chip--link" data-chat-chip style="--i: {{ count($quick) + $loop->index }}"
                    x-show="step === 'start' || step === 'done'" @click="close({ restoreFocus: false })">
                    <i class="bi {{ $item['icon'] ?? 'bi-plus-lg' }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                </{{ $tag($item) }}>
            @endforeach
        </div>

        <form class="lt-chat__composer" x-show="activeComposer" x-cloak @submit.prevent="send()" novalidate>
            @if ($fields)
                <div class="lt-chat__details" x-show="hasFields()">
                    <button type="button" class="lt-chat__details-toggle" @click="detailsOpen = !detailsOpen" :aria-expanded="detailsOpen.toString()" aria-controls="{{ $panelId }}-details">
                        <i class="bi bi-sliders2" aria-hidden="true"></i>
                        <span x-text="detailsSummary()">Detalles</span>
                        <i class="bi bi-chevron-down lt-chat__details-caret" aria-hidden="true"></i>
                    </button>
                    <div class="lt-chat__details-body" id="{{ $panelId }}-details" x-show="detailsOpen" x-collapse.duration.200ms>
                        @if (in_array('category', $fields, true))
                            <div x-show="hasField('category')">
                                <x-ui.category-select name="quickCategory" :id="$panelId.'-category'" :options="$categories" wire:model="quickCategory" />
                            </div>
                        @endif
                        @if (in_array('when', $fields, true))
                            <div class="lt-chat__when" x-show="hasField('when')" role="group" aria-label="¿Para cuándo?">
                                <span class="lt-chat__when-label">¿Para cuándo?</span>
                                <div class="lt-chat__when-options">
                                    @foreach ($when as $key => $text)
                                        <button type="button" class="lt-chat__mini" :class="{ 'is-active': wire.quickWhen === @js($key) }"
                                                :aria-pressed="(wire.quickWhen === @js($key)).toString()" @click="wire.quickWhen = @js($key)">{{ $text }}</button>
                                    @endforeach
                                    <label class="lt-chat__time" x-show="wire.quickWhen !== ''">
                                        <i class="bi bi-clock" aria-hidden="true"></i>
                                        <span class="visually-hidden">Hora</span>
                                        <input type="time" wire:model="quickTime" aria-label="Hora (opcional)">
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
            <div class="lt-chat__suggestions" id="{{ $panelId }}-suggestions" role="listbox" aria-label="{{ $voice['tasks']['suggestions_label'] }}" x-show="suggestions.length" x-cloak>
                <template x-for="(suggestion, position) in suggestions" :key="suggestion.index">
                    <button type="button" role="option" :id="'{{ $panelId }}-suggestion-' + position" :aria-selected="(suggestionIndex === position).toString()"
                            :class="{ 'is-selected': suggestionIndex === position }" @mousedown.prevent @click="chooseSuggestion(suggestion.index)" x-text="suggestion.label"></button>
                </template>
            </div>
            <div class="lt-chat__entry" :class="{ 'has-error': error }">
                <textarea x-ref="entry" x-model="draft" @keydown="onKeydown($event)" :rows="activeComposer?.mode === 'bulk' ? 3 : 1"
                          :placeholder="activeComposer?.placeholder ?? 'Escribe aquí…'" :aria-label="activeComposer?.placeholder ?? 'Escribe aquí'"
                          :aria-invalid="error ? 'true' : 'false'" aria-describedby="{{ $panelId }}-error"
                          aria-autocomplete="list" aria-controls="{{ $panelId }}-suggestions" :aria-activedescendant="suggestionIndex >= 0 ? '{{ $panelId }}-suggestion-' + suggestionIndex : null"
                          autocomplete="off" enterkeyhint="send"></textarea>
                <button type="submit" class="lt-chat__send" :disabled="busy" :aria-label="submitLabel()" :title="submitLabel()">
                    <i class="bi" :class="busy ? 'bi-three-dots' : 'bi-send-fill'" aria-hidden="true"></i>
                </button>
            </div>
            <p class="lt-chat__error" id="{{ $panelId }}-error" role="alert" x-show="error" x-text="error"></p>
            <div class="lt-chat__foot">
                <span class="lt-chat__hint" x-text="activeComposer?.mode === 'bulk' ? 'Una por línea · Ctrl+Enter crea' : 'Enter crea'"></span>
                <button type="button" class="lt-chat__more" :disabled="busy" x-show="activeComposer?.more" @click="moreOptions()">Más opciones <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </div>
        </form>
    </section>

    <div class="lt-chat__dock">
        <p class="lt-chat__nudge" x-show="hint && !open" x-cloak x-transition.opacity.duration.250ms role="status">{{ $voice['hint'] }}</p>
        <span class="lt-chat__peek" aria-hidden="true">{{ $label }}</span>
        <button type="button" class="lt-chat__launcher" x-ref="launcher" @click="toggle()"
                :aria-expanded="open.toString()" aria-controls="{{ $panelId }}"
                aria-label="{{ $label }} con {{ $name }}" title="{{ $label }}">
            <span class="lt-chat__launcher-ring" aria-hidden="true"></span>
            @if ($companion['image'])
                <img class="lt-chat__launcher-art" src="{{ $companion['image'] }}" alt="" width="64" height="64">
            @else
                <i class="bi bi-stars lt-chat__launcher-art" aria-hidden="true"></i>
            @endif
            <span class="lt-chat__launcher-badge" aria-hidden="true"><i class="bi" :class="open ? 'bi-x-lg' : 'bi-plus-lg'"></i></span>
        </button>
    </div>
</div>
