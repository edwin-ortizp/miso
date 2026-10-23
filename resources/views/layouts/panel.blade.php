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
<body class="lt-frame wall-frame lt-frame--navigation-right" x-data="ltNavigation(true)" :class="{ 'is-rail': sidebarRail }">
    <x-app-navigation side="right" toggle />
    <div class="lt-offline" x-data="ltConnection()" :class="{ 'is-visible': offline }" role="status" aria-live="polite">Sin conexión — se muestran los datos guardados</div>
    {{ $slot }}
    @livewireScriptConfig
</body>
</html>
