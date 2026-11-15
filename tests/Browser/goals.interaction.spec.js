import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8067;
const directory = path.resolve('storage/framework/testing');
const database = path.join(directory, 'goals-browser.sqlite');
let server, fixture;
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: `http://127.0.0.1:${PORT}` });

test.beforeAll(async () => {
    mkdirSync(directory, { recursive: true });
    const compiledViews = path.join(directory, 'goals-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${PORT}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        GAME_IMAGE_BASE_URL: '', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        APP_CONFIG_CACHE: path.join(directory, 'unused-companion-voice-config.php') };
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/goals-fixture.php'], { env, encoding: 'utf8' }));
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    throw Error('Companion voice server did not start');
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


for (const width of [375, 768, 1280]) {
 test(`objetivos: filtros y compañero a ${width}`, async ({page}) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.setViewportSize({width, height:900});
    await login(page);
    await page.goto('/goals');
    await page.getByRole('button', {name:'Filtros', exact:true}).click();
    const filters = page.locator('#goals-filters');
    await expect(filters).toBeVisible();
    await filters.getByRole('button', {name:'Completados', exact:true}).click();
    await expect(page).toHaveURL(/status=completed/);
    await filters.getByRole('button', {name:'Filtrar', exact:true}).click();
    await expect(filters).toBeHidden();
    await page.getByRole('button', {name:'Limpiar filtros', exact:true}).click();
    await expect(page).toHaveURL(/status=all/);
    await page.locator('.lt-chat__launcher').click();
    const panel = page.locator('body > .lt-chat-host .lt-chat__panel');
    await expect(panel).toBeVisible();
    await panel.getByRole('button', {name:'Agregar objetivo', exact:true}).click();
    await expect(page.getByRole('dialog', {name:'Agregar objetivo', exact:true})).toBeVisible();
    await page.keyboard.press('Escape');
    await page.locator(`a[href$="/goals/${fixture.goal}"]`).click();
    await expect(page.getByRole('heading', {name:'Hábitos relacionados', exact:true})).toBeVisible();
    await page.locator('.lt-chat__launcher').click();
    await expect(panel).toBeVisible();
    await panel.getByRole('button', {name:'Vincular hábitos', exact:true}).click();
    const links = page.getByRole('dialog', {name:'Vincular hábitos', exact:true});
    await expect(links).toBeVisible();
    await links.getByLabel('Leer', {exact:true}).check();
    await links.getByRole('button', {name:'Guardar vínculos', exact:true}).click();
    await expect(links).toBeHidden();
    await expect(page.getByText('Pendiente hoy', {exact:false})).toBeVisible();
    if (width === 768) await expect.poll(() => page.locator('#app-navigation').evaluate(el => el.getBoundingClientRect().width)).toBeLessThan(120);
    await page.screenshot({path:`storage/framework/testing/playwright/goals/detail-${width}.png`, fullPage:true, animations:'disabled'});
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', {name:'Desvincular Leer', exact:true}).click();
    await expect(page.getByText('Pendiente hoy', {exact:false})).toBeHidden();
    expect(errors).toEqual([]);
 });
}


test('crear desde objetivo usa los editores compartidos y conserva el contexto al navegar', async ({page}) => {
    await page.setViewportSize({width:1280, height:900});
    await login(page);
    await page.goto(`/goals/${fixture.goal}`);
    const launcher = page.locator('.lt-chat__launcher');
    const panel = page.locator('body > .lt-chat-host .lt-chat__panel');
    await launcher.click();
    await panel.getByRole('button', {name:'Crear hábito', exact:true}).click();
    const habit = page.getByRole('dialog', {name:'Nuevo hábito', exact:true});
    await expect(habit).toBeVisible();
    await habit.getByLabel('Nombre', {exact:false}).fill('Hábito creado en objetivo');
    await habit.getByRole('button', {name:'Crear hábito', exact:true}).click();
    await expect(habit).toBeHidden();
    await expect(page.locator('.goal-related-open').filter({hasText:'Hábito creado en objetivo'})).toBeVisible();
    await launcher.click();
    await panel.getByRole('button', {name:'Crear tarea', exact:true}).click();
    const task = page.getByRole('dialog', {name:'Nueva tarea', exact:true});
    await expect(task).toBeVisible();
    await task.getByRole('textbox', {name:/Título/}).fill('Tarea creada en objetivo');
    await task.getByRole('button', {name:'Guardar', exact:true}).click();
    await expect(page.getByRole('dialog', {name:'Editar tarea', exact:true})).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByText('Tarea creada en objetivo', {exact:false}).first()).toBeVisible();
    const axe = await new AxeBuilder({page}).include('.goal-detail').analyze();
    expect(axe.violations).toEqual([]);
    await page.getByRole('link', {name:'Volver a objetivos', exact:true}).click();
    await expect(page).toHaveURL(url => url.pathname === '/goals');
    await page.getByRole('button', {name:'Agregar objetivo con Compañero de prueba', exact:true}).click();
    await expect(panel.getByRole('button', {name:'Agregar objetivo', exact:true})).toBeVisible();
    await expect(page.locator('body > .lt-chat-host')).toHaveCount(1);
});
