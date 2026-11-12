import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

let server, env, fixture;
const base = 'http://127.0.0.1:8034';
test.describe.configure({ mode: 'serial' });
test.use({ hasTouch: true, actionTimeout: 15_000, navigationTimeout: 20_000 });
test.setTimeout(180_000);

test.beforeAll(async () => {
    test.setTimeout(60_000);
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'catalog-colors-browser.sqlite');
    closeSync(openSync(database, 'a'));
    env = { ...process.env, APP_ENV: 'testing', APP_URL: base, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-catalog-config.php') };
    server = spawn('php', ['-S', '127.0.0.1:8034', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let attempt = 0; attempt < 60; attempt++) {
        try { const response = await fetch(`${base}/login`, { signal: AbortSignal.timeout(5000) }); if (response.ok) { return; } } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw new Error('Isolated catalog server did not start.');
});
test.afterAll(() => server?.kill());

async function editRecord(page, name) {
    const row = page.getByRole('row').filter({ hasText: name });
    const primary = row.getByRole('button', { name: 'Editar', exact: true });
    if (await primary.isVisible()) await primary.click();
    else {
        await row.getByRole('button', { name: 'Acciones de ' + name, exact: true }).click();
        await row.getByRole('menuitem', { name: 'Editar', exact: true }).click();
    }
}

async function login(page) {
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/catalog-colors-fixture.php'], { env, encoding: 'utf8' }));
    await page.goto(`${base}/login`);
    await page.getByLabel('Correo electrónico').fill('catalog-colors@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
}

async function assertMenuBounds(page, trigger, menu) {
    const field = await trigger.boundingBox();
    const list = await menu.boundingBox();
    const size = page.viewportSize();
    expect(list.x).toBeGreaterThanOrEqual(0);
    expect(list.y).toBeGreaterThanOrEqual(0);
    expect(list.x + list.width).toBeLessThanOrEqual(size.width);
    expect(list.y + list.height).toBeLessThanOrEqual(size.height);
    expect(Math.abs(list.width - field.width)).toBeLessThan(2);
}

async function assertChipContrast(page) {
    for (const theme of ['light', 'dark']) {
        await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
        const result = await new AxeBuilder({ page }).include('.md-catalog-chip').withRules(['color-contrast']).analyze();
        expect(result.violations).toEqual([]);
    }
}

for (const width of [375, 768, 1280]) {
    test(`catalog color field and chips at ${width}px`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 900 });
        await login(page);
        await page.goto(`${base}/tasks/settings`);
        const row = page.getByRole('row').filter({ hasText: 'Proyecto visual' });
        await editRecord(page, 'Proyecto visual');
        const dialog = page.getByRole('dialog', { name: 'Editar categoría', exact: true });
        const trigger = dialog.getByRole('combobox', { name: /^Color/ });
        const menu = dialog.getByRole('listbox', { name: 'Color', exact: true });
        await expect(trigger).toContainText('Proyecto visual');
        await expect(trigger).toContainText('Violeta');
        await expect(trigger.locator('.bi-star')).toHaveCount(1);
        expect(await dialog.evaluate(el => el.closest('[data-module]').dataset.module)).toBe('tasks');
        await dialog.getByLabel('Nombre', { exact: false }).fill('Proyecto visual con un nombre largo para probar los chips');
        await expect(trigger).toContainText('Proyecto visual con un nombre largo');
        await dialog.getByLabel('Icono', { exact: true }).selectOption('bi-airplane');
        await expect(trigger.locator('.bi-airplane')).toHaveCount(1);
        for (const theme of ['light', 'dark']) {
            await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
            await trigger.click();
            await expect(menu).toBeVisible();
            await assertMenuBounds(page, trigger, menu);
            await expect(menu.getByRole('option')).toHaveCount(11);
            const axe = await new AxeBuilder({ page }).include('.md-catalog-color-select').analyze();
            expect(axe.violations).toEqual([]);
            if (width === 1280) {
                for (const label of ['Predeterminado', 'Gris', 'Azul', 'Índigo', 'Violeta', 'Rosa', 'Rojo', 'Naranja', 'Amarillo', 'Verde', 'Turquesa']) {
                    await menu.getByRole('option', { name: new RegExp('^' + label + ' ') }).click();
                    await expect(trigger).toContainText(label);
                    const palette = await new AxeBuilder({ page }).include('.md-catalog-color-select__trigger').withRules(['color-contrast']).analyze();
                    expect(palette.violations).toEqual([]);
                    await trigger.click();
                }
            }
            expect(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
            await page.screenshot({ path: `storage/framework/testing/catalog-${width}-${theme}.png` });
            await trigger.press('Escape');
            await expect(menu).not.toBeVisible();
            await expect(dialog).toBeVisible();
            await expect(trigger).toBeFocused();
        }
        await trigger.press('ArrowDown');
        await trigger.press('End');
        await trigger.press('Enter');
        await expect(trigger).toContainText('Turquesa');
        await trigger.click();
        await trigger.press('Tab');
        await expect(menu).not.toBeVisible();
        await expect(dialog).toBeVisible();
        await trigger.click();
        await menu.getByRole('option', { name: /^Azul / }).tap();
        await expect(trigger).toContainText('Azul');
        await dialog.getByLabel('Nombre', { exact: false }).fill('Proyecto visual');
        await expect(trigger.locator('.md-catalog-chip')).toHaveText('Proyecto visual');
        await trigger.click();
        await assertMenuBounds(page, trigger, menu);
        await trigger.press('Escape');
        await dialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        await expect(row.locator('.md-catalog-chip')).toHaveClass(/md-catalog-chip--blue/);
        await expect(row.locator('.bi-airplane')).toHaveCount(1);
        await editRecord(page, 'Proyecto visual');
        await expect(trigger).toContainText('Azul');
        await trigger.click();
        await trigger.press('Home');
        await trigger.press('Enter');
        await expect(trigger).toContainText('Predeterminado');
        await dialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(row.locator('.md-catalog-chip')).toHaveClass(/md-catalog-chip--accent/);

        for (const route of ['/tasks/list', '/tasks/planning', '/tasks/kanban']) {
            await page.goto(`${base}${route}`);
            await expect(page.locator('.md-catalog-chip').filter({ hasText: 'Proyecto visual' }).first().locator('.bi-airplane')).toBeVisible();
        }
        await page.goto(`${base}/tasks/settings/statuses`);
        await editRecord(page, 'Borrador');
        const statusDialog = page.getByRole('dialog', { name: 'Editar estado', exact: true });
        const statusColor = statusDialog.getByRole('combobox', { name: /^Color/ });
        await statusColor.click();
        await statusDialog.getByRole('option', { name: /^Naranja / }).tap();
        await expect(statusColor).toContainText('Naranja');
        await statusDialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(statusDialog).not.toBeVisible();
        await editRecord(page, 'Borrador');
        await expect(statusColor).toContainText('Naranja');
        await statusColor.click();
        await statusColor.press('Home');
        await statusColor.press('Enter');
        await statusDialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(statusDialog).not.toBeVisible();
        await page.getByRole('button', { name: 'Nuevo estado', exact: true }).click();
        const newStatus = page.getByRole('dialog', { name: 'Nuevo estado', exact: true });
        await newStatus.getByLabel('Nombre', { exact: false }).fill('Estado visual nuevo');
        await expect(newStatus.getByRole('combobox', { name: /^Color/ })).toContainText('Estado visual nuevo');
        await newStatus.getByRole('combobox', { name: /^Color/ }).click();
        await newStatus.getByRole('option', { name: /^Verde / }).tap();
        await newStatus.getByRole('button', { name: 'Crear estado', exact: true }).click();
        await expect(newStatus).not.toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Estado visual nuevo' }).locator('.md-catalog-chip')).toHaveClass(/md-catalog-chip--green/);
        await page.goto(`${base}/tasks/settings`);
        await page.getByRole('button', { name: 'Nueva categoría', exact: true }).click();
        const newCategory = page.getByRole('dialog', { name: 'Nueva categoría', exact: true });
        await newCategory.getByLabel('Nombre', { exact: false }).fill('Categoría visual nueva');
        await expect(newCategory.getByRole('combobox', { name: /^Color/ })).toContainText('Categoría visual nueva');
        await newCategory.getByRole('combobox', { name: /^Color/ }).click();
        await newCategory.getByRole('option', { name: /^Rosa / }).tap();
        await newCategory.getByRole('button', { name: 'Crear categoría', exact: true }).click();
        await expect(newCategory).not.toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Categoría visual nueva' }).locator('.md-catalog-chip')).toHaveClass(/md-catalog-chip--pink/);
        await page.goto(`${base}/plans`);
        await expect(page.locator('.plan-row .md-catalog-chip').filter({ hasText: 'Paseos visuales' }).locator('.bi-tree')).toBeVisible();
        await assertChipContrast(page);
        await page.goto(`${base}/vehicles/${fixture.vehicle}/expenses`);
        await expect(page.locator('.md-catalog-chip:visible').filter({ hasText: 'Gasto visual' }).locator('.bi-shield').first()).toBeVisible();
        await assertChipContrast(page);
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
        expect(errors).toEqual([]);
    });
}
