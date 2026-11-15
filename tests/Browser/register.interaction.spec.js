import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdir } from 'node:fs/promises';

const captures = 'storage/framework/testing/register-captures';
const open = async (page, hour = 9) => {
    await page.clock.install({ time: new Date(`2026-11-15T${hour.toString().padStart(2, '0')}:00:00-05:00`) });
    await page.goto('/register');
    await expect(page.getByRole('button', { name: 'Mostrar contraseña', exact: true })).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
};
const fillValid = async page => {
    await page.locator('#reg-name').fill('Ana');
    await page.locator('#reg-email').fill('new@example.test');
    await page.locator('#reg-pw').fill('safe-password');
    await page.locator('#reg-pw-conf').fill('safe-password');
};

for (const width of [320, 390, 768, 1440]) {
    for (const colorScheme of ['light', 'dark']) {
        for (const [hour, period] of [[9, 'morning'], [15, 'afternoon'], [22, 'night']]) {
            test(`registro ${width} ${colorScheme} ${period}`, async ({ page }) => {
                await page.setViewportSize({ width, height: 900 });
                await page.emulateMedia({ colorScheme, reducedMotion: 'reduce' });
                await open(page, hour);
                await expect(page.locator('body')).toHaveAttribute('data-period', period);
                await expect(page.getByRole('heading', { name: 'Crea tu cuenta' })).toBeVisible();
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
                await page.getByRole('button', { name: 'Crear mi cuenta' }).scrollIntoViewIfNeeded();
                await expect(page.getByRole('button', { name: 'Crear mi cuenta' })).toBeInViewport();
                await mkdir(captures, { recursive: true });
                await page.screenshot({ path: `${captures}/${width}-${colorScheme}-${period}.png`, fullPage: true });
                expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
            });
        }
    }
}

test('validaciones oportunas, contraseña corta y confirmación cruzada', async ({ page }) => {
    await open(page);
    await expect(page.locator('#reg-name-error')).toBeHidden();
    await page.getByRole('button', { name: 'Crear mi cuenta' }).click();
    await expect(page.locator('#reg-name')).toBeFocused();
    await expect(page.locator('#reg-name-error')).toHaveText('Ingresa tu nombre.');
    await page.locator('#reg-name').fill('Ana');
    await page.locator('#reg-email').fill('invalid');
    await page.locator('#reg-pw').fill('short');
    await page.locator('#reg-pw-conf').fill('different');
    await expect(page.locator('#reg-email-error')).toHaveText('Ingresa un correo electrónico válido.');
    await expect(page.locator('#reg-pw-error')).toHaveText('Usa una contraseña de 8 caracteres o más.');
    await expect(page.locator('#reg-pw-conf-error')).toHaveText('Las contraseñas no coinciden. Revisa la confirmación.');
    await page.locator('#reg-email').fill('new@example.test');
    await page.locator('#reg-pw').fill('safe-password');
    await page.locator('#reg-pw-conf').fill('safe-password');
    await expect(page.locator('#reg-pw-conf-error')).toBeHidden();
    await page.locator('#reg-pw').fill('another-password');
    await expect(page.locator('#reg-pw-conf-error')).toBeVisible();
    await page.locator('#reg-pw').fill('safe-password');
    await expect(page.locator('#reg-pw-conf-error')).toBeHidden();
});

