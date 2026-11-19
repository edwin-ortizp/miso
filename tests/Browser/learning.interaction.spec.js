import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const port = Number(process.env.MISO_LEARNING_TEST_PORT || 8087);
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
        await expect(page.locator('.learning-summary')).toContainText('Materia');
        await page.reload();
        await expect(page.locator('.learning-shortcuts')).toContainText('Cálculo diferencial');
        await page.goto('/learning');
        const row = page.getByRole('row').filter({hasText: 'Ingeniería de Sistemas'});
        const title = row.locator('.learning-record-link span').first();
        const institution = row.locator('.md-table__meta');
        await expect(institution).toHaveText('UNAL');
        await page.evaluate(() => document.fonts.ready);
        const titleBounds = await title.boundingBox();
        const institutionBounds = await institution.boundingBox();
        expect(Math.abs(institutionBounds.x - titleBounds.x)).toBeLessThanOrEqual(2);
        expect(institutionBounds.y - (titleBounds.y + titleBounds.height)).toBeLessThanOrEqual(4);
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
        await page.goto(`/learning/${fixture.degree}`);
        const subjectRow = page.getByRole('row').filter({ hasText: 'Introducción a la ingeniería' });
        const subjectTrigger = subjectRow.getByRole('button', { name: 'Acciones de Introducción a la ingeniería' });
        if (width === 375) {
            const table = page.locator('.learning-table-wrap');
            await table.evaluate(element => { element.scrollLeft = element.scrollWidth; });
            await expect.poll(() => table.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
        }
        await subjectTrigger.click();
        const subjectMenu = subjectRow.getByRole('menu');
        await expect(subjectMenu).toBeVisible();
        const lastAction = subjectMenu.getByRole('menuitem', { name: 'Eliminar', exact: true });
        await expect(lastAction).toBeInViewport();
        await lastAction.click({ trial: true });
        await page.keyboard.press('Escape');
        await expect(subjectMenu).toBeHidden();
        await expect(subjectTrigger).toBeFocused();
        await subjectTrigger.click();
        await subjectMenu.getByRole('menuitem', { name: 'Cambiar semestre' }).click();
        await expect(page.getByRole('dialog', { name: 'Editar materia' }).getByLabel('Semestre', { exact: true })).toBeVisible();
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

for (const width of [390, 768, 1440]) {
    test('Academic dashboard, quick capture and projection '+width, async ({ page }) => {
        test.setTimeout(90000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 950 });
        const academic = fixture.academic[width];
        await login(page);
        await page.getByRole('link', { name: 'Mi estudio', exact: true }).click();
        await expect(page).toHaveURL(/learning\/study$/);
        const study = page.locator('.learning-page');
        await expect(study.getByRole('heading', { name: 'Agenda', exact: true })).toBeVisible();
        await study.getByLabel('Semestre', { exact: true }).selectOption(academic.period);

        const fab = page.getByRole('button', { name: 'Agregar tarea', exact: true });
        await fab.click();
        const dialog = page.getByRole('dialog', { name: 'Agregar tarea', exact: true });
        await expect(dialog).toBeVisible();
        await expect(dialog.getByLabel('Materia', { exact: false })).toHaveValue(academic.subject);
        const task = 'Entrega académica '+width;
        const date = await page.evaluate(() => new Intl.DateTimeFormat('sv-SE', {
            timeZone: 'America/Bogota', year: 'numeric', month: '2-digit', day: '2-digit',
        }).format(new Date()));
        await dialog.getByLabel('Título', { exact: false }).fill(task);
        await dialog.getByLabel('Fecha de entrega (opcional)').fill(date);
        await dialog.getByLabel('Cuenta para la nota').check();
        await dialog.getByLabel('Peso en la materia (%)').fill('30');
        await expect(dialog.getByRole('button', { name: 'Guardar' })).toBeInViewport();
        await page.screenshot({path: directory+'/study-capture-'+width+'.png'});
        await dialog.getByRole('button', { name: 'Guardar' }).click();
        await expect(dialog).toHaveCount(0);
        await expect(study.getByRole('status').filter({ hasText: 'Tarea y evaluación guardadas.' })).toBeVisible();
        await expect(study.getByRole('button', { name: task, exact: true })).toBeVisible();
        await expect(study.getByRole('link', { name: 'Mi estudio', exact: true })).toHaveAttribute('aria-current', 'page');
        await expect(fab).toBeFocused();
        await page.screenshot({path: directory+'/study-dashboard-'+width+'.png', fullPage: true});

        await study.getByRole('button', { name: 'Completar '+task, exact: true }).click();
        await expect(study.getByRole('button', { name: task, exact: true })).toHaveCount(0);
        await page.goto('/learning/'+fixture.degree+'/units/'+academic.subject);
        await page.getByRole('link', { name: 'Notas', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Calificación', exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Registrar nota de '+task, exact: true }).click();
        const score = page.getByRole('dialog', { name: 'Registrar nota', exact: true });
        await score.getByLabel('Nota', { exact: true }).fill('4,20');
        await score.getByRole('button', { name: 'Guardar' }).click();
        await expect(score).toHaveCount(0);
        await expect(page.getByText('4,20', { exact: true }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Editar nota de '+task, exact: true })).toBeFocused();

        if (width === 390) {
            await page.getByRole('button', { name: 'Agregar evaluación', exact: true }).click();
            await page.getByRole('menuitem', { name: 'Agregar evaluación', exact: true }).click();
            const exam = page.getByRole('dialog', { name: 'Agregar evaluación', exact: true });
            await exam.getByLabel('Título', { exact: false }).fill('Parcial de práctica');
            await exam.getByLabel('Fecha (opcional)').fill(date);
            await exam.getByLabel('Peso en la materia (%)').fill('70');
            await exam.getByRole('button', { name: 'Guardar' }).click();
            await expect(page.getByText('Necesitas 2,49 para pasar', { exact: true })).toBeVisible();
            await page.getByRole('button', { name: 'Simular notas', exact: true }).click();
            const simulator = page.getByRole('dialog', { name: 'Simular notas', exact: true });
            await simulator.getByLabel('Parcial de práctica', { exact: true }).fill('3');
            await simulator.getByRole('button', { name: 'Calcular', exact: true }).click();
            await expect(simulator.getByRole('status')).toContainText('Nota simulada 3,36');
            await page.keyboard.press('Escape');
            await expect(simulator).toHaveCount(0);
            await expect(page.getByRole('button', { name: 'Simular notas', exact: true })).toBeFocused();
            await expect(page.getByText('Necesitas 2,49 para pasar', { exact: true })).toBeVisible();

            await page.goto('/tasks/calendar');
            const event = page.locator('.fc-event').filter({ hasText: 'Parcial de práctica' });
            await expect(event).toBeVisible();
            await page.getByRole('button', { name: 'Filtros', exact: true }).click();
            await page.getByLabel('Evaluaciones', { exact: true }).uncheck();
            await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
            await expect(event).toBeHidden();
            await page.reload();
            await expect(event).toBeHidden();
            await page.getByRole('button', { name: /^Filtros/ }).click();
            await page.getByLabel('Evaluaciones', { exact: true }).check();
            await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
            await event.press('Enter');
            await expect(page.locator('.learning-grade-row--selected')).toContainText('Parcial de práctica');
        }
        for (const theme of ['light', 'dark']) {
            await page.evaluate(value => { document.documentElement.dataset.theme = value; }, theme);
            await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            const audit = await new AxeBuilder({ page }).include('.learning-page').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
            expect(audit.violations).toEqual([]);
            await page.screenshot({path: directory+'/study-grades-'+width+'-'+theme+'.png', fullPage: true});
        }
        if (width === 768) {
            await page.goto('/learning/'+fixture.degree+'/units/'+fixture.subject+'?section=grades');
            await page.getByRole('button', { name: 'Configurar calificación', exact: true }).click();
            const settings = page.getByRole('dialog', { name: 'Configurar calificación', exact: true });
            await settings.getByLabel('Esquema', { exact: true }).selectOption('cuts');
            await settings.getByRole('button', { name: 'Guardar', exact: true }).click();
            await expect(settings).toHaveCount(0);
            await page.locator('.md-fab-split__toggle:visible, .md-fab-split__compact:visible').click();
            await page.getByRole('menuitem', { name: 'Agregar corte', exact: true }).click();
            const cut = page.getByRole('dialog', { name: 'Agregar corte', exact: true });
            await cut.getByLabel('Título', { exact: false }).fill('Primer corte');
            await cut.getByLabel('Peso del corte en la materia (%)', { exact: false }).fill('30');
            await cut.getByRole('button', { name: 'Guardar', exact: true }).click();
            await expect(page.getByRole('heading', { name: 'Primer corte · 30 % de la materia' })).toBeVisible();
            await expect(page.getByText('Falta definir 70 % del peso de la materia entre sus cortes.')).toBeVisible();
        }
        expect(errors).toEqual([]);
    });
}

test('study reservation starts with the current formation and unit', async ({ page }) => {
    await login(page);
    await page.locator('.learning-shortcuts a').filter({ hasText: 'Cálculo diferencial' }).click();
    await expect(page).toHaveURL(new RegExp(`/learning/${fixture.degree}/units/${fixture.subject}`));
    await page.getByRole('button', { name: 'Más acciones de creación', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Reservar tiempo de estudio', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Reservar tiempo de estudio' });
    const formation = dialog.getByRole('combobox', { name: 'Formación (opcional)' });
    await expect(formation).toHaveValue('Ingeniería de Sistemas');
    await expect(dialog.getByRole('combobox', { name: 'Unidad (opcional)' })).toHaveValue('Cálculo diferencial');
    await formation.fill('ingenieria');
    await expect(dialog.getByRole('option', { name: 'Ingeniería de Sistemas' })).toBeVisible();
    await formation.press('Escape');
    await expect(formation).toHaveValue('Ingeniería de Sistemas');
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await expect(dialog).toBeHidden();
});
