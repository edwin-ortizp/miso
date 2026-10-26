@props([
    'icon' => null,
    'tone' => 'neutral',
    'href' => null,
])

@php
    abort_unless(in_array($tone, ['neutral', 'danger', 'success'], true), 500, "Tono de opción de menú no soportado: {$tone}");

    $classes = ['md-menu__item', 'md-menu__item--danger' => $tone === 'danger', 'md-menu__item--success' => $tone === 'success'];
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="button" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
        <span>{{ $slot }}</span>
    </button>
@endif
