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
    execFileSync('php', ['tests/Browser/support/layout-fixture.php'], { env: { ...env, TEST_TASK_CARDS: '1' }, encoding: 'utf8' });
    server = spawn('php', ['-S', '127.0.0.1:8032', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`${BASE}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Testing server did not start');
});
test.afterAll(() => server?.kill());
let blocked = [];
test.afterEach(() => expect(blocked, 'Recursos pedidos a rutas que no existen en producción').toEqual([]));
test.beforeEach(async ({ page }) => {
    // Como en producción: el hosting bloquea /vendor/ y /build/ no se despliega.
    blocked = [];
    await page.route(/\/(vendor|build)\//, route => { blocked.push(route.request().url()); return route.abort(); });
    await page.goto(`${BASE}/login`);
    await page.locator('input[type=email]').fill('layout@example.test');
    await page.locator('input[type=password]').fill('browser-test-password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
    await page.goto(`${BASE}/tasks/list`);
    await page.getByRole('button', { name: 'Nueva tarea', exact: true }).click();
    // En móvil el FAB despliega un menú de creación.
    if (page.viewportSize().width < 768) await page.getByRole('menuitem', { name: 'Nueva tarea', exact: true }).click();
});

async function taskSection(dialog, name) {
    const nav = dialog.getByRole('navigation', { name: 'Secciones del formulario' });
    await nav.getByRole('button', { name: new RegExp('^' + name) }).click();
}

for (const width of [390, 1440]) test(`las tarjetas separan la planificación y los tags a ${width} px`, async ({ page }, testInfo) => {
    const title = 'Preparar informe de prueba';
    await page.locator('#task-editor').getByRole('button', { name: 'Cancelar', exact: true }).click();
    await page.setViewportSize({ width, height: 844 });

    const row = page.locator('.task-row').filter({ hasText: title });
    const footer = row.locator('.task-card__footer');
    const openTask = row.getByRole('button', { name: `Abrir tarea: ${title}` });
    const date = row.getByRole('button', { name: new RegExp(`Cambiar fechas de ${title}`) });
    await expect(openTask.locator('.md-task-code')).toBeVisible();
    await expect(openTask.locator('.task-card__description')).toHaveText('Revisar cifras y enviar el resumen');
    await expect(date).toContainText('09:00');
    await expect(row.locator('.task-row__planning')).toContainText('45 min');
    await expect(row.locator('.task-row__planning')).toContainText('Semanal');
    await expect(footer).toContainText('Personal');
    await expect(footer).toContainText('Importante');
    await expect(footer).toContainText('1/2 subtareas');
    await expect(footer.locator('.task-row__status')).not.toBeEmpty();

    const geometry = await row.evaluate(card => {
        const summary = card.querySelector('.md-task-open-button').getBoundingClientRect();
        const planning = card.querySelector('.task-row__planning').getBoundingClientRect();
        const tags = card.querySelector('.task-card__footer').getBoundingClientRect();
        const style = getComputedStyle(card);
        return {
            innerWidth: card.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
            tagsWidth: tags.width,
            footerBelowPlanning: tags.top >= planning.bottom,
            planningBesideSummary: planning.left >= summary.right,
            cardFits: card.scrollWidth <= card.clientWidth,
        };
    });
    expect(geometry.tagsWidth).toBeCloseTo(geometry.innerWidth, 0);
    expect(geometry.footerBelowPlanning).toBe(true);
    expect(geometry.planningBesideSummary).toBe(width === 1440);
    expect(geometry.cardFits).toBe(true);
    await expect(footer).toHaveCSS('border-top-width', '1px');
    await row.screenshot({ path: testInfo.outputPath('tarjeta-lista.png') });

    const more = row.getByRole('button', { name: `Más acciones de ${title}` });
    await more.click();
    await expect(row.getByRole('menuitem', { name: 'Editar', exact: true })).toBeVisible();
    await more.press('Escape');
    await date.click();
    const quickDates = page.locator('#task-quick-dates-dialog');
    await expect(quickDates).toBeVisible();
    await quickDates.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await expect(date).toBeFocused();
    await openTask.click();
    await expect(page.locator('#task-editor').getByRole('textbox', { name: 'Título', exact: true })).toHaveValue(title);
});

test('el candado del listado se actualiza al guardar Horario fijo', async ({ page }) => {
    const dialog = page.locator('#task-editor');
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    const title = 'Preparar informe de prueba';
    const date = page.getByRole('button', { name: new RegExp(`Cambiar fechas de ${title}`) });
    const lock = date.locator('.task-locked-icon');
    await expect(lock).toHaveCount(0);
    await page.getByRole('button', { name: `Abrir tarea: ${title}` }).click();

    for (const enabled of [true, false]) {
        await dialog.locator('.md-form-dialog__head').getByRole('button', { name: 'Editar', exact: true }).click();
        await taskSection(dialog, 'Programación');
        await dialog.getByRole('switch', { name: 'Horario fijo' }).press('Space');
        await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(lock).toHaveCount(enabled ? 1 : 0);
    }
});

test('Kanban, Planificación y Flujo mantienen los datos y las acciones de la tarjeta', async ({ page }) => {
    for (const [route, cardClass, tag, action] of [
        ['kanban', '.task-kanban-card', 'Personal', 'Completar tarea: Preparar informe de prueba'],
        ['planning', '.md-planning-card', 'Personal', 'Completar Preparar informe de prueba'],
        ['flow', '.task-flow-card', 'S', 'Completar Preparar informe de prueba'],
    ]) {
        await page.goto(`${BASE}/tasks/${route}`);
        const card = page.locator(cardClass).filter({ hasText: 'Preparar informe de prueba' });
        await expect(card.locator('.task-card__description')).toHaveText('Revisar cifras y enviar el resumen');
        await expect(card.locator('.task-card__footer')).toContainText(tag);
        await expect(card.getByRole('button', { name: action, exact: true })).toBeVisible();
    }
});

for (const width of [360, 390, 1440]) test(`Editar enables Markdown in one action ${width}`, async ({ page }) => {
    await page.setViewportSize({ width, height: 844 });
    const dialog = page.locator('#task-editor');
    const source = dialog.locator('[data-markdown-source]');
    await expect(source).toBeVisible(); // Creation starts in write mode.
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    const open = () => page.getByRole('button', { name: 'Abrir tarea: Tarea de prueba de distribución' }).click();
    await open();
    await expect(source).toBeHidden();
    const edit = dialog.locator('.md-form-dialog__head').getByRole('button', { name: 'Editar', exact: true });
    await expect(edit).toContainText('Editar');
    await page.screenshot({ path: `storage/framework/testing/playwright/task-consult-${width}.png` });
    await edit.click();
    await expect(source).toBeVisible();
    const description = `Descripción íntegra 🌱 **Markdown** ${width}`;
    await source.fill(description);
    await dialog.locator('[data-markdown-mode="preview"]').click();
    const editor = page.locator('[wire\\:name="task.task-editor"]');
    await editor.evaluate(async el => { await el.__livewire.$wire.$refresh(); });
    await expect(source).toBeHidden();
    await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(edit).toBeVisible();
    await expect(source).toBeHidden();
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await open();
    await expect(source).toBeHidden();
    await edit.click();
    await expect(source).toHaveValue(description);
    await source.fill('Borrador que se descartará');
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    const discard = page.getByRole('dialog', { name: 'Tienes cambios sin guardar', exact: true });
    await expect(discard).toBeVisible();
    await discard.getByRole('button', { name: 'Descartar cambios', exact: true }).click();
    await expect(dialog).toBeHidden();
    await open();
    await edit.click();
    await expect(source).toHaveValue(description);
    await page.screenshot({ path: `storage/framework/testing/playwright/task-edit-${width}.png` });
    const id = await editor.evaluate(el => el.__livewire.$wire.editingId);
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await page.evaluate(id => window.dispatchEvent(new CustomEvent('task-editor', { detail: { action: 'openForm', id, mode: 'edit' } })), id);
    await expect(source).toBeVisible();
    await expect(source).toHaveValue(description);
    // Leave the shared fixture unchanged for the existing formatting scenarios.
    await source.fill('');
    await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(edit).toBeVisible();
});

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
        await dialog.locator('.md-form-dialog__head').getByRole('button', { name: 'Editar', exact: true }).click();
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
    await start.fill('2026-10-21');
    const end = dialog.locator('#task-editor-end');
    await end.click({ position: { x: 24, y: 28 } });
    await end.fill('2026-10-22');
    const startTime = dialog.locator('#task-editor-start-time');
    await startTime.click({ position: { x: 24, y: 28 } });
    await startTime.fill('09:30');
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

for (const width of [390, 1440]) test(`la programación sincroniza inicio, fin y minutos a ${width} px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 844 });
    const dialog = page.locator('#task-editor');
    const title = `Turno nocturno ${width}`;
    const duration = dialog.getByRole('spinbutton', { name: 'Tiempo estimado (min)' });
    const startTime = dialog.locator('#task-editor-start-time');
    const endDate = dialog.getByLabel('Fecha fin', { exact: true });
    const endTime = dialog.locator('#task-editor-end-time');

    await dialog.getByRole('textbox', { name: 'Título', exact: true }).fill(title);
    await taskSection(dialog, 'Programación');
    await dialog.getByLabel('Fecha inicio', { exact: true }).fill('2026-11-18');
    await startTime.fill('21:30');
    await duration.fill('90');
    await duration.blur();
    await expect(endTime).toHaveValue('23:00');
    await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(dialog).toBeHidden();

    await page.getByRole('button', { name: `Abrir tarea: ${title}`, exact: true }).click();
    await expect(duration).toHaveValue('90');
    await dialog.locator('.md-form-dialog__head').getByRole('button', { name: 'Editar', exact: true }).click();
    await taskSection(dialog, 'Programación');
    await startTime.fill('23:30');
    await startTime.blur();
    await expect(endDate).toHaveValue('2026-11-19');
    await expect(endTime).toHaveValue('01:00');
    await expect(duration).toHaveValue('90');
    await expect(dialog.getByText('Cambios pendientes de guardar')).toBeVisible();

    await endTime.fill('00:15');
    await endTime.blur();
    await expect(duration).toHaveValue('45');

    await endDate.fill('2026-11-18');
    await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(dialog.getByText('El fin debe ser igual o posterior al inicio.')).toBeVisible();
    await endDate.fill('2026-11-19');
    await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(dialog.locator('.md-form-dialog__head').getByRole('button', { name: 'Editar', exact: true })).toBeVisible();
    await expect(duration).toHaveValue('45');
    await expect(dialog.getByText('Cambios pendientes de guardar')).toBeHidden();
});

