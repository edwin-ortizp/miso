import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

// CHG-070: cierre del día en móvil y escritorio.
const PORT = 8058;
let server;
test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    const compiledViews = path.join(directory, 'day-close-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    const database = path.join(directory, 'day-close-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-day-close-config.php') };
    execFileSync('php', ['tests/Browser/support/day-close-fixture.php'], { env, stdio: 'pipe' });
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Day close browser server did not start');
});
test.afterAll(() => server?.kill());
test.use({ baseURL: `http://127.0.0.1:${PORT}` });
test.describe.configure({ mode: 'serial' });

async function login(page) {
    await page.goto('/login');
    await page.locator('input[type=email]').fill('day-close-browser@example.test');
    await page.locator('input[type=password]').fill('browser-test-password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
}

for (const width of [360, 1440]) {
    test(`day close summary, move and undo at ${width}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 900 });
        await login(page);
        const response = await page.goto('/cierre?via=close');
        expect(response.status(), (await page.locator('body').innerText()).slice(0, 1000)).toBe(200);
        await expect(page.getByRole('heading', { name: 'Cerrar el día' }).first()).toBeAttached();
        await expect(page.getByText('Así te fue hoy')).toBeVisible();
        await expect(page.getByText('Quedó pendiente (2)')).toBeVisible();
        await expect(page.getByText('Tarea de ayer')).toHaveCount(0);
        // Medir después de la animación de entrada de la página (desplaza el contenido unos píxeles).
        await page.waitForFunction(() => document.getAnimations().every(a => a.playState !== 'running'));
        const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > innerWidth + 1).slice(0, 8).map(el => `${el.tagName}.${el.className} ${Math.round(el.getBoundingClientRect().right)}`));
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), overflow.join(' | ')).toBe(true);
        const a11y = await new AxeBuilder({ page }).include('.day-close').analyze();
        expect(a11y.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) }))).toEqual([]);

        await page.screenshot({ path: `storage/framework/testing/playwright/day-close/day-close-${width}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Pasar todo a mañana' }).click();
        await expect(page.getByText('Listo, quedaron para mañana.')).toBeVisible();
        await expect(page.getByText('Nada pendiente para mañana.')).toBeVisible();
        await page.getByRole('button', { name: 'Deshacer' }).click();
        await expect(page.getByText('Deshecho. Las tareas quedaron como estaban.')).toBeVisible();
        await expect(page.getByText('Quedó pendiente (2)')).toBeVisible();
        expect(errors).toEqual([]);
    });
}

test('finishing the day closes it and shows the closed state', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 900 });
    await login(page);
    await page.goto('/cierre');
    await page.getByRole('button', { name: 'Listo, terminé el día' }).click();
    await page.waitForURL(url => url.pathname.endsWith('/') || url.pathname === '');
    await expect(page.locator('[data-testid="day-close-card"]')).toHaveCount(0);
    await page.goto('/cierre');
    await expect(page.getByText('Ya cerraste el día. Descansa.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Listo, terminé el día' })).toHaveCount(0);
});
