<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sin conexión · MiSo</title>
    <meta name="theme-color" content="#2B5BB5">
    @include('partials.app-styles')
</head>
<body class="lt-frame">
    <main class="lt-main lt-offline-page">
        <div class="lt-offline-page__body">
            <p class="lt-offline-page__mark"><i class="bi bi-wifi-off" aria-hidden="true"></i></p>
            <h1 class="md-headline-small">Sin conexión</h1>
            <p class="md-body-medium">Esta pantalla no se ha abierto antes, así que no hay una copia guardada.</p>
            <p class="md-body-medium">Las pantallas que ya visitaste siguen disponibles.</p>
            <a href="/" class="md-btn-filled lt-offline-page__action">
                <i class="bi bi-house" aria-hidden="true"></i> Ir al inicio
            </a>
        </div>
    </main>
</body>
</html>
