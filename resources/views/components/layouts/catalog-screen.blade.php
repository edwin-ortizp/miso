<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Arquetipo · Catálogo' }}</title>
    @include('partials.app-styles')
    @include('partials.app-scripts')
</head>
<body class="md-catalog-screen">
    <main class="md-main-content md-main-content--catalog">
        {{ $slot }}
    </main>
</body>
</html>
