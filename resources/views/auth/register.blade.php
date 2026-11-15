<x-auth-shell :register="true">
    <form method="POST" action="{{ route('register') }}" class="md-login__form" data-login-form>
        @csrf
        <div data-login-field="name">
            <x-ui.field name="name" id="reg-name" label="Nombre" :value="is_string(old('name')) ? old('name') : ''" required autocomplete="name"
                :aria-describedby="$errors->has('name') ? null : 'reg-name-error'" />
            @unless ($errors->has('name'))
                <p id="reg-name-error" class="md-login__field-error" aria-live="polite" hidden></p>
            @endunless
        </div>
        <div data-login-field="email">
            <x-ui.field name="email" id="reg-email" label="Correo electrónico" type="email" :value="is_string(old('email')) ? old('email') : ''"
                required autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false"
                :aria-describedby="$errors->has('email') ? null : 'reg-email-error'" />
            @unless ($errors->has('email'))
                <p id="reg-email-error" class="md-login__field-error" aria-live="polite" hidden></p>
            @endunless
        </div>
        <div class="md-login__password" data-login-field="password">
            <x-ui.field name="password" id="reg-pw" label="Contraseña" type="password" required
                autocomplete="new-password" minlength="8" help="Usa 8 caracteres o más." />
            <x-ui.icon-action icon="bi-eye" label="Mostrar contraseña" class="md-login__reveal"
                aria-controls="reg-pw" aria-pressed="false" data-login-reveal hidden />
            @unless ($errors->has('password'))
                <p id="reg-pw-error" class="md-login__field-error" aria-live="polite" hidden></p>
            @endunless
        </div>
        <div class="md-login__password" data-login-field="password_confirmation">
            <x-ui.field name="password_confirmation" id="reg-pw-conf" label="Confirmar contraseña" type="password" required
                autocomplete="new-password" :aria-describedby="$errors->has('password_confirmation') ? null : 'reg-pw-conf-error'" />
            <x-ui.icon-action icon="bi-eye" label="Mostrar confirmación de contraseña" class="md-login__reveal"
                aria-controls="reg-pw-conf" aria-pressed="false" data-login-reveal data-password-name="confirmación de contraseña" hidden />
            @unless ($errors->has('password_confirmation'))
                <p id="reg-pw-conf-error" class="md-login__field-error" aria-live="polite" hidden></p>
            @endunless
        </div>
        <x-ui.action type="submit" size="lg" class="md-login__submit" data-login-submit>
            <span data-login-submit-label>Crear mi cuenta</span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </x-ui.action>
        <p class="md-login__status" role="status" data-login-status></p>
    </form>
    <div class="md-login__register">
        <span>¿Ya tienes cuenta?</span>
        <x-ui.action variant="text" :href="route('login')">Inicia sesión</x-ui.action>
    </div>
</x-auth-shell>
