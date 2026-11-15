import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdir } from 'node:fs/promises';

const captures = 'storage/framework/testing/login-captures';
const open = async (page, hour = 9) => {
    await page.clock.install({ time: new Date(`2026-11-15T${String(hour).padStart(2, '0')}:00:00-05:00`) });
    await page.goto('/login');
    await expect(page.locator('[data-login-reveal]')).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
};

for (const width of [320, 390, 768, 1440]) {
    for (const colorScheme of ['light', 'dark']) {
        for (const [hour, period] of [[9, 'morning'], [15, 'afternoon'], [22, 'night']]) {
            test(`refugio ${width} ${colorScheme} ${period}`, async ({ page }) => {
                await page.setViewportSize({ width, height: 900 });
                await page.emulateMedia({ colorScheme, reducedMotion: 'reduce' });
                await open(page, hour);
                await expect(page.locator('body')).toHaveAttribute('data-period', period);
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
                await expect(page.getByRole('button', { name: 'Iniciar sesión', exact: true })).toBeVisible();
                await mkdir(captures, { recursive: true });
                await page.screenshot({ path: `${captures}/${width}-${colorScheme}-${period}.png`, fullPage: true });
                const violations = (await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations;
                expect(violations).toEqual([]);
            });
        }
    }
}

test('validación al salir, corrección, foco y mostrar contraseña', async ({ page }) => {
    await open(page);
    const email = page.locator('#login-email');
    const password = page.locator('#login-pw');
    await expect(page.locator('#login-email-error')).toBeHidden();
    await email.fill('incorrecto');
    await expect(page.locator('#login-email-error')).toBeHidden();
    await password.focus();
    await expect(page.locator('#login-email-error')).toHaveText('Ingresa un correo electrónico válido.');
    await email.fill('persona@example.test');
    await expect(page.locator('#login-email-error')).toBeHidden();
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
    await expect(password).toBeFocused();
    await expect(page.locator('#login-pw-error')).toHaveText('Ingresa tu contraseña.');
    await password.fill('Una contraseña');
    await page.getByRole('button', { name: 'Mostrar contraseña' }).click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(password).toBeFocused();
    await page.getByRole('button', { name: 'Ocultar contraseña' }).click();
    await expect(password).toHaveAttribute('type', 'password');
    await expect(password).toHaveValue('Una contraseña');
    await expect(password).toBeFocused();
});

test('envío único, espera y recuperación desde historial', async ({ page }) => {
    await open(page);
    await page.locator('#login-email').fill('persona@example.test');
    await page.locator('#login-pw').fill('una-clave');
    // Observar el estado de envío antes de que el navegador abandone el documento.
    const state = await page.evaluate(() => {
        const form = document.querySelector('form');
        let accepted = 0;
        window.loginTestPreventSubmit = event => { if (!event.defaultPrevented) accepted++; event.preventDefault(); };
        form.addEventListener('submit', window.loginTestPreventSubmit);
        form.requestSubmit();
        form.requestSubmit();
        return { accepted, disabled: document.querySelector('[data-login-submit]').disabled,
            label: document.querySelector('[data-login-submit-label]').textContent };
    });
    expect(state).toEqual({ accepted: 1, disabled: true, label: 'Iniciando sesión…' });
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
    await expect(page.locator('[data-login-submit]')).toBeEnabled();
    await expect(page.locator('[data-login-submit-label]')).toHaveText('Iniciar sesión');
    await page.evaluate(() => document.querySelector('form').removeEventListener('submit', window.loginTestPreventSubmit));
    let posts = 0;
    await page.route('**/login', async route => {
        if (route.request().method() !== 'POST') return route.continue();
        posts++;
        expect(route.request().postData()).toContain('email=persona%40example.test');
        await route.fulfill({ status: 200, contentType: 'text/html', body: '<h1>Destino de prueba</h1>' });
    });
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Destino de prueba' })).toBeVisible();
    expect(posts).toBe(1);
    await page.goBack();
    await expect(page.locator('[data-login-submit]')).toBeEnabled();
    await expect(page.locator('[data-login-submit-label]')).toHaveText('Iniciar sesión');
});
test('cambia de franja y actualiza al volver sin perder el formulario', async ({ page }) => {
    await page.clock.install({ time: new Date('2026-11-15T11:59:59-05:00') });
    await page.goto('/login');
    await page.locator('#login-email').fill('persona@example.test');
    await page.locator('#login-pw').fill('conservar');
    await page.clock.fastForward(1500);
    await expect(page.locator('body')).toHaveAttribute('data-period', 'afternoon');
    await page.clock.setSystemTime(new Date('2026-11-15T22:00:00-05:00'));
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await expect(page.locator('[data-login-greeting]')).toHaveText('Buenas noches');
    await expect(page.locator('#login-email')).toHaveValue('persona@example.test');
    await expect(page.locator('#login-pw')).toHaveValue('conservar');
});

for (const [time, expected] of [['04:59:59', 'night'], ['05:00:00', 'morning'], ['11:59:59', 'morning'], ['12:00:00', 'afternoon'], ['17:59:59', 'afternoon'], ['18:00:00', 'night']]) {
    test(`límite ${time}`, async ({ page }) => {
        await page.clock.install({ time: new Date(`2026-11-15T${time}-05:00`) });
        await page.goto('/login');
        await expect(page.locator('body')).toHaveAttribute('data-period', expected);
    });
}

test('movimiento limitado a cinco segundos y preferencia reducida', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await open(page);
    await expect(page.locator('body')).toHaveClass(/md-login--arriving/);
    await page.clock.fastForward(5100);
    await expect(page.locator('body')).not.toHaveClass(/md-login--arriving/);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.reload();
    await expect(page.locator('body')).not.toHaveClass(/md-login--arriving/);
    expect(await page.locator('.md-login__clouds').evaluate(el => getComputedStyle(el).animationName)).toBe('none');
});

