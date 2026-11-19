import { test, expect } from '@playwright/test';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const PORT = Number(process.env.MISO_TASK_CALENDAR_TEST_PORT ?? 8033);
test.use({ baseURL: `http://127.0.0.1:${PORT}`, serviceWorkers: 'block' });
let server;

test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    const output = path.join(directory, 'playwright', 'tasks-calendar');
    mkdirSync(output, { recursive: true });
    const database = path.join(directory, `tasks-calendar-browser-${PORT}.sqlite`);
    closeSync(openSync(database, 'a'));
    const env = {
        ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false',
        NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-tasks-calendar-config.php'),
    };
    execFileSync('php', ['tests/Browser/support/tasks-calendar-fixture.php'], { env, stdio: 'pipe' });
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try {
            if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return;
        } catch {}
        await new Promise((resolve) => setTimeout(resolve, 500));
    }
    throw Error('Server did not start');
});
test.afterAll(() => server?.kill());


for (const width of [320, 390]) {
    test(`mobile Agenda layout and view preference ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.locator('input[type=email]').fill('tasks-calendar-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/calendar');
        const toolbar = page.locator('.md-calendar-compact-toolbar');
        await expect(page.locator('.fc-listWeek-view')).toBeVisible();
        const agenda = page.locator('.fc-listWeek-view');
        const row = agenda.locator('.fc-list-event').filter({ hasText: 'Calendar recurring task' }).first();
        await expect(row).toHaveCSS('display', 'grid');
        const completed = agenda.locator('.fc-list-event').filter({ hasText: 'Calendar completed task with a long title' });
        await expect(completed.getByRole('img', { name: 'Completada' })).toBeVisible();
        await expect(completed.locator('.fc-list-event-time')).toHaveText(/todo el día/i);
        await expect(completed.locator('.fc-list-event-title a')).toHaveCSS('text-decoration-line', 'line-through');
        const title = await completed.locator('.fc-list-event-title').boundingBox();
        expect(title.height).toBeGreaterThan(34);
        await expect(agenda.locator('.fc-list-event').filter({ hasText: 'Calendar browser task' })
            .locator('.fc-list-event-time')).toContainText(/9:00.*10:00/);
        await expect(agenda.locator('.fc-list-day').first()).toBeVisible();
        await page.evaluate(() => { document.documentElement.dataset.theme = 'dark'; });
        await expect.poll(() => completed.evaluate(row => getComputedStyle(row).backgroundColor))
            .not.toBe('rgba(0, 0, 0, 0)');
        await page.evaluate(() => { document.documentElement.dataset.theme = 'light'; });

        await expect(row.locator('.md-calendar-agenda-status')).toBeVisible();
        await expect(row.getByRole('button', { name: /Más acciones/ })).toHaveCSS('width', '44px');
        const trigger = row.getByRole('button', { name: /Más acciones/ });
        await trigger.scrollIntoViewIfNeeded();
        await trigger.click();
        const menu = page.getByRole('menu', { name: 'Acciones de la tarea' });
        await expect(menu.getByRole('menuitem', { name: 'Completar', exact: true })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(trigger).toBeFocused();

        await toolbar.getByRole('button', { name: 'Ahora' }).click();
        await expect(page.locator('.fc-timeGridDay-view')).toBeVisible();
        await toolbar.getByLabel('Vista:', { exact: true }).selectOption('listWeek');
        await page.setViewportSize({ width: 820, height: 700 });
        await expect(agenda).toBeVisible();
        await expect(row).toHaveCSS('display', 'table-row');
        await page.setViewportSize({ width, height: 900 });
        await expect(row).toHaveCSS('display', 'grid');

        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

        const period = toolbar.locator('.md-calendar-period');
        const initialPeriod = await period.textContent();
        await toolbar.getByRole('button', { name: 'Periodo siguiente' }).click();
        await expect(period).not.toHaveText(initialPeriod);
        await toolbar.getByRole('button', { name: 'Periodo anterior' }).click();
        await expect(period).toHaveText(initialPeriod);

        await toolbar.getByLabel('Vista:', { exact: true }).selectOption('timeGridWeek');
        await expect(page.locator('.fc-timeGridWeek-view')).toBeVisible();
        const navigation = page.getByRole('navigation', { name: 'Vistas del módulo' });
        await navigation.getByRole('link', { name: 'Lista', exact: true }).click();
        await expect(navigation.getByRole('link', { name: 'Lista', exact: true })).toHaveAttribute('aria-current', 'page');
        await navigation.getByRole('link', { name: 'Calendario', exact: true }).click();
        await expect(page.locator('.fc-timeGridWeek-view')).toBeVisible();
        await page.reload();
        await expect(agenda).toBeVisible();
    });
}

for (const width of [390, 1440]) {
    test(`Agenda starts today and retains future weeks ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.clock.setFixedTime(new Date('2027-01-06T15:00:00-05:00'));
        await page.goto('/login');
        await page.locator('input[type=email]').fill('tasks-calendar-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/calendar');

        const toolbar = page.locator('.md-calendar-compact-toolbar');
        const agenda = page.locator('.fc-listWeek-view');
        const previous = toolbar.getByRole('button', { name: 'Periodo anterior' });
        const next = toolbar.getByRole('button', { name: 'Periodo siguiente' });
        const period = toolbar.locator('.md-calendar-period');
        const select = async (view, label) => width < 768
            ? toolbar.getByLabel('Vista:', { exact: true }).selectOption(view)
            : toolbar.getByRole('button', { name: label, exact: true }).click();

        if (width >= 768) await select('listWeek', 'Agenda');
        await expect(agenda.getByText('Agenda task earlier today')).toBeVisible();
        await expect(agenda.getByText('Agenda ongoing plan').first()).toBeVisible();
        await expect(agenda.getByText('Agenda historical task')).toHaveCount(0);
        await expect(agenda.locator('.fc-list-day').first()).toHaveAttribute('data-date', '2027-01-06');
        await expect(period).toContainText(/6.*10.*2027/);
        await expect(previous).toBeDisabled();

        await next.click();
        await expect(previous).toBeEnabled();
        await expect(period).toContainText(/11.*17.*2027/);
        await expect(agenda.locator('.fc-list-empty')).toBeVisible();
        await select('timeGridWeek', 'Semana');
        await select('listWeek', 'Agenda');
        await expect(period).toContainText(/11.*17.*2027/);
        await previous.click();
        await expect(previous).toBeDisabled();

        await select('timeGridWeek', 'Semana');
        await expect(previous).toBeEnabled();
        await expect(page.locator('.fc-timeGridWeek-view').getByText('Agenda historical task')).toBeVisible();
        await previous.click();
        await select('listWeek', 'Agenda');
        await expect(previous).toBeDisabled();
        await expect(period).toContainText(/6.*10.*2027/);

        await toolbar.getByRole('button', { name: 'Ahora', exact: true }).click();
        await expect(page.locator('.fc-timeGridDay-view')).toBeVisible();
        await expect(previous).toBeEnabled();
    });
}

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
        await expect(page.locator(width < 768 ? '.fc-listWeek-view' : '.fc-timeGridWeek-view')).toBeVisible();
        await expect(toolbar.locator('.md-calendar-period')).not.toBeEmpty();
        await expect(toolbar.getByRole('button', { name: 'Ahora' })).toBeVisible();
        await expect(toolbar.getByRole('button', { name: 'Filtros' })).toBeVisible();
        if (width >= 768) {
            await expect(toolbar.getByRole('group', { name: 'Vista del calendario' })).toBeVisible();
            await expect(toolbar.getByRole('button', { name: 'Semana', exact: true })).toBeVisible();
        } else {
            await expect(toolbar.getByLabel('Vista:', { exact: true })).toBeVisible();
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
            else await toolbar.getByLabel('Vista:', { exact: true }).selectOption(view);
            const viewSurface = page.locator(`.fc-${view}-view`);
            await expect(viewSurface).toBeVisible();
            await expect.poll(() => viewSurface.evaluate(surface =>
                window.Alpine.$data(surface.closest('.md-task-calendar')).calendarLoading
            )).toBe(false);
            const action = viewSurface.getByRole('button', { name: 'Más acciones de Calendar recurring task', exact: true }).first();
            await action.scrollIntoViewIfNeeded();
            await action.focus();
            await expect(action).toBeFocused();
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

for (const width of [1440, 390]) {
    test(`personal calendar filters and reservation editor ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.locator('input[type=email]').fill('tasks-calendar-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/calendar');

        const filter = page.getByRole('button', { name: /^Filtros/ });
        await filter.click();
        const health = page.getByRole('checkbox', { name: 'Salud', exact: true });
        await expect(health).not.toBeChecked();
        await health.check();
        await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
        await expect(filter).toBeFocused();
        await page.reload();
        await filter.click();
        await expect(health).toBeChecked();
        await page.getByRole('button', { name: 'Limpiar', exact: true }).click();
        await page.getByRole('button', { name: 'Filtrar', exact: true }).click();

        await page.getByRole('button', { name: width >= 768 ? 'Más acciones de creación' : 'Nueva tarea', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Reservar tiempo de estudio', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Reservar tiempo de estudio' });
        await expect(dialog).toBeVisible();
        await dialog.getByLabel('Título').fill(`Reserva de prueba ${width}`);
        await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(dialog).toBeHidden();
        const reservation = page.locator('.md-calendar-reservation').filter({ hasText: `Reserva de prueba ${width}` });
        await expect(reservation).toBeVisible();
        await reservation.click();
        await expect(page.getByRole('dialog', { name: 'Editar reserva' })).toBeVisible();
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    });
}
