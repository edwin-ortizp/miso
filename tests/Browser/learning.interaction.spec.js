import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const port = 8087;
const directory = path.resolve('storage/framework/testing');
let server, fixture;
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: `http://127.0.0.1:${port}` });

test.beforeAll(async () => {
    test.setTimeout(120000);
    mkdirSync(directory, { recursive: true });
    const views = path.join(directory, 'learning-browser-views');
    mkdirSync(views, { recursive: true });
    const database = path.join(directory, 'learning-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${port}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: views, CACHE_STORE: 'array', SESSION_DRIVER: 'file', BCRYPT_ROUNDS: '4', DEBUGBAR_ENABLED: 'false',
        TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false', APP_ROUTES_CACHE: path.join(directory, 'learning-no-routes.php'), APP_CONFIG_CACHE: path.join(directory, 'learning-no-config.php') };
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/learning-fixture.php'], { env, encoding: 'utf8', timeout: 110000 }));
    server = spawn('php', ['-S', `127.0.0.1:${port}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: 'ignore' });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`http://127.0.0.1:${port}/login`, {signal: AbortSignal.timeout(3000)})).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    throw Error('Learning test server did not start');
});

test.afterAll(async () => {
    if (server && server.exitCode === null) {
        const stopped = new Promise(resolve => server.once('exit', resolve));
        server.kill();
        await stopped;
    }
});

async function login(page, empty = false) {
    await page.goto('/login');
    await page.locator('input[type=email]').fill(empty ? 'learning-empty@example.test' : 'learning@example.test');
    await page.locator('input[type=password]').fill('learning-test-password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
    await page.goto('/learning');
}

for (const width of [375, 768, 1440]) {
    test(`Learning navigation, menus and accessibility ${width}`, async ({ page }) => {
        test.setTimeout(120000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 1000 });
        await login(page);
        await expect(page.locator('.learning-page')).toBeVisible();
        await expect(page.locator('.game-guardian:visible')).toHaveCount(1);
        const main = await page.locator('.learning-page [data-region=content]').boundingBox();
        const pins = await page.locator('.learning-shortcuts').boundingBox();
        const guardian = await page.locator('.learning-page [data-region=context]').boundingBox();
        if (width < 1200) {
            expect(pins.y + pins.height).toBeLessThanOrEqual(main.y + 1);
            expect(main.y + main.height).toBeLessThanOrEqual(guardian.y + 1);
        } else expect(pins.x).toBeGreaterThanOrEqual(main.x + main.width);
        await page.locator('.learning-shortcuts a').filter({hasText: 'Cálculo diferencial'}).click();
        await expect(page).toHaveURL(new RegExp(`/learning/${fixture.degree}/units/${fixture.subject}`));
        await expect(page.locator('.learning-path')).toContainText('Semestre 1');
        await page.reload();
        await expect(page.locator('.learning-shortcuts')).toContainText('Cálculo diferencial');
        await page.goto('/learning');
        const row = page.getByRole('row').filter({hasText: 'Ingeniería de Sistemas'});
        const trigger = row.getByRole('button', { name: 'Acciones de Ingeniería de Sistemas', exact: true });
        await trigger.click();
        const menu = row.locator('[role=menu]:visible');
        await expect(menu).toBeVisible();
        const bounds = await menu.boundingBox();
        expect(bounds.x).toBeGreaterThanOrEqual(0);
        expect(bounds.x + bounds.width).toBeLessThanOrEqual(width + 1);
        await menu.getByRole('menuitem', {name: 'Editar', exact: true}).click();
        const dialog = page.getByRole('dialog', {name: 'Editar pregrado'});
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', {name:'Organización', exact:true}).click();
        await expect(dialog.getByLabel('Institución o plataforma')).toBeVisible();
        await expect(dialog.locator(':focus')).toHaveCount(1);
        await page.screenshot({path: `${directory}/learning-dialog-${width}.png`, fullPage: true});
        const audit = await new AxeBuilder({page}).include('#learning-editor').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
        expect(audit.violations).toEqual([]);
        await page.keyboard.press('Escape');
        // The canonical dirty guard only prompts when there are changes.
        await expect(dialog).toHaveCount(0);
        await expect(trigger).toBeFocused();
        for (const theme of ['light', 'dark']) {
            await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
            await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            const audit = await new AxeBuilder({page}).include('.learning-page').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
            expect(audit.violations).toEqual([]);
            await page.screenshot({path:`${directory}/learning-${width}-${theme}.png`, fullPage:true});
        }
        expect(errors).toEqual([]);
    });
}

test('create, validate, persist, pin and delete from an empty account', async ({page}) => {
    test.setTimeout(120000);
    await page.setViewportSize({width:1440,height:1000});
    await login(page, true);
    await expect(page.getByText('Aún no tienes formaciones')).toBeVisible();
    await expect(page.locator('.game-guardian:visible')).toHaveCount(0);
    const fab = page.getByRole('button', {name:'Agregar formación', exact:true});
    await fab.click();
    const dialog = page.locator('#learning-editor');
    await dialog.getByRole('button', {name:'Guardar',exact:true}).click();
    await expect(dialog.locator('[aria-invalid=true]').first()).toBeVisible();
    await dialog.getByLabel('Tipo de formación').selectOption('course');
    await dialog.getByLabel('Nombre', {exact:false}).fill('Curso real de prueba');
    await dialog.getByRole('button', {name:'Guardar',exact:true}).click();
    await expect(page.locator('#learning-editor')).toHaveCount(0);
    await expect(page.getByRole('link',{name:'Curso real de prueba',exact:true})).toBeVisible();
    await page.reload();
    await page.getByRole('link',{name:'Curso real de prueba',exact:true}).click();
    await page.getByRole('button',{name:'Fijar en accesos directos',exact:true}).click();
    await expect(page.locator('.learning-shortcuts')).toContainText('Curso real de prueba');
    await page.reload();
    await expect(page.locator('.learning-shortcuts')).toContainText('Curso real de prueba');
    await page.getByRole('button',{name:'Acciones del elemento',exact:true}).click();
    page.once('dialog', d => d.accept());
    await page.getByRole('menuitem',{name:'Eliminar',exact:true}).click();
    await expect(page).toHaveURL(/\/learning$/);
    await expect(page.getByText('Aún no tienes formaciones')).toBeVisible();
});