test('teclado, foco visible, viewport bajo y zoom equivalente al 200 %', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 420 });
    await open(page);
    await page.keyboard.press('Tab');
    await expect(page.locator('.md-login__brand')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.locator('#login-email')).toBeFocused();
    expect(await page.locator('#login-email').evaluate(el => getComputedStyle(el).outlineStyle)).not.toBe('none');
    await page.locator('#login-pw').focus();
    await page.keyboard.press('Tab');
    await expect(page.locator('[data-login-reveal]')).toBeFocused();
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).scrollIntoViewIfNeeded();
    await expect(page.locator('[data-login-submit]')).toBeInViewport();
    // 1440px al 200 % tiene 720px CSS disponibles.
    await page.setViewportSize({ width: 720, height: 450 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.locator('[data-login-submit]').scrollIntoViewIfNeeded();
    await expect(page.locator('[data-login-submit]')).toBeInViewport();
});

test('sin JavaScript mantiene HTML POST, CSRF y validación nativa', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:8034/login');
    await expect(page.locator('[data-login-reveal]')).toBeHidden();
    await expect(page.locator('input[name="_token"]')).toHaveAttribute('value', /.+/);
    await expect(page.locator('form')).toHaveAttribute('method', 'POST');
    expect(await page.locator('form').getAttribute('novalidate')).toBeNull();
    await page.locator('#login-email').fill('persona@example.test');
    await page.locator('#login-pw').fill('una-clave');
    await page.route('**/login', route => route.request().method() === 'POST'
        ? route.fulfill({ contentType: 'text/html', body: '<h1>POST recibido</h1>' }) : route.continue());
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'POST recibido' })).toBeVisible();
    await context.close();
});

for (const width of [320, 390, 768, 1440]) {
    for (const colorScheme of ['light', 'dark']) {
        test(`errores del servidor ${width} ${colorScheme}`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme, reducedMotion: 'reduce' });
            await open(page, 22);
            await page.locator('#login-email').fill('incorrecto');
            // Omite la mejora JS deliberadamente para probar la validación del servidor.
            await page.evaluate(() => document.querySelector('form').submit());
            await expect(page.locator('#login-email-error')).toHaveText('Ingresa un correo electrónico válido.');
            await expect(page.locator('#login-pw-error')).toHaveText('Ingresa tu contraseña.');
            await expect(page.locator('#login-email')).toBeFocused();
            await expect(page.locator('#login-email')).toHaveAttribute('aria-invalid', 'true');
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await mkdir(captures, { recursive: true });
            await page.screenshot({ path: `${captures}/${width}-${colorScheme}-errors.png`, fullPage: true });
            expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
            await page.locator('#login-email').fill('persona@example.test');
            await page.locator('#login-pw').fill('corregida');
            await page.locator('#login-email').focus();
            await expect(page.locator('#login-email-error')).toBeHidden();
            await expect(page.locator('#login-pw-error')).toBeHidden();
        });
    }
}
