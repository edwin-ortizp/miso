@props(['label', 'items' => [], 'current'])

{{-- Navegación lateral de secciones de Ajustes de un módulo. items: [clave => ['label', 'icon', 'route']]. --}}
<nav class="module-settings-nav" aria-label="{{ $label }}">
    @foreach ($items as $key => $item)
        <a href="{{ route($item['route']) }}" wire:navigate
           class="module-settings-nav__item {{ $current === $key ? 'is-active' : '' }}"
           @if ($current === $key) aria-current="page" @endif>
            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}
        </a>
    @endforeach
</nav>
