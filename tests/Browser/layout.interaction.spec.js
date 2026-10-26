import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

let server, habit;
test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'layout-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-layout-config.php') };
    habit = execFileSync('php', ['tests/Browser/support/layout-fixture.php'], { env, encoding: 'utf8' }).trim();
    server = spawn('php', ['-S', '127.0.0.1:8031', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch('http://127.0.0.1:8031/login')).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error('Testing server did not start');
});
test.afterAll(() => server?.kill());
test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico', { exact: true }).fill('layout@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
});

async function openTaskDialog(page, bulk = false) {
    const mobile = page.viewportSize().width < 768;
    if (mobile) await page.getByRole('button', { name: 'Nueva tarea', exact: true }).click();
    else if (bulk) await page.getByRole('button', { name: 'Más acciones de creación', exact: true }).click();
    if (bulk || mobile) await page.getByRole('menuitem', { name: bulk ? 'Crear múltiples tareas' : 'Nueva tarea', exact: true }).click();
    else await page.getByRole('button', { name: 'Nueva tarea', exact: true }).click();
}

async function taskSection(dialog, name) {
    const nav = dialog.getByRole('navigation', { name: 'Secciones del formulario' });
    if (!await nav.isVisible()) await dialog.getByRole('button', { name: 'Mostrar u ocultar secciones' }).click();
    await nav.getByRole('button', { name: new RegExp('^' + name) }).click();
}

for (const width of [390, 1440]) {
    for (const theme of ['light', 'dark']) {
        test(`acciones rápidas de tareas ${width}px ${theme}`, async ({ page }) => {
            await page.setViewportSize({ width, height: 1000 });
            await page.goto('/tasks/list');
            await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
            const row = page.locator('.task-row').filter({ hasText: 'Tarea de prueba de distribución' });
            const trigger = row.getByRole('button', { name: 'Más acciones de Tarea de prueba de distribución', exact: true });
            await trigger.focus();
            await page.keyboard.press('Enter');
            await expect(trigger).toHaveAttribute('aria-expanded', 'true');
            const complete = row.getByRole('menuitem', { name: 'Completar', exact: true });
            await expect(complete).toBeVisible();
            await expect(row.getByRole('menuitem', { name: 'Mover para hoy', exact: true })).toBeVisible();
            await expect(row.getByRole('menuitem', { name: 'Mover para mañana', exact: true })).toBeVisible();
            await expect(row.getByRole('menuitem', { name: 'Editar', exact: true })).toBeVisible();
            await expect(row.getByRole('menuitem', { name: 'Eliminar', exact: true })).toBeVisible();
            const colors = await complete.evaluate(el => ({ text: getComputedStyle(el).color, icon: getComputedStyle(el.querySelector('i')).color }));
            expect(colors.text).toBe(colors.icon);
            const box = await row.getByRole('menu').boundingBox();
            expect(box.x).toBeGreaterThanOrEqual(0);
            expect(box.x + box.width).toBeLessThanOrEqual(width);
            const a11y = await new AxeBuilder({ page }).include('.task-row .md-menu__surface').withRules(['color-contrast']).analyze();
            expect(a11y.violations).toEqual([]);
            await complete.focus();
            await page.keyboard.press('Escape');
            await expect(trigger).toBeFocused();
            await expect(trigger).toHaveAttribute('aria-expanded', 'false');
            await trigger.click();
            await row.getByRole('menuitem', { name: 'Mover para mañana', exact: true }).click();
            await expect(trigger).toHaveAttribute('aria-expanded', 'false');
            await expect(row).toBeVisible();
        });
    }
}

