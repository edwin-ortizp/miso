import { test, expect } from '@playwright/test';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const PORT = 8033;
test.use({ baseURL: `http://127.0.0.1:${PORT}` });
let server;

test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    const output = path.join(directory, 'playwright', 'tasks-calendar');
    mkdirSync(output, { recursive: true });
    const database = path.join(directory, 'tasks-calendar-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = {
        ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false',
        NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-tasks-calendar-config.php'),
    };
    execFileSync('php', ['tests/Browser/support/tasks-calendar-fixture.php'], { env, stdio: 'pipe' });
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'pipe', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try {
            if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return;
        } catch {}
        await new Promise((resolve) => setTimeout(resolve, 500));
    }
    throw Error('Server did not start');
});
test.afterAll(() => server?.kill());


for (const width of [1440, 768, 390]) {
    test(`calendar controls and task navigation ${width}`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.locator('input[type=email]').fill('tasks-calendar-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/calendar');
        const toolbar = page.locator('.md-calendar-compact-toolbar');
        await expect(page.locator('.fc-view-harness')).toBeVisible();
        await expect(toolbar.locator('.md-calendar-period')).not.toBeEmpty();
        await expect(toolbar.getByRole('button', { name: 'Ahora' })).toBeVisible();
        await expect(toolbar.getByRole('button', { name: 'Filtros' })).toBeVisible();
        if (width >= 768) {
            await expect(toolbar.getByRole('group', { name: 'Vista del calendario' })).toBeVisible();
            await expect(toolbar.getByRole('button', { name: 'Semana', exact: true })).toBeVisible();
        } else {
            await expect(toolbar.getByLabel('Vista', { exact: true })).toBeVisible();
        }
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const trigger = page.getByRole('button', { name: 'Más acciones de Calendar recurring task', exact: true }).first();
        await trigger.focus();
        await trigger.click();
        const menu = page.getByRole('menu', { name: 'Acciones de la tarea' });
        await expect(menu).toBeVisible();
        await expect(menu.getByRole('menuitem', { name: 'Mover para mañana' })).toBeVisible();
        const box = await menu.boundingBox();
        expect(box.width).toBeGreaterThanOrEqual(210);
        expect(box.width).toBeLessThan(320);
        const triggerBox = await trigger.boundingBox();
        expect(Math.abs(box.x - Math.max(8, triggerBox.x + triggerBox.width - box.width))).toBeLessThan(2);
        await page.screenshot({ path: testInfo.outputPath('calendar-menu.png') });
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(width);
        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
        await expect(trigger).toBeFocused();
        for (const [view, label] of [['dayGridMonth', 'Mes'], ['listWeek', 'Agenda'], ['timeGridDay', 'Día'], ['timeGridWeek', 'Semana']]) {
            if (width >= 768) await toolbar.getByRole('button', { name: label, exact: true }).click();
            else await toolbar.getByLabel('Vista', { exact: true }).selectOption(view);
            const action = page.getByRole('button', { name: 'Más acciones de Calendar recurring task', exact: true }).first();
            await action.focus();
            await page.keyboard.press('Enter');
            await expect(menu).toBeVisible();
            await expect.poll(async () => {
                const rect = await menu.boundingBox();
                return rect && Math.abs(rect.width - box.width) < 2 && rect.x >= 8 && rect.x + rect.width <= width - 8;
            }).toBe(true);
            if (view === 'timeGridWeek') {
                await page.keyboard.press('Escape');
                const other = page.getByRole('button', { name: 'Más acciones de Calendar browser task', exact: true }).first();
                await other.focus();
                await page.keyboard.press('Enter');
                await expect(menu).toBeVisible();
                await expect.poll(async () => (await menu.boundingBox())?.x ?? -1).toBeGreaterThanOrEqual(8);
                const otherBox = await menu.boundingBox();
                expect(Math.abs(otherBox.width - box.width)).toBeLessThan(2);
                expect(otherBox.x + otherBox.width).toBeLessThanOrEqual(width - 8);
            }
            await page.keyboard.press('ArrowDown');
            await expect(menu.getByRole('menuitem', { name: 'Completar', exact: true })).toBeFocused();
            await page.keyboard.press('Escape');
            await expect(menu).toBeHidden();
        }

        await expect(page.getByRole('dialog', { name: 'Consultar tarea' })).toHaveCount(0);
        await page.screenshot({ path: testInfo.outputPath('calendar.png') });
        const controls = await toolbar.locator('.md-calendar-controls').boundingBox();
        const now = await toolbar.getByRole('button', { name: 'Ahora' }).boundingBox();
        const filters = await toolbar.getByRole('button', { name: 'Filtros' }).boundingBox();
        expect(Math.abs(now.y - filters.y)).toBeLessThan(16);
        expect(controls.height).toBeLessThan(80);
        await toolbar.getByRole('button', { name: 'Filtros' }).click();
        await expect(page.getByRole('dialog', { name: 'Filtros' })).toBeVisible();
        await page.getByRole('dialog', { name: 'Filtros' }).getByLabel('Planes').uncheck();
        await page.getByRole('dialog', { name: 'Filtros' }).getByRole('button', { name: 'Filtrar' }).click();
        await expect(toolbar.getByRole('button', { name: 'Quitar filtro de tipos de elemento' })).toBeVisible();
        const listResponse = await page.goto('/tasks');
        expect(listResponse.status()).toBe(200);
        await expect(page.getByText('Internal Server Error')).toHaveCount(0);
        await expect(page.locator('.lt-topbar')).toBeVisible();
        const navigation = page.getByRole('navigation', { name: 'Vistas del módulo' });
        await expect(navigation).toBeVisible();
        await expect(navigation.getByRole('link', { name: 'Lista', exact: true })).toHaveAttribute('aria-current', 'page');
        await expect(navigation.getByRole('link', { name: 'Calendario', exact: true })).toBeAttached();
        await expect(navigation.getByRole('link')).toHaveCount(8);
        await page.screenshot({ path: testInfo.outputPath('task-list.png') });
    });
}
