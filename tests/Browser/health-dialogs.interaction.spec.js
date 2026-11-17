import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8091;
const directory = path.resolve('storage/framework/testing');
const database = path.join(directory, 'health-guardian-browser.sqlite');
let server, fixture;
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: `http://127.0.0.1:${PORT}` });

test.beforeAll(async () => {
    test.setTimeout(120000);
    mkdirSync(directory, { recursive: true });
    const compiledViews = path.join(directory, 'health-guardian-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${PORT}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        GAME_IMAGE_BASE_URL: '', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        APP_CONFIG_CACHE: path.join(directory, 'unused-health-guardian-config.php') };
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/health-guardian-fixture.php'], { env, encoding: 'utf8' }));
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    throw Error('Health dialogs server did not start');
});

test.afterAll(async () => {
    if (server && server.exitCode === null) {
        const stopped = new Promise(resolve => server.once('exit', resolve));
        server.kill();
        await stopped;
    }
    // Solo archivos exactos de esta fixture dentro del directorio de testing.
    for (const suffix of ['', '-wal', '-shm']) {
        const target = database + suffix;
        if (path.dirname(target) !== directory) throw Error('Unsafe fixture cleanup');
        if (existsSync(target)) unlinkSync(target);
    }
});

async function login(page, email = 'voice@example.test') {
    await page.goto('/login');
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill('browser-test-password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => !url.pathname.includes('/login'));

}



for (const width of [360, 390, 1440]) {
    test(`modales de Salud con selectores táctiles a ${width}`, async ({ page }) => {
        test.setTimeout(120000);
        await page.setViewportSize({ width, height: 900 });
        await login(page);
        await page.goto('/health/medications');
        // La creación vive en el FAB del compañero; aquí se abre el formulario igual que su acción.
        await page.waitForFunction(() => window.Livewire?.all().some((c) => c.name === 'health.medications'));
        await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'health.medications').$wire.openForm());
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('details')).toHaveCount(0);

        await dialog.getByLabel('Frecuencia').selectOption('weekdays');
        const monday = dialog.getByRole('button', { name: 'Lunes' });
        await expect(monday).toBeVisible();
        const box = await monday.boundingBox();
        expect(box.width).toBeGreaterThanOrEqual(43);
        expect(box.height).toBeGreaterThanOrEqual(43);
        await expect(monday).toHaveAttribute('aria-pressed', 'false');
        await monday.click();
        await expect(monday).toHaveAttribute('aria-pressed', 'true');

        // Horarios: un selector de hora por fila, sin texto libre.
        await dialog.getByRole('button', { name: /agregar hora/i }).click();
        await expect(dialog.locator('input[type=time]')).toHaveCount(2);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);

        const results = await new AxeBuilder({ page }).include('[role=dialog]').analyze();
        expect(results.violations.filter(v => ['serious', 'critical'].includes(v.impact))).toEqual([]);
    });
}
