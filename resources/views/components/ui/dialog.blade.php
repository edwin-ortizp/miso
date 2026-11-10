@props([
    'state',
    'title',
    'size' => 'md',
    'icon' => null,
    'describedBy' => null,
    'standard' => false,
    'closeExpression' => null,
    'expanded' => false,
    'persistentContent' => false,
    'sections' => [],
])

@php
    $closeAction = $closeExpression ?? $state.' = false';
    $sizes = ['md', 'lg', 'fullscreen'];

    abort_unless(in_array($size, $sizes, true), 500, "Tamaño de diálogo no soportado: {$size}");

    $firstSection = array_key_first($sections);
    $headingId = 'dialog-'.\Illuminate\Support\Str::slug($title);
    $surfaceClasses = [
        'md-dialog',
        'md-dialog--large' => $size === 'lg',
        'md-dialog-fullscreen' => $size === 'fullscreen',
    ];
@endphp

@if ($standard)
    @teleport('body')
        {{-- x-show permite actualizar con Livewire el contenido de diálogos accionables teletransportados. --}}
        @unless ($persistentContent)<template x-if="{{ $state }}">@endunless
            <div @if ($persistentContent) x-show="{{ $state }}" x-cloak @endif class="md-form-dialog-layer" data-module="{{ \App\Support\Ui\ModuleContext::current() }}" x-dialog-viewport
                 x-data="{ expanded: {{ $size === 'fullscreen' || $expanded ? 'true' : 'false' }}, section: @js($firstSection), navCollapsed: window.matchMedia('(max-width: 767.98px)').matches }"
                 @if ($sections) x-effect="if ({{ $state }}) { section = @js($firstSection); navCollapsed = window.matchMedia('(max-width: 767.98px)').matches }" @endif>
                <div class="md-dialog-scrim" @click="{{ $closeAction }}"></div>
                <section {{ $attributes->class(['md-form-dialog', 'md-form-dialog--large' => $size === 'lg', 'md-form-dialog--sections' => $sections !== []]) }} :class="{ 'is-expanded': expanded, 'is-nav-collapsed': navCollapsed }"
                         role="dialog" aria-modal="true" aria-labelledby="{{ $headingId }}"
                         @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                         x-md-surface="{{ $persistentContent ? $state : '' }}" @md-surface-close="{{ $closeAction }}">
                    <div class="md-form-dialog__form">
                        <header class="md-form-dialog__head" role="presentation">
                            @if ($sections)
                                <button type="button" class="md-btn-icon" @click="navCollapsed = !navCollapsed"
                                        :aria-expanded="(!navCollapsed).toString()" aria-controls="{{ $headingId }}-nav"
                                        aria-label="Mostrar u ocultar secciones" title="Secciones"><i class="bi bi-layout-sidebar" aria-hidden="true"></i></button>
                            @endif
                            @if ($icon)<span class="md-form-dialog__icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>@endif
                            <h2 id="{{ $headingId }}" class="md-form-dialog__title">{{ $title }}</h2>
                            <button type="button" class="md-btn-icon" @click="expanded = !expanded"
                                    :aria-pressed="expanded.toString()" :aria-label="expanded ? 'Restaurar' : 'Expandir'">
                                <i class="bi" :class="expanded ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="md-btn-icon" aria-label="Cerrar" @click="{{ $closeAction }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </header>
                        <div class="md-form-dialog__main">
                            @if ($sections)
                                <nav id="{{ $headingId }}-nav" class="md-form-dialog__nav" aria-label="Secciones del diálogo">
                                    @foreach ($sections as $key => $item)
                                        <button type="button" class="md-form-dialog__nav-item" @click="section = @js($key); if (window.matchMedia('(max-width: 767.98px)').matches) { navCollapsed = true; $nextTick(() => $el.closest('[role=dialog]').querySelector('.md-form-dialog__body').focus()) }"
                                                :aria-current="section === @js($key) ? 'true' : null">
                                            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                                        </button>
                                    @endforeach
                                </nav>
                            @endif
                            <div class="md-form-dialog__body" @if ($sections) tabindex="0" @endif>{{ $slot }}</div>
                        </div>
                        <footer class="md-form-dialog__actions" role="presentation">
                            @isset($actions) {{ $actions }} @else <button type="button" class="md-btn-text" @click="{{ $closeAction }}">Cerrar</button> @endisset
                        </footer>
                    </div>
                </section>
            </div>
        @unless ($persistentContent)</template>@endunless
    @endteleport
@else
@if ($size !== 'md') @teleport('body') @endif
<template x-if="{{ $state }}">
    <div @if ($size !== 'md') data-module="{{ \App\Support\Ui\ModuleContext::current() }}" @endif>
        <div class="md-dialog-scrim" @click="{{ $closeAction }}"></div>
        <div {{ $attributes->class($surfaceClasses) }}
             @if ($size !== 'md') x-dialog-viewport @endif
             role="dialog"
             aria-modal="true"
             aria-labelledby="{{ $headingId }}"
             @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
             x-md-surface
             @md-surface-close="{{ $closeAction }}"
             @click.stop>
            @if ($size !== 'md') <header class="md-dialog-responsive-head"> @endif
            @if ($icon)
                <div class="md-dialog-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
            @endif

            <h2 id="{{ $headingId }}" class="md-dialog-headline md-headline-small">{{ $title }}</h2>
            @if ($size !== 'md')
                <button type="button" class="md-btn-icon md-dialog-mobile-close" aria-label="Cerrar" @click="$dispatch('md-surface-close')"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </header>
            @endif

            <div class="md-dialog-content">{{ $slot }}</div>

            @isset($actions)
                <div class="md-dialog-actions">{{ $actions }}</div>
            @endisset
        </div>
    </div>
</template>
@if ($size !== 'md') @endteleport @endif

@endif
