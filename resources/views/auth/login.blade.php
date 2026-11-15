<x-auth-shell>
    @if ($errors->has('credentials'))
        <div class="md-login__alert" role="alert" tabindex="-1" data-login-server-error>
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <span>{{ $errors->first('credentials') }}</span>
        </div>
    @endif
    <form method="POST" action="{{ route('login') }}" class="md-login__form" data-login-form>
        @csrf
        <div data-login-field="email">
            <x-ui.field name="email" id="login-email" label="Correo electrónico" type="email"
                :value="old('email')" required autocomplete="username" inputmode="email"
                autocapitalize="none" spellcheck="false" :aria-describedby="$errors->has('email') ? null : 'login-email-error'" />
            @unless ($errors->has('email'))
                <p id="login-email-error" class="md-login__field-error" aria-live="polite" hidden></p>
            @endunless
        </div>
        <div class="md-login__password" data-login-field="password">
            <x-ui.field name="password" id="login-pw" label="Contraseña" type="password"
                required autocomplete="current-password" :aria-describedby="$errors->has('password') ? null : 'login-pw-error'" />
            <x-ui.icon-action icon="bi-eye" label="Mostrar contraseña" class="md-login__reveal"
                aria-controls="login-pw" aria-pressed="false" data-login-reveal hidden />
            @unless ($errors->has('password'))
                <p id="login-pw-error" class="md-login__field-error" aria-live="polite" hidden></p>
            @endunless
        </div>
        <label class="md-checkbox md-login__remember">
            <input type="checkbox" name="remember" @checked(old('remember'))> Recordarme
        </label>
        <x-ui.action type="submit" size="lg" class="md-login__submit" data-login-submit>
            <span data-login-submit-label>Iniciar sesión</span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </x-ui.action>
        <p class="md-login__status" role="status" data-login-status></p>
    </form>
    <div class="md-login__register">
        <span>¿No tienes cuenta?</span>
        <x-ui.action variant="text" :href="route('register')">Regístrate</x-ui.action>
    </div>
</x-auth-shell>
