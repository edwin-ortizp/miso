import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

const PORT = 8032;
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
    const output = path.join(directory, 'playwright', 'tasks-settings');
    mkdirSync(output, { recursive: true });
    const database = path.join(directory, 'tasks-settings-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = {
        ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false',
        NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-tasks-settings-config.php'),
    };
    execFileSync('php', ['tests/Browser/support/tasks-settings-fixture.php'], { env, stdio: 'pipe' });
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

for (const width of [1280, 1600, 390]) {
    test(`tasks settings real interactions ${width}`, async ({ page }) => {
        test.setTimeout(120000);
        page.setDefaultTimeout(12000);
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        const compact = width <= 640;

        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/login');
        await page.getByLabel('Correo electrónico', { exact: true }).fill('tasks-settings-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL((url) => !url.pathname.includes('/login'));

        await page.goto('/tasks/settings');
        const nav = page.locator('.task-settings-nav');
        const card = page.locator('#task-settings-categories');
        await expect(card).toBeVisible();
        await expect(nav.getByRole('link', { name: 'Categorías', exact: true })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByRole('navigation', { name: 'Vistas del módulo' }).getByRole('link', { name: 'Ajustes', exact: true })).toHaveAttribute('aria-current', 'page');

        // Geometría: navegación local a la derecha en escritorio y arriba en móvil.
        const navBox = await nav.boundingBox();
        const cardBox = await card.boundingBox();
        expect(width > 768 ? navBox.x > cardBox.x : navBox.y < cardBox.y).toBe(true);
        // Los estilos acotados del módulo están cargados (el rail tiene borde propio).
        expect(await nav.evaluate((element) => getComputedStyle(element).borderTopWidth)).not.toBe('0px');

        for (const column of ['Nombre', 'Pendientes', 'Total']) {
            await expect(card.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }

        const searchInput = card.locator('#task-settings-categories-search');
        const toggle = card.locator('.md-mcard__search-toggle');
        const hogarRow = card.locator('tbody tr').filter({ hasText: 'Hogar' });
        const childRow = card.locator('tbody tr').filter({ hasText: 'Automatización' });
        await expect(hogarRow).toHaveCount(1);
        await expect(childRow).toHaveCount(1);
        await expect(childRow).toContainText('Tecnología / Automatización');

        // Búsqueda compacta: el ícono abre el campo, queda enfocado y Escape lo cierra.
        if (compact) {
            await expect(searchInput).toBeHidden();
            await toggle.click();
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await expect(searchInput).toBeFocused();
        }

        // Búsqueda por nombre, por ruta visible, sin resultados y limpieza.
        await searchInput.fill('Hogar');
        await expect(hogarRow).toHaveCount(1);
        await expect(childRow).toHaveCount(0);
        await searchInput.fill('Automatización');
        await expect(childRow).toHaveCount(1);
        await expect(hogarRow).toHaveCount(0);
        await searchInput.fill('Tecnología');
        await expect(childRow).toHaveCount(1);
        await expect(card.locator('tbody tr').filter({ hasText: 'Finanzas' })).toHaveCount(0);
        await searchInput.fill('zzzz-no-existe');
        await expect(card.locator('.md-empty-state[data-state="filtered-empty"]')).toBeVisible();
        await searchInput.fill('');
        await expect(hogarRow).toHaveCount(1);
        if (compact) {
            await searchInput.press('Escape');
            await expect(searchInput).toBeHidden();
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        }

        // Menú de fila: abre, cierra con Escape y devuelve el foco al disparador.
        const menuTrigger = hogarRow.locator('.md-row-actions__more');
        await menuTrigger.click();
        await expect(menuTrigger).toHaveAttribute('aria-expanded', 'true');
        await expect(page.getByRole('menuitem', { name: 'Eliminar', exact: true })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(menuTrigger).toHaveAttribute('aria-expanded', 'false');
        await expect(menuTrigger).toBeFocused();

        // Eliminar una categoría con tareas pide a dónde pasarlas.
        await menuTrigger.click();
        await page.getByRole('menuitem', { name: 'Eliminar', exact: true }).click();
        const deleteDialog = page.getByRole('dialog');
        await expect(deleteDialog.getByRole('heading', { name: 'Eliminar «Hogar»' })).toBeVisible();
        await expect(deleteDialog.getByLabel('Pasar tareas a')).toBeVisible();
        const deleteA11y = await new AxeBuilder({ page }).include('[role="dialog"]').withRules(['button-name', 'label', 'aria-valid-attr-value', 'select-name']).analyze();
        expect(deleteA11y.violations, describeViolations(deleteA11y.violations)).toEqual([]);
        await deleteDialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        // Editar categoría reutiliza el mismo diálogo con el nombre precargado.
        // En escritorio la acción principal es un botón propio; en compacto vive dentro del menú ⋮.
        if (compact) {
            await menuTrigger.click();
            await page.getByRole('menuitem', { name: 'Editar', exact: true }).click();
        } else {
            await hogarRow.getByRole('button', { name: 'Editar', exact: true }).click();
        }
        const editDialog = page.getByRole('dialog');
        await expect(editDialog.getByLabel('Nombre')).toHaveValue('Hogar');
        await editDialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        // Creación por FAB + diálogo canónico (sin navegación).
        const newCategory = `Categoría navegador ${width}`;
        await page.getByRole('button', { name: 'Nueva categoría', exact: true }).click();
        const createDialog = page.getByRole('dialog');
        await expect(createDialog.getByRole('heading', { name: 'Nueva categoría' })).toBeVisible();
        await createDialog.getByLabel('Nombre').fill(newCategory);
        await createDialog.getByLabel('Icono').selectOption('bi-star');
        const createA11y = await new AxeBuilder({ page }).include('[role="dialog"]').withRules(['button-name', 'label', 'aria-valid-attr-value', 'select-name']).analyze();
        expect(createA11y.violations, describeViolations(createA11y.violations)).toEqual([]);
        await createDialog.getByRole('button', { name: 'Crear categoría', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(card.getByRole('cell', { name: newCategory, exact: true })).toBeVisible();

        const categoriesA11y = await analyze(page, '.task-settings-workspace');
        expect(categoriesA11y.violations, describeViolations(categoriesA11y.violations)).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/tasks-settings/categories-${width}.png`, fullPage: true });

        // Sección de estados: misma card, columnas, chip tonal por tipo y estados protegidos.
        await nav.getByRole('link', { name: 'Estados', exact: true }).click();
        await expect(page).toHaveURL(/\/tasks\/settings\/statuses/);
        const statusCard = page.locator('#task-settings-statuses');
        await expect(statusCard).toBeVisible();
        await expect(nav.getByRole('link', { name: 'Estados', exact: true })).toHaveAttribute('aria-current', 'page');
        // Misma geometría de la navegación local en la sección de estados.
        const statusNavBox = await nav.boundingBox();
        const statusCardBox = await statusCard.boundingBox();
        expect(width > 768 ? statusNavBox.x > statusCardBox.x : statusNavBox.y < statusCardBox.y).toBe(true);
        for (const column of ['Nombre', 'Tipo', 'Tareas']) {
            await expect(statusCard.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const doneRow = statusCard.locator('tbody tr').filter({ hasText: 'Done' });
        await expect(doneRow.locator('.md-chip-tonal--success')).toContainText('Terminado');
        await expect(statusCard.locator('tbody tr').filter({ hasText: 'WIP' }).locator('.md-chip-tonal--warning')).toContainText('En curso');

        // El estado terminado no ofrece eliminar.
        await doneRow.locator('.md-row-actions__more').click();
        await expect(page.getByRole('menuitem', { name: 'Eliminar', exact: true })).toHaveCount(0);
        await page.keyboard.press('Escape');

        // Estados con tareas piden reasignar antes de eliminar.
        await statusCard.locator('tbody tr').filter({ hasText: 'WIP' }).locator('.md-row-actions__more').click();
        await page.getByRole('menuitem', { name: 'Eliminar', exact: true }).click();
        const statusDeleteDialog = page.getByRole('dialog');
        await expect(statusDeleteDialog.getByRole('heading', { name: 'Eliminar «WIP»' })).toBeVisible();
        await expect(statusDeleteDialog.getByLabel('Pasar tareas a')).toBeVisible();
        await statusDeleteDialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        const newStatus = `Estado navegador ${width}`;
        await page.getByRole('button', { name: 'Nuevo estado', exact: true }).click();
        const statusDialog = page.getByRole('dialog');
        await expect(statusDialog.getByRole('heading', { name: 'Nuevo estado' })).toBeVisible();
        await statusDialog.getByLabel('Nombre').fill(newStatus);
        await statusDialog.getByLabel('Tipo').selectOption('ready');
        await statusDialog.getByRole('button', { name: 'Crear estado', exact: true }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(statusCard.getByRole('cell', { name: newStatus, exact: true })).toBeVisible();

        const statusesA11y = await analyze(page, '.task-settings-workspace');
        expect(statusesA11y.violations, describeViolations(statusesA11y.violations)).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(errors).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/tasks-settings/statuses-${width}.png`, fullPage: true });
    });
}

for (const width of [390, 1280]) {
    test(`task codes copy, search and prefix settings ${width}`, async ({ page, context }) => {
        test.setTimeout(90000);
        page.setDefaultTimeout(12000);
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.getByLabel('Correo electrónico', { exact: true }).fill('tasks-settings-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/tasks/list');
        const row = page.locator('.md-task-open-button').filter({ hasText: 'Barrer la casa' });
        const code = (await row.locator('code').textContent()).trim();
        expect(code).toMatch(/^HOG-\d{5}$/);
        await row.click();
        const dialog = page.locator('#task-editor');
        const copy = dialog.getByRole('button', { name: `Copiar código ${code}`, exact: true });
        await expect(copy).toBeVisible();
        await copy.focus();
        await page.keyboard.press('Enter');
        await expect(dialog.getByRole('status').filter({ hasText: 'Código copiado' })).toBeVisible();
        expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(code);
        await page.evaluate(() => { navigator.clipboard.writeText = async () => { throw new Error('Clipboard unavailable'); }; });
        await copy.click();
        await expect(dialog.getByRole('status').filter({ hasText: 'No se pudo copiar el código' })).toBeVisible();
        expect(await dialog.locator('input[name="code"]').count()).toBe(0);
        await page.screenshot({ path: `storage/framework/testing/playwright/task-code-${width}.png` });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        // The shared Markdown toolbar has an existing contrast issue; check the new code control itself.
        const a11y = await analyze(page, '#task-editor .md-task-code');
        expect(a11y.violations, describeViolations(a11y.violations)).toEqual([]);
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        const search = page.locator('#task-list-search');
        if (!await search.isVisible()) await page.locator('#task-list .md-mcard__search-toggle').click();
        await search.fill(code.toLowerCase());
        await expect(row).toBeVisible();
        await expect(page.locator('.md-task-open-button')).toHaveCount(1);
        await page.goto('/tasks/flow');
        await expect(page.locator('.task-flow-card').filter({ hasText: 'Barrer la casa' })).toContainText(code);
        await page.goto('/tasks/settings');
        await page.getByLabel('Prefijo para tareas sin categoría', { exact: true }).fill('MSS');
        await Promise.all([
            page.waitForResponse(response => response.url().includes('/update') && response.request().method() === 'POST'),
            page.getByRole('button', { name: 'Guardar prefijo', exact: true }).click(),
        ]);
        await page.reload();
        await expect(page.getByLabel('Prefijo para tareas sin categoría', { exact: true })).toHaveValue('MSS');
    });
}
