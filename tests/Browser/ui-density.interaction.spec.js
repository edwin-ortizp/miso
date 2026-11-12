import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

let server;
test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'ui-density-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8047', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', VAPID_PUBLIC_KEY: '', APP_CONFIG_CACHE: path.join(directory, 'unused-ui-density-config.php') };
    execFileSync('php', ['tests/Browser/support/ui-density-fixture.php'], { env, encoding: 'utf8' }).trim();
    server = spawn('php', ['-S', '127.0.0.1:8047', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch('http://127.0.0.1:8047/login')).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Testing server did not start');
});
test.afterAll(() => server?.kill());
test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico', { exact: true }).fill('density@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
});



for (const width of [375, 768, 1280, 1680]) {
    test(`superficies compactas y accesibles ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 1000 });
        for (const [name, url, region] of [
            ['pomodoro', '/pomodoro', '.pomodoro-stage'],
            ['profile', '/settings', '.settings-content'],
            ['body', '/settings/body', '.settings-content'],
            ['notifications', '/settings/notifications', '.settings-content'],
        ]) {
            await page.goto(url);
            await expect(page.locator(region)).toBeVisible();
            await page.mouse.move(width - 5, 8);
            if (width >= 768) {
                await expect.poll(() => page.locator('.lt-sidebar').evaluate(el => Math.round(el.getBoundingClientRect().width))).toBe(width >= 1024 ? 282 : 92);
            }
            await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            const a11y = await new AxeBuilder({ page }).include(region).withTags(['wcag2a', 'wcag2aa']).analyze();
            expect(a11y.violations).toEqual([]);
            if (name === 'pomodoro') {
                await expect(page.locator('.pomodoro-page .md-metric')).toHaveCount(0);
                await expect(page.locator('.pomodoro-daily-goal')).toHaveCount(1);
                const timer = await page.locator('.pomodoro-timer-card').boundingBox();
                const goal = await page.locator('.pomodoro-daily-goal').boundingBox();
                const table = await page.locator('#pomodoro-sessions').boundingBox();
                if (width < 1200) {
                    expect(goal.y).toBeGreaterThan(timer.y);
                    expect(goal.y).toBeLessThan(table.y);
                } else {
                    expect(timer.height).toBeLessThan(450);
                    expect(await page.locator('.focus-companion__art').evaluate(el => el.getBoundingClientRect().width)).toBeGreaterThanOrEqual(140);
                }
            } else {
                expect(await page.locator('.settings-nav__link').first().evaluate(el => el.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
            }
            if (name === 'notifications') {
                await expect(page.getByRole('switch', { name: 'Mañana 8–10', exact: true })).toBeAttached();
                await expect(page.getByRole('button', { name: 'Guardar preferencias', exact: true })).toHaveCount(1);
            }
            await page.screenshot({ path: `storage/framework/testing/ui-density-${name}-${width}.png`, fullPage: true });
        }
    });
}

test('ciclo local y ocultación del compañero conservan controles', async ({ page }) => {
    await page.goto('/pomodoro');
    const toggle = page.locator('.pomodoro-controls button').first();
    await toggle.click();
    await expect(toggle).toHaveText('Pausar');
    await toggle.click();
    await expect(toggle).toHaveText('Iniciar');
    await page.getByRole('button', { name: 'Ocultar compañero', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Mostrar compañero', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Mostrar compañero', exact: true }).click();
    await expect(page.locator('.focus-companion__art')).toBeVisible();
    await page.getByRole('button', { name: 'Descanso corto', exact: true }).click();
    await expect(page.locator('.pomodoro-dial__mode')).toHaveText('Descanso');
});

test('preferencias se guardan juntas y muestran la confirmación al lado', async ({ page }) => {
    await page.goto('/settings/notifications');
    const morning = page.getByRole('switch', { name: 'Mañana 8–10', exact: true });
    await morning.focus();
    await page.keyboard.press('Space');
    await page.getByRole('switch', { name: 'Tareas que vencen mañana', exact: true }).first().focus();
    await page.keyboard.press('Space');
    await page.getByRole('button', { name: 'Guardar preferencias', exact: true }).click();
    await expect(page.locator('.settings-preferences-actions [role=status]')).toHaveText('Preferencias guardadas.');
    await page.reload();
    await expect(morning).not.toBeChecked();
    await expect(page.getByRole('switch', { name: 'Tareas que vencen mañana', exact: true }).first()).toBeChecked();
    for (const state of ['unsupported', 'unconfigured', 'denied', 'off', 'on']) {
        // Solo presentación: no se piden permisos ni se contacta a un proveedor push.
        await page.evaluate(state => { window.Alpine.$data(document.querySelector('[x-data^="ltPushToggle"]')).state = state; }, state);
        await expect(page.locator('.settings-device')).toBeVisible();
        await expect(page.getByRole('switch', { name: 'Mañana 8–10', exact: true })).toBeAttached();
        await page.screenshot({ path: `storage/framework/testing/ui-density-push-${state}.png`, fullPage: true });
    }
});

test('datos físicos conservan validación y referencias tras guardar', async ({ page }) => {
    await page.goto('/settings/body');
    await page.getByLabel('Peso actual (kg)', { exact: true }).fill('10');
    await page.getByRole('button', { name: 'Guardar datos físicos' }).click();
    await expect(page.locator('.md-field__error, .md-field--error, [aria-invalid=true]').first()).toBeVisible();
    await page.getByLabel('Peso actual (kg)', { exact: true }).fill('70');
    await page.getByRole('button', { name: 'Guardar datos físicos' }).click();
    await expect(page.getByText('Perfil actualizado correctamente.')).toBeVisible();
    await expect(page.locator('.settings-body-reference')).toContainText('22,9');
    await page.reload();
    await expect(page.getByLabel('Peso actual (kg)', { exact: true })).toHaveValue('70');
});

test('cuenta sin datos ni compañero mantiene los estados vacíos', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/login');
    await page.getByLabel('Correo electrónico', { exact: true }).fill('density-empty@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
    await page.goto('/pomodoro');
    await expect(page.locator('.focus-companion')).toHaveCount(0);
    await expect(page.getByText('Sin sesiones de enfoque', { exact: true })).toBeVisible();
    await page.goto('/settings/body');
    await expect(page.getByText('Falta fecha de nacimiento', { exact: true })).toBeVisible();
    await expect(page.getByText('Faltan peso y estatura', { exact: true })).toBeVisible();
    await page.goto('/settings/notifications');
    await expect(page.getByRole('switch', { name: 'Mañana 8–10', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Guardar preferencias', exact: true })).toBeVisible();
});
