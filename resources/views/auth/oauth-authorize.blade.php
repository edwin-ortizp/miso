<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conectar aplicación - MiSo</title>
    @include('partials.app-styles')
</head>
<body class="md-auth-shell">
    <div class="md-card-elevated md-auth-card">
        <header class="md-auth-head">
            <span class="md-auth-head__icon" aria-hidden="true"><i class="bi bi-plug"></i></span>
            <h1 class="md-headline-medium">Conectar aplicación</h1>
            <p class="md-body-medium">
                <strong>{{ $client->name }}</strong> quiere acceder a tu cuenta de MiSo
                como <strong>{{ $user->name }}</strong>.
            </p>
        </header>

        <section class="md-auth-panel">
            <h2 class="md-title-small">Qué podrá hacer</h2>
            <ul class="md-body-medium">
                <li>Consultar tus hábitos, tareas, salud, relaciones, planes y vehículos.</li>
                <li>Crear y actualizar registros en tu nombre: completar hábitos y tareas, anotar hidratación, ejercicio, ánimo y energía.</li>
            </ul>
            <p class="md-body-small">Solo sobre tus datos. Puedes revocar el acceso cuando quieras desde Ajustes.</p>
        </section>

        {{--
            Los clientes MCP se registran solos (RFC 7591): el nombre lo eligen ellos y
            nadie ha verificado su identidad. Merece un aviso explícito, no letra pequeña.
        --}}
        <p class="md-auth-warning md-body-small">
            <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
            <span>
                Esta aplicación se registró por sí misma y su identidad no está verificada.
                Continúa solo si fuiste tú quien inició la conexión.
            </span>
        </p>

        <div class="md-auth-actions">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="md-btn-text">Cancelar</button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="md-btn-filled">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    <span>Autorizar</span>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
