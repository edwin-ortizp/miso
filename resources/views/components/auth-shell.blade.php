@props(['register' => false])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
    <title>{{ $register ? 'Crear cuenta' : 'Iniciar sesión' }} · MiSo</title>
    @include('partials.app-styles')
    <script>window.MisoNative?.postMessage(JSON.stringify({v:1,type:'session',account:null}));</script>
    <script src="{{ asset('js/auth/login.js') }}?v={{ filemtime(public_path('js/auth/login.js')) }}" defer></script>
</head>
<body class="md-login" data-auth-mode="{{ $register ? 'register' : 'login' }}">
    <main class="md-login__layout">
        <section class="md-login__refuge" aria-label="Tu espacio en MiSo">
            <a class="md-login__brand" href="{{ route('login') }}" aria-label="MiSo, inicio de sesión">
                <i class="bi bi-heart-pulse" aria-hidden="true"></i> MiSo
                <span>tu socio de crecimiento personal</span>
            </a>
            <div class="md-login__welcome">
                <p class="md-login__greeting" data-login-greeting>Qué bueno verte</p>
                <h1>{{ $register ? 'Tu próximo pequeño paso' : 'Tu espacio para' }}<br>{{ $register ? 'empieza aquí' : 'avanzar a tu ritmo' }}<span>.</span></h1>
                <p>{{ $register ? 'Hábitos, objetivos y un espacio para ti.' : 'Un hábito, una idea, un pequeño paso.' }}<br>{{ $register ? 'Empieza con lo que más te importa.' : 'Aquí hay espacio para todo lo que quieres cuidar.' }}</p>
            </div>
            @include('auth.partials.refuge')
            <p class="md-login__scene-note"><span aria-hidden="true">✦</span> Cada día, a tu manera.</p>
        </section>
        <section class="md-login__access" aria-labelledby="login-title">
            <div class="md-login__form-wrap">
                <div class="md-login__form-mark" aria-hidden="true"><i class="bi {{ $register ? 'bi-person-plus' : 'bi-door-open' }}"></i></div>
                <h2 id="login-title">{{ $register ? 'Crea tu cuenta' : 'Bienvenido a MiSo' }}</h2>
                <p class="md-login__intro">{{ $register ? 'Después te acompañamos a preparar tu espacio.' : 'Entra y encuentra tu próximo pequeño paso.' }}</p>
                {{ $slot }}
                <p class="md-login__footnote"><i class="bi bi-flower1" aria-hidden="true"></i> Crecer también es darte tu tiempo.</p>
            </div>
        </section>
    </main>
</body>
</html>