test('la barra Markdown pasa a edición desde la vista previa', async ({ page }) => {
    const dialog = page.locator('#task-editor');
    await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await page.getByRole('button', { name: 'Abrir tarea: Tarea de prueba de distribución' }).click();
    await expect(dialog.getByRole('textbox', { name: 'Título', exact: true })).toHaveValue('Tarea de prueba de distribución');
    await dialog.locator('.md-form-dialog__head').getByRole('button', { name: 'Editar', exact: true }).click();
    await dialog.locator('[data-markdown-mode="preview"]').click();
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

test('el calendario carga FullCalendar sin pedir recursos a /build/', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${BASE}/tasks/calendar`);
    await expect(page.locator('.md-task-calendar__surface .fc-view-harness')).toBeVisible();
    expect(errors).toEqual([]);
});

// CHG-108: en móvil las secciones son una franja siempre visible; en escritorio queda el rail colapsable.
for (const width of [360, 390]) test(`Sections strip stays visible on mobile ${width}`, async ({ page }) => {
    await page.setViewportSize({ width, height: 844 });
    const dialog = page.locator('#task-editor');
    const nav = dialog.getByRole('navigation', { name: 'Secciones del formulario' });
    await expect(nav).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Mostrar u ocultar secciones' })).toBeHidden();
    const strip = await nav.boundingBox();
    const head = await dialog.locator('.md-form-dialog__head').boundingBox();
    expect(strip.y).toBeGreaterThanOrEqual(head.y + head.height - 1);
    const items = nav.getByRole('button');
    await items.nth(1).click();
    await expect(nav).toBeVisible();
    await expect(items.nth(1)).toHaveAttribute('aria-current', 'true');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('Sections rail keeps its collapse control on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const dialog = page.locator('#task-editor');
    const toggle = dialog.getByRole('button', { name: 'Mostrar u ocultar secciones' });
    await expect(toggle).toBeVisible();
    await toggle.click();
    await expect(dialog.getByRole('navigation', { name: 'Secciones del formulario' })).toBeHidden();
});
