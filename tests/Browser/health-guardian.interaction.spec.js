import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8083;
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
    throw Error('Health guardian server did not start');
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


for (const width of [390, 1440]) {
 test(`guardianes de salud a ${width}`, async ({page}) => {
    test.setTimeout(120000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({width, height:900});
    await login(page);
    for (const section of ['', '/body', '/measurements', '/medications', '/sleep', '/cycle', '/trends']) {
        await page.goto('/health' + section);
        const guardian = page.locator('.game-guardian:visible');
        await expect(guardian).toHaveCount(1);
        await expect(guardian).toContainText('Compañero de prueba');
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        if (width === 1440) {
            const rail = await page.locator('[data-region="context"]').boundingBox();
            const main = await page.locator('[data-region="content"]').boundingBox();
            expect(rail.x).toBeGreaterThan(main.x + main.width - 1);
            if (section) {
                const panel = page.locator('[data-health-context]');
                const heading = await panel.locator('h2').boundingBox();
                const card = await guardian.boundingBox();
                expect(card.y).toBeGreaterThan(heading.y + heading.height);
                expect(card.x).toBeGreaterThanOrEqual(rail.x - 1);
                expect(card.x + card.width).toBeLessThanOrEqual(rail.x + rail.width + 1);
            }
        }
        if (section === '/body') {
            await page.locator('svg[data-body-model="male"][data-body-side="front"] [data-zone="chest"]').focus();
            await page.keyboard.press('Enter');
            await expect(guardian).toContainText('Pecho: sin eventos');
        }
        if (section === '/trends') {
            await page.getByRole('button', {name:'90 días', exact:true}).click();
            await expect(guardian).toContainText('1 registro en 1 día');
            await page.getByRole('button', {name:'30 días', exact:true}).click();
            await expect(guardian).toContainText('Aún no hay datos');
        }
        await guardian.getByRole('button', {name:'Otro consejo'}).click();
        await guardian.getByRole('button', {name:'Ver ficha de Compañero de prueba'}).focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('[role=dialog]:visible')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('[role=dialog]:visible')).toHaveCount(0);
        const accessibility = await new AxeBuilder({page}).include('.game-guardian').analyze();
        expect(accessibility.violations).toEqual([]);
        await page.screenshot({path:`storage/framework/testing/playwright/health-${section.replace('/', '') || 'index'}-${width}.png`, fullPage:true});
    }
    expect(errors).toEqual([]);
 });
}
