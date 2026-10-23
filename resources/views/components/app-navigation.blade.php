@props(['side' => 'left', 'toggle' => false])
@php
    abort_unless(in_array($side, ['left', 'right'], true), 500, 'Posición de navegación no válida.');
@endphp
@if ($toggle)
    <button type="button" class="md-btn-icon lt-navigation-toggle" @click="toggleNav()" aria-controls="app-navigation"
            :aria-label="compact ? (sidebarOpen ? 'Cerrar los módulos' : 'Mostrar los módulos') : (sidebarRail ? 'Expandir la barra de módulos' : 'Colapsar la barra de módulos')"
            :aria-expanded="compact ? sidebarOpen : !sidebarRail"><i class="bi bi-list" aria-hidden="true"></i></button>
@endif
    <aside id="app-navigation" class="lt-sidebar lt-sidebar--{{ $side }}" :class="{ 'is-open': sidebarOpen }" aria-label="Módulos de MiSo"
           :inert="compact && !sidebarOpen" :aria-hidden="(compact && !sidebarOpen).toString()" x-md-surface="compact && sidebarOpen"
           @md-surface-close="sidebarOpen = false">
        <div class="lt-sidebar__brand">
            @unless ($toggle)
                <span class="lt-sidebar__mark" aria-hidden="true"><i class="bi bi-activity"></i></span>
            @endunless
            <span class="lt-sidebar__name">MiSo</span>
        </div>
        <nav class="lt-sidebar__scroll">
            @foreach (config('modules.navigation', []) as $section)
                <div class="lt-nav-group">
                    <span class="lt-nav-group__label">{{ $section['label'] }}</span>
                    @foreach ($section['items'] as $item)
                        @php($active = request()->routeIs(...$item['active']))
                        <a href="{{ route($item['route']) }}" wire:navigate class="lt-nav-item {{ $active ? 'is-active' : '' }}"
                           @isset($item['module']) data-module="{{ $item['module'] }}" @endisset
                           @if($active) aria-current="page" @endif
                           @click="sidebarOpen = false" :tabindex="compact && !sidebarOpen ? -1 : 0">
                            <span class="lt-nav-item__icon"><i class="bi {{ $item['icon'] }}" aria-hidden="true"></i></span>
                            <span class="lt-nav-item__label">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

<button type="button" class="lt-scrim" x-show="sidebarOpen" x-cloak aria-label="Cerrar los módulos" @click="sidebarOpen = false" tabindex="-1"></button>
