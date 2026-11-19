@props([
    'name' => null,
    'label',
    'id' => null,
    'options' => [],
    'searchModel' => null,
    'multiple' => false,
    'selectedOptions' => [],
    'searchTerm' => '',
    'minSearchLength' => 2,
    'moreResults' => false,
    'placeholder' => 'Buscar',
    'empty' => 'Sin resultados',
    'help' => null,
    'error' => null,
])

{{-- El modo simple filtra las opciones en el cliente. El modo múltiple muestra las coincidencias remotas y conserva la selección en wire:model. --}}
@php
    $fieldId = $id ?? \App\Support\Ui\FieldIdentity::for($name, $label);
    $model = $attributes->wire('model')->value();
    $message = $error ?? ($name && isset($errors) ? $errors->first($name) : null);
    $describedBy = trim(($help && $multiple ? $fieldId.'-help ' : '').($message ? $fieldId.'-error' : ''));
    $formatOptions = fn ($choices) => collect($choices)->map(fn ($text, $value) => ['value' => (string) $value, 'label' => (string) $text])->values();
@endphp

<div class="md-text-field md-field md-combobox @if ($message) md-error @endif"
     {{ $attributes->only('wire:key') }}
     data-options="{{ $formatOptions($options)->toJson() }}"
     @if ($multiple)
         data-selected-options="{{ $formatOptions($selectedOptions)->toJson() }}"
         data-search-term="{{ $searchTerm }}" data-more-results="{{ (int) $moreResults }}"
     @endif
     x-data="{
         value: {!! $model ? "\$wire.entangle('{$model}')".($attributes->wire('model')->hasModifier('live') ? '.live' : '') : "''" !!},
         searchModel: @js($searchModel),
         multiple: @js($multiple),
         minimum: @js($minSearchLength),
         options: [],
         labels: {},
         resultTerm: '',
         more: false,
         syncOptions() {
             this.options = JSON.parse(this.$el.dataset.options);
             if (this.multiple) {
                 for (const option of JSON.parse(this.$el.dataset.selectedOptions)) this.labels[option.value] = option.label;
                 this.resultTerm = this.$el.dataset.searchTerm;
                 this.more = this.$el.dataset.moreResults === '1';
             }
             this.active = -1;
             if (! this.open && ! this.multiple) this.term = this.labelFor(this.value);
         },
         observer: null,
         init() {
             this.syncOptions();
             this.observer = new MutationObserver(() => this.syncOptions());
             this.observer.observe(this.$el, { attributes: true, attributeFilter: ['data-options', 'data-selected-options', 'data-search-term', 'data-more-results'] });
         },
         destroy() { this.observer?.disconnect(); },
         fieldLabel: @js($label),
         term: '',
         open: false,
         active: -1,
         normalize: text => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase(),
         get searchText() { return this.term.trim(); },
         get searching() { return this.multiple && this.searchText.length >= this.minimum && this.searchText !== this.resultTerm; },
         get filtered() {
             if (this.multiple) return this.searching || this.searchText.length < this.minimum ? [] : this.options;
             const term = this.normalize(this.term.trim());
             return this.options.filter(option => this.normalize(option.label).includes(term));
         },
         selected(id) { return this.multiple ? (this.value ?? []).includes(id) : id === String(this.value ?? ''); },
         remove(id) { this.value = (this.value ?? []).filter(value => value !== id); },
         labelFor(value) { return this.options.find(option => option.value === String(value ?? ''))?.label ?? ''; },
         search() {
             if (this.searchModel && (! this.multiple || this.searchText.length >= this.minimum)) {
                 this.$wire.set(this.searchModel, this.multiple ? this.searchText : this.term);
             }
         },
         show() { this.term = ''; this.open = true; this.active = -1; this.search(); },
         choose(option) {
             if (this.multiple) {
                 this.labels[option.value] = option.label;
                 if (this.selected(option.value)) this.remove(option.value);
                 else this.value = [...(this.value ?? []), option.value];
             } else {
                 this.value = option.value;
                 this.close();
             }
             this.$refs.input.focus({ preventScroll: true });
         },
         toggle() { this.$refs.input.focus({ preventScroll: true }); if (this.open) this.close(); else this.show(); },
         close() { this.open = false; this.active = -1; this.term = this.multiple ? '' : this.labelFor(this.value); },
         move(step) { if (! this.open) this.show(); const count = this.filtered.length; if (! count) return; this.active = this.active < 0 ? (step > 0 ? 0 : count - 1) : (this.active + step + count) % count; this.$nextTick(() => this.$refs.list.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' })); },
     }"
     x-md-surface.nonmodal="open" @md-surface-close.stop="close(); $refs.input.focus({ preventScroll: true })"
     x-init="if (! multiple) $watch('value', value => { if (! open) term = labelFor(value) })"
     x-on:pointerdown.window.capture="if (open && ! $el.contains($event.target)) close()"
     x-on:focusout="$nextTick(() => { if (! $el.contains(document.activeElement)) close() })"
     x-on:keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); close(); $refs.input.focus({ preventScroll: true }) }">
    <input type="text" id="{{ $fieldId }}" role="combobox" autocomplete="off" placeholder=" " @if ($multiple) maxlength="160" @endif
           class="md-field__control" aria-autocomplete="list" aria-controls="{{ $fieldId }}-list"
           x-ref="input" x-bind:aria-expanded="open" x-model="term"
           x-bind:aria-activedescendant="open && active >= 0 ? '{{ $fieldId }}-option-' + active : null"
           x-on:click="if (! open) show(); else if (! multiple) close()" x-on:input="open = true; active = -1"
           x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
           x-on:keydown.enter="if (open && (multiple || filtered[active])) { $event.preventDefault(); if (filtered[active]) choose(filtered[active]) }"
           x-on:keydown.tab="close()"
           @if ($searchModel) x-on:input.debounce.250ms="if (open) search()" @endif
           @if ($message) aria-invalid="true" @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
    <label for="{{ $fieldId }}">{{ $label }}</label>
    <x-ui.icon-action icon="bi-chevron-down" :label="'Mostrar opciones de '.$label"
        class="md-combobox__chevron" x-bind:aria-label="(open ? 'Ocultar opciones de ' : 'Mostrar opciones de ') + fieldLabel"
        x-bind:aria-expanded="open" aria-controls="{{ $fieldId }}-list" x-on:click="toggle()" />
    <ul id="{{ $fieldId }}-list" role="listbox" class="md-combobox__list" x-ref="list" @if ($multiple) aria-multiselectable="true" x-bind:aria-busy="searching" @endif x-show="open" x-cloak x-on:mousedown.prevent>
        @unless ($multiple)
        <li role="option" x-on:click="choose({ value: '', label: '' })" class="md-combobox__option md-combobox__option--muted" x-show="term === ''" aria-selected="false">{{ $placeholder }}</li>
        @endunless
        <template x-for="(option, index) in filtered" :key="option.value">
            <li role="option" class="md-combobox__option" x-text="option.label"
                x-bind:id="'{{ $fieldId }}-option-' + index"
                x-bind:class="{ 'is-active': index === active, 'is-selected': selected(option.value) }"
                x-bind:aria-selected="selected(option.value)"
                x-on:click="choose(option)"></li>
        </template>
        @if ($multiple)
            <li class="md-combobox__empty" role="presentation" x-show="searchText.length < minimum">Escribe al menos {{ $minSearchLength }} caracteres.</li>
            <li class="md-combobox__empty" role="presentation" x-show="searching">Buscando…</li>
            <li class="md-combobox__empty" role="presentation" x-show="!searching && searchText.length >= minimum && filtered.length === 0">{{ $empty }}</li>
            <li class="md-combobox__empty" role="presentation" x-show="!searching && filtered.length && more">Escribe un nombre más preciso para ver otras coincidencias.</li>
        @else
            <li class="md-combobox__empty" x-show="filtered.length === 0">{{ $empty }}</li>
        @endif
    </ul>
    @if ($multiple)
        <span class="visually-hidden" role="status" x-text="open && searchText.length >= minimum ? (searching ? 'Buscando…' : (filtered.length ? filtered.length + ' coincidencias' : @js($empty))) : ''"></span>
        <div class="md-combobox__selection" x-show="value?.length" x-cloak>
            <template x-for="id in (value ?? [])" :key="id">
                <span class="md-chip md-chip-input md-applied-chip">
                    <span x-text="labels[id] ?? 'Tarea no disponible'"></span>
                    <button type="button" class="md-btn-icon md-applied-chip__remove"
                            x-bind:aria-label="'Quitar ' + (labels[id] ?? 'tarea no disponible')"
                            x-on:click="remove(id); $refs.input.focus({ preventScroll: true })">
                        <i class="bi bi-x" aria-hidden="true"></i>
                    </button>
                </span>
            </template>
        </div>
    @endif
    @if ($help)<p id="{{ $fieldId }}-help" class="md-supporting-text">{{ $help }}</p>@endif
    @if ($message)<p id="{{ $fieldId }}-error" class="md-supporting-text" role="alert">{{ $message }}</p>@endif
</div>
