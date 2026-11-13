<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'Tu comienzo en MiSo' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-96x96.png') }}">
    @include('partials.app-styles')
    @include('partials.app-scripts')
    @livewireStyles
</head>
<body class="lt-frame experience-welcome-page">
    <header class="experience-topbar">
        <a class="experience-brand" href="{{ route('onboarding') }}" aria-label="MiSo, tu comienzo">
            <span class="experience-brand__mark" aria-hidden="true"><i class="bi bi-activity"></i></span>
            <span>MiSo <small>Tu socio de crecimiento personal</small></span>
        </a>
        <span class="experience-save-note"><i class="bi bi-cloud-check" aria-hidden="true"></i> A tu ritmo. Guardamos cada paso.</span>
        <x-account-menu />
    </header>
    <main class="experience-main">{{ $slot }}</main>
    @include('partials.discard-guard')
    @livewireScriptConfig
</body>
</html>