test('contraseñas visibles independientes, autofill y foco', async ({ page }) => {
    await open(page);
    await fillValid(page);
    await page.getByRole('button', { name: 'Mostrar contraseña', exact: true }).click();
    await expect(page.locator('#reg-pw')).toHaveAttribute('type', 'text');
    await expect(page.locator('#reg-pw')).toBeFocused();
    await expect(page.locator('#reg-pw-conf')).toHaveAttribute('type', 'password');
    await page.getByRole('button', { name: 'Mostrar confirmación de contraseña' }).click();
    await expect(page.locator('#reg-pw-conf')).toHaveAttribute('type', 'text');
    await expect(page.locator('#reg-pw-conf')).toBeFocused();
    await page.getByRole('button', { name: 'Ocultar contraseña', exact: true }).click();
    await expect(page.locator('#reg-pw')).toHaveAttribute('type', 'password');
    await expect(page.locator('#reg-pw')).toHaveAttribute('autocomplete', 'new-password');
    await expect(page.locator('#reg-pw-conf')).toHaveAttribute('autocomplete', 'new-password');
    await expect(page.locator('#reg-pw')).toHaveAttribute('aria-describedby', 'reg-pw-help reg-pw-error');
});

test('nombre largo permanece completo y contraseña Unicode cuenta caracteres', async ({ page }) => {
    await open(page);
    await page.locator('#reg-name').fill('á'.repeat(256));
    await page.locator('#reg-email').focus();
    await expect(page.locator('#reg-name-error')).toHaveText('Tu nombre puede tener hasta 255 caracteres.');
    await expect(page.locator('#reg-name')).toHaveValue('á'.repeat(256));
    await page.locator('#reg-pw').fill('🌱'.repeat(4));
    await page.locator('#reg-pw-conf').focus();
    await expect(page.locator('#reg-pw-error')).toHaveText('Usa una contraseña de 8 caracteres o más.');
    await page.locator('#reg-pw').fill('🌱'.repeat(8));
    await expect(page.locator('#reg-pw-error')).toBeHidden();
});

test('estado de espera, envío único y recuperación del historial', async ({ page }) => {
    await open(page);
    await fillValid(page);
    const state = await page.evaluate(() => {
        const form = document.querySelector('form');
        let accepted = 0;
        window.stopRegister = event => { if (!event.defaultPrevented) accepted++; event.preventDefault(); };
        form.addEventListener('submit', window.stopRegister);
        form.requestSubmit(); form.requestSubmit();
        return { accepted, disabled: document.querySelector('[data-login-submit]').disabled,
            label: document.querySelector('[data-login-submit-label]').textContent };
    });
    expect(state).toEqual({ accepted: 1, disabled: true, label: 'Creando tu cuenta…' });
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
    await expect(page.locator('[data-login-submit]')).toBeEnabled();
    await expect(page.locator('[data-login-submit-label]')).toHaveText('Crear mi cuenta');
    await page.evaluate(() => document.querySelector('form').removeEventListener('submit', window.stopRegister));
    await page.route('**/register', route => route.request().method() === 'POST'
        ? route.fulfill({ contentType: 'text/html', body: '<h1>Bienvenida de prueba</h1>' }) : route.continue());
    await page.getByRole('button', { name: 'Crear mi cuenta' }).click();
    await expect(page.getByRole('heading', { name: 'Bienvenida de prueba' })).toBeVisible();
    await page.goBack();
    await expect(page.locator('[data-login-submit]')).toBeEnabled();
});

