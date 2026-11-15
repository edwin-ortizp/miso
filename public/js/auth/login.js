/* Acceso y registro independientes del bundle autenticado. Mejora progresiva sobre POST HTML. */
(() => {
    const root = document.querySelector('.md-login');
    const form = document.querySelector('[data-login-form]');
    if (!root || !form) return;
    const registering = root.dataset.authMode === 'register';
    const greeting = root.querySelector('[data-login-greeting]');
    let boundaryTimer;
    function updatePeriod() {
        const now = new Date();
        const hour = now.getHours();
        const period = hour >= 5 && hour < 12 ? 'morning' : hour >= 12 && hour < 18 ? 'afternoon' : 'night';
        root.dataset.period = period;
        greeting.textContent = { morning: 'Buenos días', afternoon: 'Buenas tardes', night: 'Buenas noches' }[period];
        const next = new Date(now);
        next.setHours(hour < 5 ? 5 : hour < 12 ? 12 : hour < 18 ? 18 : 29, 0, 0, 0);
        clearTimeout(boundaryTimer);
        boundaryTimer = setTimeout(updatePeriod, next.getTime() - now.getTime() + 50);
    }
    updatePeriod();
    document.addEventListener('visibilitychange', () => { if (!document.hidden) updatePeriod(); });

    const fields = [...form.querySelectorAll('[data-login-field] input')];
    const touched = new Set();
    const password = form.elements.password;
    const confirmation = form.elements.password_confirmation;
    const serverErrors = new Map(fields.filter(input => input.getAttribute('aria-invalid') === 'true')
        .map(input => [input, { value: input.value, message: document.getElementById(`${input.id}-error`).textContent }]));
    // x-ui.field ya asocia su ayuda; añadir el error dinámico sin duplicar atributos.
    fields.forEach(input => {
        const descriptions = new Set((input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
        descriptions.add(`${input.id}-error`);
        input.setAttribute('aria-describedby', [...descriptions].join(' '));
    });
    function validate(input) {
        const container = input.closest('[data-login-field]');
        const field = container.querySelector('.md-field');
        const message = document.getElementById(`${input.id}-error`);
        let error = '';
        if (!(['name', 'email'].includes(input.name) ? input.value.trim() : input.value)) error = {
            name: 'Ingresa tu nombre.', email: 'Ingresa tu correo electrónico.',
            password: registering ? 'Crea una contraseña.' : 'Ingresa tu contraseña.',
            password_confirmation: 'Confirma tu contraseña.',
        }[input.name];
        else if (input.validity.typeMismatch) error = 'Ingresa un correo electrónico válido.';
        else if (registering && ['name', 'email'].includes(input.name) && Array.from(input.value.trim()).length > 255)
            error = input.name === 'name' ? 'Tu nombre puede tener hasta 255 caracteres.' : 'El correo puede tener hasta 255 caracteres.';
        else if (registering && input === password && Array.from(input.value).length < 8) error = 'Usa una contraseña de 8 caracteres o más.';
        else if (input === confirmation && input.value !== password.value) error = 'Las contraseñas no coinciden. Revisa la confirmación.';
        const previous = serverErrors.get(input);
        if (previous && input.value !== previous.value) serverErrors.delete(input);
        else if (!error && previous) error = previous.message;
        message.textContent = error;
        message.hidden = !error;
        field.classList.toggle('md-error', !!error);
        input.setAttribute('aria-invalid', String(!!error));
        return !error;
    }
    fields.forEach(input => {
        input.addEventListener('blur', () => { touched.add(input); validate(input); });
        input.addEventListener('input', () => {
            if (touched.has(input)) validate(input);
            if (input === password && touched.has(confirmation)) validate(confirmation);
        });
    });
    root.querySelectorAll('[data-login-reveal]').forEach(reveal => {
      const target = document.getElementById(reveal.getAttribute('aria-controls'));
      reveal.hidden = false;
      reveal.addEventListener('click', () => {
        const visible = target.type === 'password';
        const start = target.selectionStart;
        const end = target.selectionEnd;
        target.type = visible ? 'text' : 'password';
        reveal.setAttribute('aria-label', `${visible ? 'Ocultar' : 'Mostrar'} ${reveal.dataset.passwordName || 'contraseña'}`);
        reveal.title = reveal.getAttribute('aria-label');
        reveal.setAttribute('aria-pressed', String(visible));
        reveal.querySelector('i').className = `bi ${visible ? 'bi-eye-slash' : 'bi-eye'} md-btn__icon`;
        target.focus({ preventScroll: true });
        if (start !== null) target.setSelectionRange(start, end);
      });
    });
    const submit = root.querySelector('[data-login-submit]');
    const label = root.querySelector('[data-login-submit-label]');
    const status = root.querySelector('[data-login-status]');
    let submitting = false;
    form.noValidate = true;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        const invalid = fields.filter(input => { touched.add(input); return !validate(input); });
        if (invalid.length) { event.preventDefault(); invalid[0].focus(); return; }
        submitting = true;
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        label.textContent = registering ? 'Creando tu cuenta…' : 'Iniciando sesión…';
        status.textContent = label.textContent;
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
        label.textContent = registering ? 'Crear mi cuenta' : 'Iniciar sesión';
        status.textContent = '';
        updatePeriod();
    });
    // No abrir el teclado móvil automáticamente; solo dirigir el foco tras un error.
    const serverError = root.querySelector('[data-login-server-error]');
    const invalidField = form.querySelector('[aria-invalid="true"]');
    if (serverError) serverError.focus();
    else if (invalidField) invalidField.focus();
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        root.classList.add('md-login--arriving');
        setTimeout(() => root.classList.remove('md-login--arriving'), 5000);
    }
})();
