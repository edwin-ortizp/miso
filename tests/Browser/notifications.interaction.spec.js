import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const PORT = 8051;
let server;
test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    const compiledViews = path.join(directory, 'notifications-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    const database = path.join(directory, 'notifications-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-notifications-config.php') };
    execFileSync('php', ['tests/Browser/support/notifications-fixture.php'], { env, stdio: 'pipe' });
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Notification browser server did not start');
});
test.afterAll(() => server?.kill());
test.use({ baseURL: `http://127.0.0.1:${PORT}` });

for (const width of [390, 768, 1440]) {
    test(`notification inbox, settings and reminders at ${width}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.locator('input[type=email]').fill('notifications-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        const response = await page.goto('/settings/notifications');
        expect(response.status(), (await page.locator('body').innerText()).slice(0, 1000)).toBe(200);
        await expect(page.locator('body')).toContainText('Notificaciones');
        const bell = page.getByRole('button', { name: /^Notificaciones:/ });
        await bell.click();
        const inbox = page.getByRole('dialog', { name: 'Notificaciones', exact: true });
        await expect(inbox).toBeVisible();
        await expect(inbox.getByText('Un recordatorio de prueba.').first()).toBeVisible();
        const alreadyRead = await inbox.getByText('Leída', { exact: true }).count();
        await inbox.getByRole('button', { name: 'Marcar leída', exact: true }).first().click();
        await expect(inbox.getByText('Leída', { exact: true })).toHaveCount(alreadyRead + 1);
        await inbox.getByRole('button', { name: 'Filtros', exact: true }).click();
        await inbox.getByLabel('Lectura', { exact: true }).selectOption('read');
        await inbox.getByRole('button', { name: 'Filtrar', exact: true }).click();
        await expect(inbox.getByText('Un recordatorio de prueba.')).toHaveCount(alreadyRead + 1);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const a11y = await new AxeBuilder({ page }).include('.md-sheet').analyze();
        expect(a11y.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => ({ target: n.target, summary: n.failureSummary })) }))).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/notifications-inbox-${width}.png` });
        await page.keyboard.press('Escape');
        await expect(inbox).toBeHidden();
        await expect(bell).toBeFocused();
        await page.goto('/settings/notifications');
        await expect(page.getByRole('button', { name: 'Configurar Telegram', exact: true })).toHaveCount(0);
        const settingsA11y = await new AxeBuilder({ page }).include('main').analyze();
        expect(settingsA11y.violations.map(v => v.id)).toEqual([]);
        await page.goto('/tasks/list');
        await page.getByRole('button', { name: /Nueva tarea/, exact: true }).first().click();
        if (width < 768) await page.getByRole('menuitem', { name: 'Nueva tarea', exact: true }).click();
        const editor = page.getByRole('dialog', { name: 'Nueva tarea', exact: true });
        await expect(editor).toBeVisible();
        // Section navigation is collapsed on narrow screens.
        const navigation = editor.getByRole('button', { name: /secciones/i });
        if (await navigation.count() && !(await editor.getByRole('button', { name: 'Recordatorios', exact: true }).isVisible())) await navigation.click();
        await editor.getByRole('button', { name: 'Recordatorios', exact: true }).click();
        await editor.getByRole('button', { name: 'Añadir recordatorio', exact: true }).click();
        await expect(editor.getByLabel('Anticipación 1')).toBeVisible();
        await expect(editor.getByLabel('Unidad 1')).toBeVisible();
        expect(errors).toEqual([]);
    });
}
