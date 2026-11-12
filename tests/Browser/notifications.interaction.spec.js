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

for (const width of [375, 390, 768, 1440]) {
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
        await expect(inbox.getByText('Tarea de prueba', { exact: true }).first()).toBeVisible();
        const alreadyRead = await inbox.locator('.notification-row__date').filter({ hasText: 'Leída' }).count();
        await inbox.getByRole('button', { name: 'Marcar como leída', exact: true }).first().click();
        await expect(inbox.locator('.notification-row__date').filter({ hasText: 'Leída' })).toHaveCount(alreadyRead + 1);
        await inbox.getByRole('button', { name: 'Filtros', exact: true }).click();
        await inbox.getByLabel('Lectura', { exact: true }).selectOption('read');
        await inbox.getByRole('button', { name: 'Filtrar', exact: true }).click();
        await expect(inbox.locator('.notification-row')).toHaveCount(alreadyRead + 1);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const a11y = await new AxeBuilder({ page }).include('.md-sheet').analyze();
        expect(a11y.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => ({ target: n.target, summary: n.failureSummary })) }))).toEqual([]);
        await inbox.getByRole('button', { name: 'Limpiar filtros', exact: true }).click();
        await expect(inbox.locator('.notification-row')).toHaveCount(15);
        await inbox.getByRole('button', { name: 'Página siguiente', exact: true }).click();
        await expect(inbox.locator('.notification-row')).toHaveCount(9);
        await expect(inbox.getByText('Página 2 de 2', { exact: true })).toBeVisible();
        await inbox.getByRole('button', { name: 'Página anterior', exact: true }).click();
        await expect(inbox.locator('.notification-row')).toHaveCount(15);
        // Every type resolves its own context and destination. Draft selection
        // must not replace the visible list before Filtrar is pressed.
        for (const [type, title, target] of [
            ['task_due', 'Preparar la propuesta comercial', /tasks\/list\?edit=/],
            ['plan_due', 'Visitar el museo', /plans\//],
            ['birthday', 'Cumpleaños de Laura', /relationships\//],
            ['guardian_nudge', 'Tu guardián de tareas', /tasks\/list$/],
            ['weekly_insights', 'Tu resumen de la semana', /statistics#insights$/],
        ]) {
            await inbox.getByRole('button', { name: /^Filtros/ }).click();
            const filters = inbox.getByRole('dialog', { name: 'Filtros de notificaciones', exact: true });
            await expect(filters).toBeVisible();
            const bounds = await filters.boundingBox();
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width + 1);
            await filters.getByLabel('Tipo', { exact: true }).selectOption(type);
            if (type === 'task_due') await expect(inbox.locator('.notification-row')).toHaveCount(15);
            await filters.getByRole('button', { name: 'Filtrar', exact: true }).click();
            await expect(filters).toBeHidden();
            await expect(inbox.locator('.notification-row')).toHaveCount(1);
            await expect(inbox.getByRole('link', { name: new RegExp(title) })).toHaveAttribute('href', target);
            await expect(inbox.getByRole('button', { name: /Quitar filtro:/ })).toHaveCount(1);
            await inbox.getByRole('button', { name: /Quitar filtro:/ }).click();
            await expect(inbox.locator('.notification-row')).toHaveCount(15);
        }
        await expect(inbox.locator('.notification-row--danger')).toHaveCount(1);
        await expect(bell).toHaveClass(/md-btn--danger/);
        expect((await inbox.getByRole('button', { name: 'Marcar como leída', exact: true }).first().boundingBox()).height).toBeGreaterThanOrEqual(44);
        expect((await inbox.getByRole('button', { name: /^Filtros/ }).boundingBox()).height).toBeGreaterThanOrEqual(44);
        const rowHeight = await inbox.locator('.notification-row').filter({ hasText: 'Preparar la propuesta comercial' }).evaluate(el => el.getBoundingClientRect().height);
        expect(rowHeight).toBeLessThan(210);
        const allTypesA11y = await new AxeBuilder({ page }).include('.md-sheet').analyze();
        expect(allTypesA11y.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) }))).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/notifications-inbox-${width}.png` });
        await inbox.getByRole('button', { name: /^Filtros/ }).click();
        await expect(inbox.getByRole('dialog', { name: 'Filtros de notificaciones', exact: true })).toBeVisible();
        const filtersA11y = await new AxeBuilder({ page }).include('.md-sheet').analyze();
        expect(filtersA11y.violations.map(v => v.id)).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/notifications-filters-${width}.png` });
        await page.keyboard.press('Escape');
        await expect(inbox).toBeVisible();
        await expect(inbox.getByRole('dialog', { name: 'Filtros de notificaciones', exact: true })).toBeHidden();
        await page.keyboard.press('Escape');
        await expect(inbox).toBeHidden();
        await expect(bell).toBeFocused();
        await page.goto('/settings/notifications');
        await expect(page.getByRole('button', { name: 'Configurar Telegram', exact: true })).toHaveCount(0);
        const settingsA11y = await new AxeBuilder({ page }).include('main').analyze();
        expect(settingsA11y.violations.map(v => v.id)).toEqual([]);
        // A notification must open the exact task even when the list's search
        // would hide it. Following it still leaves the notice unread.
        await page.goto('/tasks/list?q=sin-resultados-de-prueba');
        await bell.click();
        const taskLink = inbox.getByRole('link', { name: /Preparar la propuesta comercial/ });
        await taskLink.click();
        await page.waitForURL(/tasks\/list\?edit=/);
        await expect(page.getByRole('dialog', { name: 'Editar tarea', exact: true })).toBeVisible();
        await page.keyboard.press('Escape');
        await bell.click();
        await expect(inbox.locator('.notification-row--unread').filter({ hasText: 'Preparar la propuesta comercial' })).toHaveCount(1);
        await page.keyboard.press('Escape');
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