test('franja compartida, movimiento reducido y navegación al login', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.clock.install({ time: new Date('2026-11-15T17:59:59-05:00') });
    await page.goto('/register');
    await fillValid(page);
    await page.clock.fastForward(1500);
    await expect(page.locator('body')).toHaveAttribute('data-period', 'night');
    await expect(page.locator('#reg-name')).toHaveValue('Ana');
    expect(await page.locator('.md-login__clouds').evaluate(el => getComputedStyle(el).animationName)).toBe('none');
    await page.getByRole('link', { name: 'Inicia sesión', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Bienvenido a MiSo' })).toBeVisible();
});

test('teclado y viewport bajo permiten completar el registro', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 420 });
    await open(page);
    await page.keyboard.press('Tab');
    await expect(page.locator('.md-login__brand')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.locator('#reg-name')).toBeFocused();
    expect(await page.locator('#reg-name').evaluate(el => getComputedStyle(el).outlineStyle)).not.toBe('none');
    await page.locator('#reg-pw-conf').focus();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Mostrar confirmación de contraseña' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Crear mi cuenta' })).toBeFocused();
    await expect(page.getByRole('button', { name: 'Crear mi cuenta' })).toBeInViewport();
    await page.setViewportSize({ width: 720, height: 450 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

for (const width of [320, 390, 768, 1440]) {
    for (const colorScheme of ['light', 'dark']) {
        test(`errores ${width} ${colorScheme}`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme, reducedMotion: 'reduce' });
            await open(page, 22);
            // Email requerido evita consultas a datos reales; prueba errores devueltos por servidor.
            await page.locator('#reg-name').fill('Ana');
            await page.locator('#reg-pw').fill('short');
            await page.locator('#reg-pw-conf').fill('different');
            await page.evaluate(() => document.querySelector('form').submit());
            await expect(page.locator('#reg-email-error')).toHaveText('Ingresa tu correo electrónico.');
            await expect(page.locator('#reg-email')).toBeFocused();
            await expect(page.locator('#reg-name')).toHaveValue('Ana');
            await expect(page.locator('#reg-pw')).toHaveValue('');
            await expect(page.locator('#reg-pw-conf-error')).toBeVisible();
            await page.screenshot({ path: `${captures}/${width}-${colorScheme}-errors.png`, fullPage: true });
            expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
            await page.locator('#reg-email').fill('new@example.test');
            await page.locator('#reg-pw').fill('safe-password');
            await page.locator('#reg-pw-conf').fill('safe-password');
            await page.locator('#reg-name').focus();
            await expect(page.locator('#reg-pw-conf-error')).toBeHidden();
        });
    }
}

test('sin JavaScript conserva ayuda, formulario y POST nativo', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:8034/register');
    await expect(page.locator('[data-login-reveal]')).toHaveCount(2);
    await expect(page.locator('[data-login-reveal]').first()).toBeHidden();
    await expect(page.getByText('Usa 8 caracteres o más.')).toBeVisible();
    expect(await page.locator('form').getAttribute('novalidate')).toBeNull();
    await fillValid(page);
    await page.route('**/register', route => route.request().method() === 'POST'
        ? route.fulfill({ contentType: 'text/html', body: '<h1>POST recibido</h1>' }) : route.continue());
    await page.getByRole('button', { name: 'Crear mi cuenta' }).click();
    await expect(page.getByRole('heading', { name: 'POST recibido' })).toBeVisible();
    await context.close();
});

test('correo registrado conserva su error hasta que cambie el valor', async ({ page }) => {
    await page.route('**/register', async route => {
        const response = await route.fetch();
        let html = await response.text();
        html = html.replace(/(<input type="email"[\s\S]*?value=")"/, '$1used@example.test"')
            .replace('id="reg-email"', 'id="reg-email" aria-invalid="true"')
            .replace(/<p id="reg-email-error"[^>]*><\/p>/,
                '<p id="reg-email-error" class="md-login__field-error" role="alert">Este correo ya está registrado. Inicia sesión con tu cuenta.</p>');
        await route.fulfill({ response, body: html });
    });
    await open(page);
    await expect(page.locator('#reg-email')).toHaveValue('used@example.test');
    await page.locator('#reg-name').fill('Ana');
    await page.locator('#reg-email').focus();
    await page.locator('#reg-pw').fill('safe-password');
    await page.locator('#reg-pw-conf').fill('safe-password');
    await expect(page.locator('#reg-email-error')).toHaveText('Este correo ya está registrado. Inicia sesión con tu cuenta.');
    await page.getByRole('button', { name: 'Crear mi cuenta' }).click();
    await expect(page.locator('#reg-email')).toBeFocused();
    await expect(page.locator('[data-login-submit]')).toBeEnabled();
    await page.locator('#reg-email').fill('different@example.test');
    await expect(page.locator('#reg-email-error')).toBeHidden();
});
