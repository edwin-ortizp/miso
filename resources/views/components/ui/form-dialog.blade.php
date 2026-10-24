@props([
    'open' => false,
    'state' => null,
    'validationState' => null,
    'titleExpression' => null,
    'close',
    'title',
    'icon' => null,
    'sections' => [],
    'submit' => 'Guardar',
    'submitAction' => null,
    'submitExpression' => null,
    'id' => null,
    'module' => null,
    'expanded' => false,
])

@php
    abort_unless(filled($close), 500, 'Un diálogo de formulario debe declarar la acción que lo cierra.');

    $dialogId = $id ?? 'form-dialog-'.\Illuminate\Support\Str::slug($title);
    $firstSection = array_key_first($sections);
    $moduleKey = \App\Support\Ui\ModuleContext::current($module);
@endphp

{{--
    Modal de formulario canónico: cabecera y footer con divisoria, secciones
    opcionales (menú lateral colapsable, no un wizard), expandir/restaurar y
    Cancelar a la izquierda / Guardar a la derecha.

    Se teletransporta a <body>: el contenido de la página vive en un contexto de
    apilamiento propio (view transitions) y un modal dentro de él quedaría por
    debajo de la barra superior, la navegación y el FAB. `data-module` conserva
    el acento del módulo fuera del shell.
--}}
@if ($state || $open)
    @teleport('body')
        <div class="md-form-dialog-layer" data-module="{{ $moduleKey }}" x-dialog-viewport="{{ $state ?? '' }}"
             @if($state) x-show="{{ $state }}" x-cloak :class="{'lt-editor-pristine': !submitted}" @endif
             x-data="{ section: @js($firstSection), navCollapsed: window.matchMedia('(max-width: 767.98px)').matches, expanded: @js((bool) $expanded) }" @if($state) x-effect="if ({{ $state }}) { section = @js($firstSection); expanded = @js((bool) $expanded); navCollapsed = window.matchMedia('(max-width: 767.98px)').matches }" @endif>
            <div class="md-dialog-scrim" @if($state) x-on:click="closeEditor()" @else wire:click="{{ $close }}" @endif></div>
            <section {{ $attributes->class(['md-form-dialog', 'md-form-dialog--sections' => $sections !== []]) }}
                     id="{{ $dialogId }}"
                     :class="{ 'is-expanded': expanded, 'is-nav-collapsed': navCollapsed }"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="{{ $dialogId }}-title"
                     x-md-surface="{{ $state ?? '' }}"
                     @md-surface-close="{{ $state ? 'closeEditor()' : '$refs.cancel.click()' }}">
                <form class="md-form-dialog__form" novalidate @if($state) @submit="submitted = true" @endif @if ($submitExpression) @submit.prevent="{{ $submitExpression }}" @elseif ($submitAction) wire:submit="{{ $submitAction }}" @else @submit.prevent @endif>
                    <header class="md-form-dialog__head" role="presentation">
                        @if ($sections !== [])
                            <button type="button" class="md-btn-icon" @click="navCollapsed = !navCollapsed"
                                    :aria-expanded="(!navCollapsed).toString()" aria-controls="{{ $dialogId }}-nav"
                                    aria-label="Mostrar u ocultar secciones" title="Secciones">
                                <i class="bi bi-layout-sidebar" aria-hidden="true"></i>
                            </button>
                        @endif
                        @if ($icon)
                            <span class="md-form-dialog__icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                        @endif
                        <h2 id="{{ $dialogId }}-title" class="md-form-dialog__title" @if($titleExpression) x-text="{{ $titleExpression }}" @endif>{{ $title }}</h2>
                        <button type="button" class="md-btn-icon" @click="expanded = !expanded"
                                :aria-pressed="expanded.toString()" :aria-label="expanded ? 'Restaurar' : 'Expandir'" :title="expanded ? 'Restaurar' : 'Expandir'">
                            <i class="bi" :class="expanded ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="md-btn-icon" @if($state) x-on:click="closeEditor()" @else wire:click="{{ $close }}" @endif aria-label="Cerrar" title="Cerrar">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </header>

                    <div class="md-form-dialog__main">
                        @if ($sections !== [])
                            <nav id="{{ $dialogId }}-nav" class="md-form-dialog__nav" aria-label="Secciones del formulario">
                                @foreach ($sections as $key => $section)
                                    <button type="button" class="md-form-dialog__nav-item"
                                            @click="section = @js($key); if (window.matchMedia('(max-width: 767.98px)').matches) navCollapsed = true"
                                            :aria-current="section === @js($key) ? 'true' : null">
                                        <i class="bi {{ $section['icon'] }}" aria-hidden="true"></i>
                                        <span>{{ $section['label'] }}</span>
                                        @if (! empty($section['error']))
                                            <span class="md-form-dialog__nav-dot" role="img" aria-label="Contiene errores"></span>
                                        @endif
                                    </button>
                                @endforeach
                            </nav>
                        @endif
                        <div class="md-form-dialog__body">
                            @if($state)
                                <p role="status" x-show="loading" x-cloak>Cargando…</p>
                                <p role="alert" x-show="loadError" x-text="loadError" x-cloak></p>
                                <div :inert="loading || !!loadError">{{ $slot }}</div>
                            @else
                                {{ $slot }}
                            @endif
                        </div>
                    </div>

                    <footer class="md-form-dialog__actions">
                        <button type="button" x-ref="cancel" class="md-btn-text" @if($state) x-on:click="closeEditor()" @else wire:click="{{ $close }}" @endif>Cancelar</button>
                        <button type="submit" class="md-btn-filled" wire:loading.attr="disabled" @if($state) :disabled="loading || !!loadError" @endif>
                            <i class="bi bi-floppy" aria-hidden="true"></i>
                            <span>{{ $submit }}</span>
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    @endteleport
@endif