for (const width of [390, 768, 1440, 1920, 2560]) {
    test(`Salud y Tareas a ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 1000 });
        for (const route of ['/health', '/tasks/list']) {
            await page.goto(route);
            await expect(page.locator('.lt-main')).toBeVisible();
            await page.waitForFunction(() => window.Livewire && window.Alpine);
            await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), { message: route }).toBe(true);
            const main = await page.locator('.lt-main').boundingBox();
            expect(main.width).toBeLessThanOrEqual(1800);
            if (width === 2560) expect(main.width).toBeCloseTo(1800, 2);
            if (route.includes('tasks') && width >= 1280) {
                const columns = await page.locator('.task-list-layout').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' '));
                expect(columns[1]).toBe('320px');
            }
            if (width === 1920) await page.screenshot({ path: `storage/framework/testing/playwright/layout/${route.includes('tasks') ? 'tasks' : 'health'}-1920.png` });
        }
    });
}

test('exercise search, keyboard, Escape, reopening and registration', async ({ page }) => {
    await page.goto('/habits');
    const morning = page.locator('button[aria-controls^="habit-time-morning-"]');
    await expect(morning).toHaveAttribute('aria-expanded', /true|false/);
    if (await morning.getAttribute('aria-expanded') === 'false') await morning.click();
    await page.locator(`[wire\\:click="toggleHabit(${habit})"]`).click();
    const dialog = page.locator('#habit-action-prompt');
    await expect(dialog).toBeVisible();
    const input = dialog.getByRole('combobox');
    await expect(input).not.toHaveValue('');
    await input.fill('TÉNIS');
    await expect(dialog.getByRole('option', { name: 'Tenis', exact: true })).toBeVisible();
    await input.press('ArrowDown');
    await input.press('Enter');
    await expect(input).toHaveValue('Tenis');
    await input.click();
    await input.fill('zzzz');
    await expect(dialog.getByText('Sin resultados')).toBeVisible();
    await input.press('Escape');
    await expect(dialog).toBeVisible();
    await expect(input).toHaveValue('Tenis');
    await input.click();
    await input.fill('ciclismo');
    await input.press('ArrowDown');
    await input.press('Enter');
    await expect(input).toHaveValue('Ciclismo');
    const accessibility = await new AxeBuilder({ page }).include('#habit-action-prompt .md-combobox').analyze();
    expect(accessibility.violations).toEqual([]);
    await dialog.getByRole('button', { name: 'Registrar', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await page.goto('/exercise');
    await expect(page.locator('tbody .md-table__title').filter({ hasText: 'Ciclismo' })).toBeVisible();
});

for (const mobile of [false, true]) {
    test.describe(`searchable category ${mobile ? 'touch' : 'desktop'}`, () => {
        test.use({ viewport: { width: mobile ? 390 : 1440, height: 900 }, hasTouch: mobile, isMobile: mobile });

        test('closes, reopens without blur and preserves the selected value', async ({ page }) => {
            test.setTimeout(60000);
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.goto('/tasks/list');
            await openTaskDialog(page);
            const dialog = page.locator('.task-properties-dialog:visible');
            const input = dialog.getByRole('combobox', { name: 'Categoría', exact: true });
            const panel = dialog.locator('.md-combobox__list');
            const activate = async locator => mobile ? locator.tap() : locator.click();
            await expect(input).toBeVisible();
            await expect(input).toHaveAttribute('aria-expanded', 'false');
            await input.focus();
            await expect(panel).toBeHidden();

            await activate(input);
            await expect(panel).toBeVisible();
            await dialog.locator('.md-form-dialog__title').dispatchEvent('pointerdown');
            await expect(panel).toBeHidden();
            await expect(input).toBeFocused();
            await activate(input);
            await activate(dialog.getByRole('textbox', { name: 'Título', exact: true }));
            await expect(panel).toBeHidden();

            await activate(input);
            await activate(dialog.getByRole('option', { name: 'Educación', exact: true }));
            await expect(input).toHaveValue('Educación');
            await expect(panel).toBeHidden();
            await activate(input);
            await expect(dialog.getByRole('option', { name: 'Personal', exact: true })).toBeVisible();
            await activate(input);
            await expect(panel).toBeHidden();
            await expect(input).toHaveValue('Educación');

            const toggle = dialog.getByRole('button', { name: 'Mostrar opciones de Categoría', exact: true });
            await activate(toggle);
            await expect(panel).toBeVisible();
            await activate(dialog.getByRole('button', { name: 'Ocultar opciones de Categoría', exact: true }));
            await expect(panel).toBeHidden();
            await expect(input).toBeFocused();

            await input.fill('TECNOLOGIA');
            await expect(dialog.getByRole('option', { name: 'Tecnología', exact: true })).toBeVisible();
            await input.press('ArrowDown');
            await input.press('Enter');
            await expect(input).toHaveValue('Tecnología');
            await expect(panel).toBeHidden();
            await input.press('ArrowDown');
            await expect(dialog.getByRole('option', { name: 'Personal', exact: true })).toBeVisible();
            await input.press('Escape');
            await expect(panel).toBeHidden();
            await expect(dialog).toBeVisible();
            await expect(input).toHaveValue('Tecnología');

            await input.fill('sin coincidencia');
            await expect(dialog.getByText('Sin resultados', { exact: true })).toBeVisible();
            await input.press('Tab');
            await expect(panel).toBeHidden();
            await expect(input).toHaveValue('Tecnología');
            await input.focus();
            await expect(panel).toBeHidden();
            await input.fill('descartar');
            await dialog.getByRole('textbox', { name: 'Título', exact: true }).focus();
            await expect(panel).toBeHidden();
            await expect(input).toHaveValue('Tecnología');

            await activate(input);
            await panel.evaluate(el => { el.scrollTop = el.scrollHeight; });
            await activate(dialog.getByRole('option', { name: 'Otros', exact: true }));
            await expect(input).toHaveValue('Otros');
            const accessibility = await new AxeBuilder({ page }).include('.task-properties-dialog .md-combobox').analyze();
            expect(accessibility.violations).toEqual([]);

            await dialog.getByRole('textbox', { name: 'Título', exact: true }).fill(`Categoría buscable ${mobile}`);
            await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
            await expect(dialog).toBeHidden();
            await page.getByRole('button', { name: `Abrir tarea: Categoría buscable ${mobile}`, exact: true }).click();
            await expect(input).toHaveValue('Otros');
            await expect(panel).toBeHidden();
            await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
            expect(errors).toEqual([]);
        });
    });
}

for (const width of [390, 768, 1440]) {
    test(`canonical task dialogs and isolated bulk creation ${width}`, async ({ page }) => {
        test.setTimeout(60000);
        await page.setViewportSize({ width, height: 900 });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/tasks/list');
        await openTaskDialog(page);
        const single = page.locator('#task-editor');
        await expect(single).toBeVisible();
        await expect(single.locator('small')).toHaveCount(0);
        await expect(single.getByText('Varias a la vez')).toHaveCount(0);
        await expect(single.locator('.md-form-dialog__actions button')).toHaveCount(2);
        await single.getByRole('textbox', { name: 'Título', exact: true }).fill('Borrador individual');
        await single.getByLabel('Tamaño', { exact: true }).selectOption('L');
        await taskSection(single, 'Privacidad');
        await single.getByLabel('Tarea privada', { exact: true }).check();
        await single.getByRole('button', { name: 'Expandir', exact: true }).click();
        await expect(single).toHaveClass(/is-expanded/);
        await expect(single.getByLabel('Tarea privada', { exact: true })).toBeChecked();
        await single.getByRole('button', { name: 'Restaurar', exact: true }).click();
        await taskSection(single, 'Programación');
        await expect(single.getByLabel('Fecha inicio', { exact: true })).toBeVisible();
        await expect(single.getByLabel('Tarea recurrente', { exact: true })).toBeVisible();
        await taskSection(single, 'Información básica');
        await expect(single.getByRole('textbox', { name: 'Título', exact: true })).toHaveValue('Borrador individual');
        await page.screenshot({ path: `storage/framework/testing/playwright/layout/task-dialog-${width}.png` });
        await single.getByRole('button', { name: 'Cancelar', exact: true }).click();

        await openTaskDialog(page, true);
        const bulk = page.locator('#task-bulk-editor');
        await expect(bulk).toBeVisible();
        await expect(bulk.getByRole('textbox', { name: 'Tareas', exact: true })).toHaveValue('');
        await expect(bulk.getByLabel('Tamaño', { exact: true })).toHaveValue('');
        await expect(bulk.getByLabel('Estado', { exact: true })).toHaveCount(0);
        await bulk.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(bulk.getByText('Escribe al menos una tarea.', { exact: true })).toBeVisible();
        await bulk.getByRole('textbox', { name: 'Tareas', exact: true }).fill(`Lote ${width} A\n\nLote ${width} B`);
        await bulk.getByLabel('Tamaño', { exact: true }).selectOption('M');
        await taskSection(bulk, 'Privacidad');
        await expect(bulk.getByLabel('Tarea privada', { exact: true })).not.toBeChecked();
        await bulk.getByLabel('Tarea privada', { exact: true }).check();
        await taskSection(bulk, 'Programación');
        await expect(bulk.getByLabel('Tarea recurrente', { exact: true })).toHaveCount(0);
        await bulk.getByLabel('Fecha inicio', { exact: true }).fill('2026-12-01');
        await taskSection(bulk, 'Información básica');
        await page.screenshot({ path: `storage/framework/testing/playwright/layout/task-bulk-dialog-${width}.png` });
        const accessibility = await new AxeBuilder({ page }).include('#task-bulk-editor').withRules(['aria-valid-attr-value', 'duplicate-id-aria', 'label', 'button-name']).analyze();
        expect(accessibility.violations).toEqual([]);
        await bulk.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(bulk).toBeHidden();
        await page.getByRole('button', { name: `Abrir tarea: Lote ${width} A`, exact: true }).click();
        await expect(single.getByLabel('Tamaño', { exact: true })).toHaveValue('M');
        await taskSection(single, 'Privacidad');
        await expect(single.getByLabel('Tarea privada', { exact: true })).toBeChecked();
        await single.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await openTaskDialog(page);
        await expect(single.getByRole('textbox', { name: 'Título', exact: true })).toHaveValue('');
        await expect(single.getByLabel('Tamaño', { exact: true })).toHaveValue('');
        await single.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await openTaskDialog(page, true);
        await expect(bulk.getByRole('textbox', { name: 'Tareas', exact: true })).toHaveValue('');
        await expect(bulk.getByText('Escribe al menos una tarea.', { exact: true })).toBeHidden();
        if (width === 1440) {
            await bulk.getByRole('textbox', { name: 'Tareas', exact: true }).fill('Descartar programación');
            await taskSection(bulk, 'Programación');
            await bulk.getByLabel('Fecha inicio', { exact: true }).fill('2026-12-02');
            await bulk.getByLabel('Fecha fin', { exact: true }).fill('2026-12-01');
            await bulk.getByRole('button', { name: 'Guardar', exact: true }).click();
            await expect(bulk.getByText('La fecha y hora de finalización debe ser posterior al inicio.', { exact: true })).toBeVisible();
            await bulk.getByRole('button', { name: 'Cancelar', exact: true }).click();
            await openTaskDialog(page, true);
            await taskSection(bulk, 'Programación');
            await expect(bulk.getByLabel('Fecha inicio', { exact: true })).toHaveValue('');
            await expect(bulk.getByText('La fecha y hora de finalización debe ser posterior al inicio.', { exact: true })).toBeHidden();
        }
        await bulk.getByRole('button', { name: 'Cerrar', exact: true }).click();
        expect(errors).toEqual([]);
    });
}
