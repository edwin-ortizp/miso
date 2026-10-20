<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - MiSo</title>
    @include('partials.app-styles')
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--md-sys-color-surface);">
    <div style="max-width: 420px; width: 100%; padding: 0 16px;">
        <div class="md-card-elevated" style="padding: 32px;">
            <div class="text-center mb-4">
                <div class="md-card-icon md-card-icon--primary mx-auto mb-3" style="width: 64px; height: 64px; border-radius: var(--md-sys-shape-corner-large); font-size: 2rem;">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <h1 class="md-headline-medium" style="color: var(--md-sys-color-on-surface);">MiSo</h1>
                <p class="md-body-medium" style="color: var(--md-sys-color-on-surface-variant);">Tu socio de crecimiento personal. Crea tu cuenta.</p>
            </div>

            @if ($errors->any())
                <div class="md-card-filled mb-3" style="background: var(--md-sys-color-error-container); color: var(--md-sys-color-on-error-container); padding: 12px 16px;">
                    <ul class="mb-0 ps-3" style="font-size: 0.875rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf
                <div class="d-flex flex-column gap-3">
                    <div class="md-text-field">
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus placeholder=" " id="reg-name">
                        <label for="reg-name">Nombre</label>
                    </div>
                    <div class="md-text-field">
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder=" " id="reg-email">
                        <label for="reg-email">Correo electrónico</label>
                    </div>
                    <div class="md-text-field">
                        <input type="password" name="password" required placeholder=" " id="reg-pw">
                        <label for="reg-pw">Contraseña</label>
                    </div>
                    <div class="md-text-field">
                        <input type="password" name="password_confirmation" required placeholder=" " id="reg-pw-conf">
                        <label for="reg-pw-conf">Confirmar contraseña</label>
                    </div>
                    <button type="submit" class="md-btn-filled w-100" style="height: 48px; font-size: 1rem;">
                        <i class="bi bi-person-plus"></i> Registrarse
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="md-btn-text" style="font-size: 0.875rem;">
                    ¿Ya tienes cuenta? Inicia sesión
                </a>
            </div>
        </div>
    </div>
</body>
</html>
