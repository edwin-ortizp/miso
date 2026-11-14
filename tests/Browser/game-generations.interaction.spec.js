import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';
let server;
test.beforeAll(async () => {
    test.setTimeout(90000);
    const dir = path.resolve('storage/framework/testing');
    mkdirSync(dir, { recursive: true });
    const database = path.join(dir, 'game-generations-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8044', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', APP_CONFIG_CACHE: path.join(dir, 'unused-generations-config.php'), GAME_IMAGE_BASE_URL: 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork' };
    execFileSync('php', ['tests/Browser/support/game-generations-fixture.php'], { env, encoding: 'utf8' });
    server = spawn('php', ['-S', '127.0.0.1:8044', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i=0; i<60; i++) {
        try { if ((await fetch('http://127.0.0.1:8044/login')).ok) return; } catch {}
        await new Promise(r => setTimeout(r, 500));
    }
    throw Error('Test server unavailable');
});
test.afterAll(() => server?.kill());
test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico', { exact: true }).fill('generations@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
});
for (const theme of ['light', 'dark']) {
    for (const width of [390, 1440]) {
        test(`Generaciones ${width}, preferencia ${theme}`, async ({ page }) => {
            await page.setViewportSize({ width, height: 1000 });
            await page.emulateMedia({ colorScheme: theme });
            await page.goto('/game/settings/generations');
            await page.evaluate(t => document.documentElement.dataset.theme=t, theme);
            await expect(page.getByRole('heading', { name: 'Tu aventura incluye 151 Pokémon' })).toBeVisible();
            await expect(page.locator('.game-generation')).toHaveCount(9);
            await expect(page.getByRole('button', { name:'Guardar cambios', exact:true })).toBeInViewport();
            await expect(page.getByRole('switch', { name: 'Primera generación: Activa', exact: true })).toBeChecked();
            await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            const violations = await new AxeBuilder({ page }).include('.settings-layout').analyze();
            expect(violations.violations).toEqual([]);
            await page.screenshot({ path: `storage/framework/testing/generations-${width}-${theme}.png`, fullPage: true });
        });
    }
}
test('guardado por sección, cambios pendientes y navegación', async ({ page }) => {
    test.setTimeout(90000);
    await page.goto('/game/settings/generations');
    await page.getByRole('switch', { name: 'Segunda generación: En pausa', exact: true }).focus();
    await page.keyboard.press('Space');
    await expect(page.getByText('Cambios pendientes', { exact: true })).toBeVisible();
    await page.keyboard.press('Space');
    await expect(page.getByText('Cambios pendientes', { exact: true })).not.toBeVisible();
    await page.keyboard.press('Space');
    await expect(page.getByText('Cambios pendientes', { exact: true })).toBeVisible();
    const nav = page.getByRole('navigation', { name: 'Secciones de ajustes de Colección' });
    await nav.getByRole('link', { name: 'General', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Tienes cambios sin guardar' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Seguir editando' }).click();
    await expect(dialog).not.toBeVisible();
    await page.getByRole('button', { name: 'Guardar cambios', exact: true }).click();
    await expect(page.getByText('Ajustes guardados.', { exact: true })).toBeVisible();
    await expect(page.getByText('Cambios pendientes', { exact: true })).not.toBeVisible();
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Tu aventura incluye 251 Pokémon' })).toBeVisible();
    await page.getByRole('switch', { name: 'Segunda generación: Activa', exact: true }).focus();
    await page.keyboard.press('Space');
    await page.getByRole('button', { name: 'Guardar cambios', exact: true }).click();
    await expect(page.getByText('Ajustes guardados.', { exact: true })).toBeVisible();
    for (const [label, route] of [['General', ''], ['Recompensas','/rewards'], ['Compañero','/companion'], ['Celebraciones','/celebrations']]) {
        await page.getByRole('navigation', { name: 'Secciones de ajustes de Colección' }).getByRole('link', { name: label, exact:true }).click();
        await expect(page).toHaveURL(new RegExp('/game/settings'+route+'$'));
        await expect(page.getByRole('button', { name:'Guardar cambios', exact:true })).toBeVisible();
        expect((await new AxeBuilder({ page }).include('.settings-layout').analyze()).violations).toEqual([]);
    }
});

test('validación, descarte y regreso conservan la selección guardada', async ({ page }) => {
    test.setTimeout(60000);
    await page.setViewportSize({ width:390, height:844 });
    await page.goto('/game/settings/generations');
    const first = page.getByRole('switch', { name: 'Primera generación: Activa', exact:true });
    await first.focus();
    await page.keyboard.press('Space');
    await page.getByRole('button', { name:'Guardar cambios', exact:true }).click();
    await expect(page.getByRole('alert').filter({ hasText:'Mantén al menos una generación activa.' })).toBeVisible();
    await page.getByRole('navigation', { name:'Secciones de ajustes de Colección' }).getByRole('link', { name:'General', exact:true }).click();
    const dialog = page.getByRole('dialog', { name:'Tienes cambios sin guardar' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name:'Descartar cambios', exact:true }).click();
    await expect(page).toHaveURL(/\/game\/settings$/);
    await page.goBack();
    await expect(page).toHaveURL(/\/game\/settings\/generations(?:\?|$)/);
    await expect(page.getByRole('switch', { name:'Primera generación: Activa', exact:true })).toBeChecked();
    await expect(page.getByText('Cambios pendientes', { exact:true })).not.toBeVisible();
});
