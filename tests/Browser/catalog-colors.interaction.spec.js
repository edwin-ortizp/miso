import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

let server;
test.describe.configure({ mode: 'serial', timeout: 90000 });
test.use({ baseURL: 'http://127.0.0.1:8037' });
test.beforeAll(async () => {
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'catalog-colors-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-catalog-config.php') };
    execFileSync('php', ['tests/Browser/support/catalog-colors-fixture.php'], { env });
    server = spawn('php', ['-S', '127.0.0.1:8037', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch('http://127.0.0.1:8037/login')).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Testing server did not start');
});
test.afterAll(() => server?.kill());

async function editRecord(page, name) {
    const row = page.locator('tbody tr').filter({ hasText: name });
    const primary = row.getByRole('button', { name: 'Editar', exact: true });
    if (await primary.isVisible()) await primary.click();
    else {
        await row.getByRole('button', { name: 'Acciones de ' + name, exact: true }).click();
        await row.getByRole('menuitem', { name: 'Editar', exact: true }).click();
    }
}

for (const width of [390, 1440]) for (const theme of ['light', 'dark']) {
    test(`colores de catálogo ${width}px ${theme}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/login');
        await page.getByLabel('Correo electrónico', { exact: true }).fill('catalog@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/settings');
        await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
        await editRecord(page, 'Proyecto de colores');
        const dialog = page.getByRole('dialog', { name: 'Editar categoría', exact: true });
        const color = dialog.getByLabel('Color', { exact: true });
        await expect(color).toBeVisible();
        await color.focus();
        await page.keyboard.press('Home');
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Tab');
        for (const value of ['gray', 'blue', 'indigo', 'violet', 'pink', 'red', 'orange', 'yellow', 'green', 'teal']) {
            await color.selectOption(value);
            await expect(dialog.locator('.md-catalog-chip')).toHaveClass(new RegExp('md-catalog-chip--' + value));
            const result = await new AxeBuilder({ page }).include('#task-category-dialog').withRules(['color-contrast', 'label', 'select-name']).analyze();
            expect(result.violations).toEqual([]);
        }
        await color.selectOption('violet');
        await expect(dialog.locator('.md-catalog-chip')).toHaveClass(/md-catalog-chip--violet/);
        expect(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/catalog-colors-${width}-${theme}.png`, fullPage: true });
        await dialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(dialog).toHaveCount(0);
        await page.goto('/tasks/settings/statuses');
        await editRecord(page, 'Borrador');
        const statusDialog = page.getByRole('dialog', { name: 'Editar estado', exact: true });
        await statusDialog.getByLabel('Color', { exact: true }).selectOption('orange');
        await expect(statusDialog.locator('.md-catalog-chip')).toHaveClass(/md-catalog-chip--orange/);
        await statusDialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(statusDialog).toHaveCount(0);
        for (const route of ['/tasks/list', '/tasks/planning', '/tasks/kanban']) {
            await page.goto(route);
            await expect(page.locator('.md-catalog-chip--violet').filter({ hasText: 'Proyecto de colores' })).toBeVisible();
            if (route === '/tasks/list') await expect(page.locator('.task-row__status .md-catalog-chip--orange')).toBeVisible();
            await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - innerWidth), { message: 'Overflow on ' + route }).toBeLessThanOrEqual(1);
        }
        await page.goto('/tasks/settings');
        await editRecord(page, 'Proyecto de colores');
        await page.getByRole('dialog').getByLabel('Color', { exact: true }).selectOption('');
        await page.getByRole('dialog').getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        expect(errors).toEqual([]);
    });
}
