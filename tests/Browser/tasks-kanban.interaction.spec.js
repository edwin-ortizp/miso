import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const PORT = 8034;
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

let server;

test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    const output = path.join(directory, 'playwright', 'tasks-kanban');
    mkdirSync(output, { recursive: true });
    const database = path.join(directory, 'tasks-kanban-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = {
        ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false',
        NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-tasks-kanban-config.php'),
    };
    execFileSync('php', ['tests/Browser/support/tasks-kanban-fixture.php'], { env, stdio: 'pipe' });
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

for (const width of [1440, 390]) {
    test(`kanban grouping ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.locator('input[type=email]').fill('tasks-kanban-browser@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/kanban');
        const group = page.getByLabel('Agrupar por');
        await expect(group).toHaveValue('state');
        await expect(page.locator('[wire\\:key^="kanban-column-state-"]')).toHaveCount(4);
        await group.focus();
        await page.keyboard.press('p');
        await page.keyboard.press('Enter');
        await expect(group).toHaveValue('priority');
        await expect(page).toHaveURL(/group=priority/);
        await expect(page.getByText('Urgente + Importante', { exact: true })).toBeVisible();
        await page.reload();
        await expect(group).toHaveValue('priority');
        await group.selectOption('state');
        await expect(page.locator('[wire\\:key^="kanban-column-state-"]')).toHaveCount(4);
        await expect(page.locator('.md-mcard__tools').getByLabel('Agrupar por')).toBeVisible();
        const card = page.locator('.task-kanban-card').filter({ hasText: 'Kanban browser task' });
        await expect(card.locator('.task-kanban-card__category')).toHaveText('Trabajo de prueba');
        await expect(card.getByLabel('Sin estado anterior', { exact: true })).toBeDisabled();
        await card.getByRole('button', { name: 'Mover a Ready', exact: true }).click();
        await expect(card.locator('.task-kanban-card__status')).toHaveText('Ready');
        await card.getByRole('button', { name: 'Mover a WIP', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(card.locator('.task-kanban-card__status')).toHaveText('WIP');
        await card.getByRole('button', { name: 'Mover a Ready', exact: true }).click();
        await expect(card.locator('.task-kanban-card__status')).toHaveText('Ready');
        expect(await card.locator('footer').evaluate(el => getComputedStyle(el).borderTopWidth)).toBe('1px');
        await card.getByRole('button', { name: 'Mover a Borrador', exact: true }).click();
        await expect(card.locator('.task-kanban-card__status')).toHaveText('Borrador');
        const violations = (await analyze(page, 'main')).violations;
        expect(describeViolations(violations)).toBe('');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/playwright/tasks-kanban/kanban-${width}.png`, fullPage: true });
    });
}
