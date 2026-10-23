import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const PORT = 8033;
const RULES = [
    'aria-allowed-attr', 'aria-required-attr', 'aria-roles', 'aria-valid-attr-value', 'button-name',
    'color-contrast', 'duplicate-id-aria', 'form-field-multiple-labels', 'heading-order', 'label',
    'link-name', 'list', 'listitem', 'select-name',
];
const describeViolations = (violations) => violations
    .map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)
    .join('\n');
const analyze = (page, include) => {
    const builder = new AxeBuilder({ page }).withRules(RULES).exclude('.phpdebugbar');

    return (include ? builder.include(include) : builder).analyze();
};

async function selectView(page, width, view) {
    if (width >= 768) return page.locator(`.fc-${view}-button`).click();
    await page.getByRole('button', { name: 'Cambiar vista del calendario' }).click();
    await page.getByRole('menuitem', { name: { timeGridDay: 'Día', timeGridWeek: 'Semana', dayGridMonth: 'Mes', listWeek: 'Agenda' }[view], exact: true }).click();
}

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
    test(`calendar status interactions ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/login');
        await page.locator('input[type=email]').fill('tasks-calendar-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/calendar');
        if (width < 768) await selectView(page, width, 'timeGridWeek');
        const event = page.locator('.fc-event').filter({ hasText: 'Calendar browser task' }).first();
        await event.click();
        const popover = page.locator('#task-calendar-quick');
        await expect(popover).toBeVisible();
        const trigger = popover.locator('[aria-haspopup=menu]');
        await trigger.click();
        const menu = page.locator('#task-calendar-status-menu');
        await expect(menu).toBeVisible();
        const box = await menu.boundingBox();
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.y).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(width);
        expect(box.y + box.height).toBeLessThanOrEqual(900);
        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
        await expect(trigger).toBeFocused();
        await trigger.press('ArrowDown');
        await expect(menu.locator('button:visible').first()).toBeFocused();
        const target = (await popover.locator('.md-task-status-split__label').innerText()).trim() === 'WIP' ? 'Ready' : 'WIP';
        await menu.getByRole('menuitem', { name: target, exact: true }).click();
        await expect(popover).toBeHidden();
        await event.click();
        await expect(popover.locator('.md-task-status-split__label')).toHaveText(target);
        expect(await popover.locator('header').evaluate(el => getComputedStyle(el).borderBottomWidth)).toBe('1px');
        expect(await popover.locator('footer').evaluate(el => getComputedStyle(el).borderTopWidth)).toBe('1px');
        const popBox = await popover.boundingBox();
        expect(popBox.y).toBeGreaterThanOrEqual(0);
        expect(popBox.y + popBox.height).toBeLessThanOrEqual(901);
        const result = await analyze(page, '#task-calendar-quick');
        expect(result.violations, describeViolations(result.violations)).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/calendar-status-${width}.png` });
        await trigger.focus();
        await page.keyboard.press('Escape');
        await expect(popover).toBeHidden();
        await event.click();
        await page.mouse.click(5, 100);
        await expect(popover).toBeHidden();
        await event.click();
        await popover.locator('button').filter({ hasText: 'Editar' }).click();
        await expect(popover).toBeHidden();
        await expect(page.locator('[role=dialog]:visible').first()).toBeVisible();
        expect(errors).toEqual([]);
    });
}

for (const [width, height] of [[390, 844], [768, 900], [1440, 900], [740, 390]]) {
    test(`calendar navigation and Now ${width}x${height}`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        await page.clock.install({ time: new Date('2026-10-22T14:45:00-05:00') });
        await page.goto('/login');
        await page.locator('input[type=email]').fill('tasks-calendar-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/calendar');
        await expect(page.locator(width < 768 ? '.fc-timeGridDay-view' : '.fc-timeGridWeek-view')).toBeVisible();
        const now = page.getByRole('button', { name: 'Ahora', exact: true });
        for (const view of ['timeGridDay', 'timeGridWeek', 'dayGridMonth', 'listWeek']) {
            await selectView(page, width, view);
            await (width < 768 ? page.getByRole('button', { name: 'Periodo anterior' }) : page.locator('.fc-prev-button')).click();
            await now.click();
            await expect(page.locator(view === 'timeGridWeek' ? '.fc-timeGridWeek-view' : '.fc-timeGridDay-view')).toBeVisible();
            await expect(page.locator('.fc-day-today').first()).toBeVisible();
            await expect(async () => {
                const line = page.locator('.fc-timegrid-now-indicator-line').first();
                const lineBox = await line.boundingBox();
                const scroller = await page.locator('.fc-scroller').filter({ has: page.locator('.fc-timegrid-body') }).boundingBox();
                expect(lineBox.y).toBeGreaterThanOrEqual(scroller.y);
                expect(lineBox.y).toBeLessThan(scroller.y + scroller.height);
                expect(lineBox.y).toBeLessThan(height - (width < 768 ? 80 : 0));
            }).toPass();
            await now.click();
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        for (const button of await page.locator(width < 768 ? '.md-calendar-mobile-toolbar button:visible' : '.fc-header-toolbar button').all()) {
            expect((await button.boundingBox()).height).toBeGreaterThanOrEqual(48);
        }
        if (width < 768) {
            const toolbar = page.locator('.md-calendar-mobile-toolbar');
            expect((await toolbar.boundingBox()).height).toBe(48);
            await page.getByRole('button', { name: 'Más opciones del calendario' }).click();
            await expect(page.getByRole('menuitem', { name: 'Ir a hoy', exact: true })).toBeVisible();
            await page.getByRole('menuitem', { name: 'Actualizar', exact: true }).click();
            await expect(page.getByRole('menuitem', { name: 'Actualizar', exact: true })).toBeHidden();
        }
        const result = await analyze(page, '.md-task-calendar');
        expect(result.violations, describeViolations(result.violations)).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/calendar-now-${width}.png` });
        await page.setViewportSize({ width: height, height: width });
        await expect(page.locator('.fc-timeGridDay-view')).toBeVisible();
    });
}
