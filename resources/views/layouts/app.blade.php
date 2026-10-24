<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
    <meta charset="UTF-8">
    {{-- `viewport-fit=cover` deja que el contenido llegue a los bordes; las
         areas seguras se respetan despues con los tokens `--lt-safe-*`. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MiSo' }}</title>

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    {{-- Mismo color que declara el manifest: si difieren, la barra de estado
         cambia de tono al instalar la aplicacion. --}}
    <meta name="theme-color" content="#2B5BB5">
    <meta name="color-scheme" content="light dark">

    {{-- iOS no lee el manifest: necesita sus propias etiquetas para abrirse
         sin barra de navegador y con el icono correcto. --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MiSo">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">

    @include('partials.app-styles')
    @include('partials.app-scripts')
    @livewireStyles
</head>
@php
    $routeName = request()->route()?->getName() ?? '';
    $activeModuleKey = null;
    foreach (config('modules', []) as $key => $candidate) {
        if ($key === 'navigation') {
            continue;
        }
        foreach ($candidate['patterns'] ?? [] as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $routeName)) {
                $activeModuleKey = $key;
                break 2;
            }
        }
    }
    $activeModuleTitle = $activeModuleKey ? (config("modules.{$activeModuleKey}.title") ?? 'MiSo') : 'MiSo';

    $searchIndex = collect(config('modules.navigation', []))
        ->flatMap(fn ($section) => $section['items'] ?? [])
        ->map(fn ($item) => ['label' => $item['label'], 'icon' => $item['icon'], 'href' => route($item['route'])])
        ->values();
@endphp
{{-- Un unico marco para todos los anchos: el CSS decide que se ve y el estado
     de Alpine solo decide comportamiento (que hace el boton de menu, que
     etiqueta accesible le corresponde). No hay variante por dispositivo. --}}
<body class="lt-frame"
      x-data="ltNavigation()"
      :class="{ 'is-rail': sidebarRail }">

    {{-- Aviso de conexion: la aplicacion sirve datos cacheados por el service
         worker, asi que hay que decir que lo que se ve puede estar viejo. --}}
    <div class="lt-offline" x-data="ltConnection()" :class="{ 'is-visible': offline }" role="status" aria-live="polite">
        Sin conexión — se muestran los datos guardados
    </div>

    <x-app-navigation />

    <div class="lt-frame__body">
        <header class="lt-topbar">
            <button type="button" class="md-btn-icon lt-topbar__menu" x-show="!(compact && searchOpen)" @click="toggleNav()"
                    :aria-label="compact ? 'Mostrar los módulos' : (sidebarRail ? 'Expandir la barra de módulos' : 'Colapsar la barra de módulos')"
                    :aria-expanded="compact ? sidebarOpen : !sidebarRail">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            {{-- Solo en compacto: mientras se busca, este botón cierra la búsqueda
                 en vez de abrir la sidebar; en el resto de casos no se muestra. --}}
            <button type="button" class="md-btn-icon" x-show="compact && searchOpen" x-cloak
                    @click="searchOpen = false; search = ''" aria-label="Cerrar búsqueda">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
            </button>

            {{-- Detalle sin pestañas: el shell envía aquí su regreso, antes del ícono del módulo. --}}
            @hasstack('module-back')
                <span class="lt-topbar__back-slot" x-show="!(compact && searchOpen)">@stack('module-back')</span>
            @endif

            @if ($activeModuleKey)
                <span class="lt-topbar__icon" data-module="{{ $activeModuleKey }}" aria-hidden="true" x-show="!(compact && searchOpen)"><i class="bi {{ config("modules.{$activeModuleKey}.icon") ?? 'bi-grid' }}"></i></span>
            @endif
            <h2 class="lt-topbar__title" x-show="!(compact && searchOpen)">@hasstack('module-title')@stack('module-title')@else{{ $activeModuleTitle }}@endif</h2>

            {{-- Escritorio: buscador siempre visible. Compacto: icono que expande
                 a buscador de ancho completo, patrón M3 de "search view". --}}
            <button type="button" class="md-btn-icon" x-show="compact && !searchOpen" @click="searchOpen = true; $nextTick(() => document.getElementById('lt-global-search').focus())" aria-label="Buscar">
                <i class="bi bi-search" aria-hidden="true"></i>
            </button>
            <div class="lt-search" role="search" x-show="!compact || searchOpen" x-data="{ open: false }" @click.outside="open = false">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="lt-global-search">Buscar en todos los módulos</label>
                <input id="lt-global-search" type="search" placeholder="Buscar en todos los módulos…" x-ref="globalSearch"
                       x-model="search" @focus="open = true" @input="open = true">
                <template x-if="open && search.length">
                    <div class="lt-pop__surface lt-pop__surface--under">
                        <template x-for="result in ({{ \Illuminate\Support\Js::from($searchIndex) }}).filter(m => m.label.toLowerCase().includes(search.toLowerCase()))" :key="result.href">
                            <a class="lt-pop__item" wire:navigate :href="result.href">
                                <i :class="'bi ' + result.icon" aria-hidden="true"></i>
                                <span x-text="result.label"></span>
                            </a>
                        </template>
                    </div>
                </template>
            </div>

            <div class="lt-topbar__tools" x-show="!(compact && searchOpen)">
                <a href="{{ route('settings') }}" wire:navigate class="md-btn-icon" aria-label="Ajustes">
                    <i class="bi bi-gear" aria-hidden="true"></i>
                </a>
                @auth
                    <livewire:notifications.notification-bell />
                @endauth
                @auth
                    <a href="{{ route('settings') }}" wire:navigate class="lt-avatar" aria-label="Abrir menú de perfil" title="Perfil">@if (filled(auth()->user()->avatar_url))<img src="{{ auth()->user()->avatar_url }}" alt="" class="lt-avatar__photo" width="40" height="40">@else{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}@endif</a>
                @endauth
            </div>
        </header>

        @if(app()->environment('local'))
            <div class="lt-env-banner">LOCAL ENVIRONMENT</div>
        @endif

        <main class="lt-main">
            {{ $slot }}
        </main>

        {{-- Destinos principales. Van siempre en el HTML: por encima de 768px los
             oculta el CSS, no una condición de JavaScript, así que no parpadean
             al cargar ni hace falta `x-cloak`. --}}
        <nav class="lt-bottom" aria-label="Destinos principales">
            <a href="{{ route('home') }}" wire:navigate class="lt-bottom__item {{ request()->routeIs('home') ? 'is-active' : '' }}" @if(request()->routeIs('home')) aria-current="page" @endif>
                <span class="lt-bottom__icon"><i class="bi bi-house" aria-hidden="true"></i></span>
                <span>Inicio</span>
            </a>
            <a href="{{ route('habits') }}" wire:navigate class="lt-bottom__item {{ request()->routeIs('habits*') ? 'is-active' : '' }}" @if(request()->routeIs('habits*')) aria-current="page" @endif>
                <span class="lt-bottom__icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span>
                <span>Hábitos</span>
            </a>
            <a href="{{ route('tasks.list') }}" wire:navigate class="lt-bottom__item {{ request()->routeIs('tasks.*') ? 'is-active' : '' }}" @if(request()->routeIs('tasks.*')) aria-current="page" @endif>
                <span class="lt-bottom__icon"><i class="bi bi-list-task" aria-hidden="true"></i></span>
                <span>Tareas</span>
            </a>
            <a href="{{ route('statistics') }}" wire:navigate class="lt-bottom__item {{ request()->routeIs('statistics*') ? 'is-active' : '' }}" @if(request()->routeIs('statistics*')) aria-current="page" @endif>
                <span class="lt-bottom__icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></span>
                <span>Stats</span>
            </a>
            <button type="button" class="lt-bottom__item" @click="sidebarOpen = true">
                <span class="lt-bottom__icon"><i class="bi bi-grid" aria-hidden="true"></i></span>
                <span>Módulos</span>
            </button>
        </nav>
    </div>

    {{-- Invitacion a instalar. Android expone `beforeinstallprompt` y se puede
         lanzar el dialogo del sistema; iOS no lo expone, asi que solo cabe
         explicar los pasos de Safari. El descarte se recuerda 30 dias. --}}
    <div x-data="ltInstallPrompt()">
        <x-ui.sheet state="open" title="Ten MiSo a mano" class="lt-install">
            <img src="{{ asset('icons/icon-128x128.png') }}" alt="" width="72" height="72" class="lt-install__icon">

            <template x-if="platform === 'android'">
                <p class="md-body-medium">Añádela a tu pantalla de inicio y ábrela como cualquier otra app, sin barra de navegador.</p>
            </template>

            <template x-if="platform === 'ios'">
                <p class="md-body-medium">
                    Toca <i class="bi bi-box-arrow-up" aria-hidden="true"></i> <strong>Compartir</strong>
                    y luego <strong>Añadir a pantalla de inicio</strong>.
                </p>
            </template>

            <x-slot:actions>
                <x-ui.action variant="text" x-on:click="dismiss()">Ahora no</x-ui.action>
                <template x-if="platform === 'android'">
                    <x-ui.action icon="bi-phone" x-on:click="install()">Añadir a la pantalla de inicio</x-ui.action>
                </template>
            </x-slot:actions>
        </x-ui.sheet>
    </div>

    @livewireScriptConfig
</body>
</html>
