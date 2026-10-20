import { test, expect } from '@playwright/test';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

// Modal de tareas en escritorio: teclado en fechas, barra Markdown y relaciones.
const BASE = 'http://127.0.0.1:8032';
let server;
test.describe.configure({ mode: 'serial' });
test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'layout-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-task-editor-config.php') };
    execFileSync('php', ['tests/Browser/support/layout-fixture.php'], { env, encoding: 'utf8' });
    server = spawn('php', ['-S', '127.0.0.1:8032', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`${BASE}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Testing server did not start');
});
test.afterAll(() => server?.kill());
test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.getByLabel('Correo electrónico', { exact: true }).fill('layout@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
    await page.goto(`${BASE}/tasks/list`);
    await page.getByRole('button', { name: 'Nueva tarea', exact: true }).click();
    // En móvil el FAB despliega un menú de creación.
    if (page.viewportSize().width < 768) await page.getByRole('menuitem', { name: 'Nueva tarea', exact: true }).click();
});

async function taskSection(dialog, name) {
    const nav = dialog.getByRole('navigation', { name: 'Secciones del formulario' });
    if (!await nav.isVisible()) await dialog.getByRole('button', { name: 'Mostrar u ocultar secciones' }).click();
    await nav.getByRole('button', { name: new RegExp('^' + name) }).click();
}

test('las fechas y horas se escriben con teclado', async ({ page }) => {
    const dialog = page.locator('#task-editor');
    await dialog.getByRole('textbox', { name: 'Título', exact: true }).fill('Tarea por teclado');
    await taskSection(dialog, 'Programación');
    const start = dialog.getByLabel('Fecha inicio', { exact: true });
    await start.focus();
    await page.keyboard.type('21102026');
    // El año admite seis dígitos: el primer Tab sale del segmento y el segundo pasa a la hora.
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await expect(dialog.locator('#task-editor-start-time')).toBeFocused();
    await page.keyboard.type('0930a');
    await page.keyboard.press('Tab');
    await expect(start).toHaveValue('2026-10-21');
    await expect(dialog.locator('#task-editor-start-time')).toHaveValue('09:30');
});

for (const editing of [false, true]) test(`la barra Markdown aplica formato${editing ? ' al editar' : ''}`, async ({ page }) => {
    const dialog = page.locator('#task-editor');
    if (editing) {
        await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await page.getByRole('button', { name: 'Abrir tarea: Tarea de prueba de distribución' }).click();
        await expect(dialog.getByRole('textbox', { name: 'Título', exact: true })).toHaveValue('Tarea de prueba de distribución');
        await dialog.locator('[data-markdown-mode="edit"]').click();
    }
    const source = dialog.locator('[data-markdown-source]');
    await source.fill('hola');
    await source.evaluate(el => el.setSelectionRange(0, 4));
    await dialog.locator('[data-markdown-snippet="bold"]').click();
    await expect(source).toHaveValue('**hola**');
    await source.evaluate(el => el.setSelectionRange(el.value.length, el.value.length));
    await dialog.locator('[data-markdown-snippet="italic"]').click();
    await expect(source).toHaveValue('**hola**_texto_');
});

test('el editor sigue funcionando tras programar con clicks', async ({ page }) => {
    const dialog = page.locator('#task-editor');
    await taskSection(dialog, 'Programación');
    const start = dialog.getByLabel('Fecha inicio', { exact: true });
    await start.click({ position: { x: 24, y: 28 } });
    await page.keyboard.type('21102026');
    await dialog.locator('#task-editor-end').click({ position: { x: 24, y: 28 } });
    await page.keyboard.type('22102026');
    await dialog.locator('#task-editor-start-time').click({ position: { x: 24, y: 28 } });
    await page.keyboard.type('0930a');
    await page.waitForTimeout(800);
    await expect(start).toHaveValue('2026-10-21');
    await expect(dialog.locator('#task-editor-end')).toHaveValue('2026-10-22');
    await expect(dialog.locator('#task-editor-start-time')).toHaveValue('09:30');
    await taskSection(dialog, 'Información básica');
    const source = dialog.locator('[data-markdown-source]');
    await source.fill('hola');
    await source.evaluate(el => el.setSelectionRange(0, 4));
    await dialog.locator('[data-markdown-snippet="bold"]').click();
    await expect(source).toHaveValue('**hola**');
});

test('la barra Markdown pasa a edición desde la vista previa', async ({ page }) => {
    const dialog = page.locator('#task-editor');
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await page.getByRole('button', { name: 'Abrir tarea: Tarea de prueba de distribución' }).click();
    await expect(dialog.getByRole('textbox', { name: 'Título', exact: true })).toHaveValue('Tarea de prueba de distribución');
    const source = dialog.locator('[data-markdown-source]');
    await expect(source).toBeHidden();
    await dialog.locator('[data-markdown-snippet="bold"]').click();
    await expect(source).toBeVisible();
    await expect(source).toHaveValue('**texto**');
});

test('la pestaña Relacionado vincula registros por módulo', async ({ page }) => {
    const dialog = page.locator('#task-editor');
    await dialog.getByRole('textbox', { name: 'Título', exact: true }).fill('Tarea con relación');
    await taskSection(dialog, 'Relacionado');
    await dialog.locator('#task-editor-relation-type-0').selectOption('goal');
    const relation = dialog.locator('#task-editor-relation-id-0');
    // El módulo viaja al servidor y el combobox se recrea con sus opciones.
    await expect(relation.locator('xpath=..').locator('.md-combobox__empty')).toHaveText('Sin resultados');
    await relation.click();
    await relation.locator('xpath=..').locator('li.md-combobox__option', { hasText: 'Meta de prueba' }).click();
    await dialog.getByRole('button', { name: 'Añadir relación' }).click();
    await expect(dialog.locator('#task-editor-relation-type-1')).toHaveValue('');
    await expect(relation).toHaveValue('Meta de prueba');
    await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(dialog).toBeHidden();
    await page.getByRole('button', { name: 'Abrir tarea: Tarea con relación' }).click();
    await taskSection(dialog, 'Relacionado');
    await expect(dialog.locator('#task-editor-relation-type-0')).toHaveValue('goal');
    await expect(dialog.locator('#task-editor-relation-id-0')).toHaveValue('Meta de prueba');
});

test('el Gantt dibuja las tareas y abre el editor compartido', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${BASE}/tasks/gantt`);
    const bar = page.locator('.md-task-gantt__surface .bar-wrapper').filter({ hasText: 'Tarea de prueba de distribución' });
    await expect(bar).toBeVisible();
    await page.getByRole('button', { name: 'Semana', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Semana', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await page.screenshot({ path: 'storage/framework/testing/playwright/task-gantt-1440.png' });
    await bar.locator('.bar').click();
    const dialog = page.locator('#task-editor');
    await expect(dialog.getByRole('textbox', { name: 'Título', exact: true })).toHaveValue('Tarea de prueba de distribución');
    expect(errors).toEqual([]);
});

test.describe('pantallas táctiles', () => {
    test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

    test('tocar una fecha sigue abriendo el selector nativo', async ({ page }) => {
        await page.evaluate(() => {
            window.__pickers = 0;
            HTMLInputElement.prototype.showPicker = function () { window.__pickers++; };
        });
        const dialog = page.locator('#task-editor');
        await taskSection(dialog, 'Programación');
        await dialog.getByLabel('Fecha inicio', { exact: true }).tap();
        expect(await page.evaluate(() => window.__pickers)).toBe(1);
    });
});

test('en escritorio el click no fuerza el selector', async ({ page }) => {
    await page.evaluate(() => {
        window.__pickers = 0;
        HTMLInputElement.prototype.showPicker = function () { window.__pickers++; };
    });
    const dialog = page.locator('#task-editor');
    await taskSection(dialog, 'Programación');
    await dialog.getByLabel('Fecha inicio', { exact: true }).click({ position: { x: 24, y: 28 } });
    expect(await page.evaluate(() => window.__pickers)).toBe(0);
});
