import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

let server;
test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'game-balls-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8038', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-game-balls-config.php') };
    execFileSync('php', ['tests/Browser/support/game-balls-fixture.php'], { env, encoding: 'utf8' }).trim();
    server = spawn('php', ['-S', '127.0.0.1:8038', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch('http://127.0.0.1:8038/login')).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Testing server did not start');
});
test.afterAll(() => server?.kill());
test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico', { exact: true }).fill('balls@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
});


for (const theme of ['light', 'dark']) {
for (const width of [360, 768, 1440]) {
    test(`Balls y álbumes ${width}px ${theme}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/game');
        await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
        await expect(page.locator('.game-streak__days')).toBeVisible();
        await expect(page.locator('.game-challenge-quest')).toBeVisible();
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        const help = page.getByRole('button', { name: 'Cómo funciona la racha', exact: true });
        await help.focus();
        await page.keyboard.press('Enter');
        const dialog = page.getByRole('dialog', { name: 'Cómo funciona la racha' });
        await expect(dialog).toBeVisible();
        await expect(dialog.getByText(/Cada día necesitas/)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await expect(help).toBeFocused();
        const violations = await new AxeBuilder({ page }).include('.game-balls-summary').analyze();
        expect(violations.violations).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/balls-${width}-${theme}.png`, fullPage: true });
        await page.goto('/game/collection');
        const rail = page.locator('.game-albums__rail');
        await expect(rail).toBeVisible();
        expect(await rail.evaluate(el => getComputedStyle(el).paddingBottom)).toBe('12px');
        expect(await rail.evaluate(el => el.clientHeight - (el.firstElementChild.getBoundingClientRect().bottom - el.getBoundingClientRect().top))).toBeGreaterThanOrEqual(12);
        await page.screenshot({ path: `storage/framework/testing/albums-${width}-${theme}.png`, fullPage: true });
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await rail.evaluate(el => el.scrollLeft = el.scrollWidth);
        expect(await rail.evaluate(el => el.scrollLeft)).toBeGreaterThan(0);
    });
}

}

test('apertura y revelación conservan su interacción', async ({ page }) => {
    await page.goto('/game');
    await page.getByRole('button', { name: /^Abrir Poké Ball de/ }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Tu descubrimiento' });
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('.game-discovery--revealed')).toBeVisible({ timeout: 10000 });
    await expect(dialog.getByRole('button', { name: /Abrir otra/ })).toBeVisible();
    await dialog.getByRole('button', { name: 'Cerrar', exact: true }).click();
    await expect(dialog).not.toBeVisible();
});

test('resumen compacto y cinco o seis balls por fila según el ancho', async ({ page }) => {
    for (const [width, columns] of [[1680, 5], [1920, 6]]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/game');
        await expect(page.locator('.game-ball-card')).toHaveCount(6);
        await expect.poll(() => page.locator('.game-balls').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length)).toBe(columns);
        const summary = await page.locator('.game-balls-summary').boundingBox();
        expect(summary.height).toBeLessThan(280);
        const cards = await page.locator('.game-ball-card').evaluateAll(els => els.map(el => ({ top: el.getBoundingClientRect().top, width: el.getBoundingClientRect().width })));
        expect(cards.filter(card => Math.abs(card.top - cards[0].top) < 2)).toHaveLength(columns);
        expect(cards[0].width).toBeGreaterThanOrEqual(220);
        await page.screenshot({ path: `storage/framework/testing/balls-compact-${width}.png`, fullPage: true });
    }
});
