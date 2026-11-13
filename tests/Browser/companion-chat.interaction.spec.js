import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8062;
const directory = path.resolve('storage/framework/testing');
const database = path.join(directory, 'companion-voice-browser.sqlite');
let server, fixture;
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: `http://127.0.0.1:${PORT}` });

test.beforeAll(async () => {
    mkdirSync(directory, { recursive: true });
    const compiledViews = path.join(directory, 'companion-voice-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${PORT}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        GAME_IMAGE_BASE_URL: '', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        APP_CONFIG_CACHE: path.join(directory, 'unused-companion-voice-config.php') };
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/companion-chat-fixture.php'], { env, encoding: 'utf8' }));
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

// CHG-100: el chat del compañero sigue abierto como una conversación.
test('crear varias tareas seguidas sin que el chat se cierre', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto('/tasks/list');
    const launcher = page.locator('body > .lt-chat-host .lt-chat__launcher');
    await launcher.click();
    const panel = page.locator('body > .lt-chat-host .lt-chat__panel');
    await expect(panel).toBeVisible();
    const entry = panel.locator('textarea');
    await expect(entry).toBeFocused();
    for (const [index, title] of ['Primera tarea del chat', 'Segunda tarea del chat'].entries()) {
        await entry.fill(title);
        await entry.press('Enter');
        await expect(panel.locator('.lt-chat__row--pet', { hasText: '¿Algo más?' })).toHaveCount(index + 1);
        await expect(entry).toBeFocused();
        await expect(panel).toBeVisible();
    }
    await expect(page.locator('main, .md-module-shell').first().getByText('Segunda tarea del chat')).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/playwright/companion-chat/tasks-1280.png' });
    expect(errors).toEqual([]);
});


const chatPanel = page => page.locator('body > .lt-chat-host .lt-chat__panel');
const chip = (page, name) => chatPanel(page).getByRole('button', {name,exact:true});

async function openChat(page) {
    await page.goto('/tasks/list');
    await page.locator('body > .lt-chat-host .lt-chat__launcher').click();
    await expect(chatPanel(page).locator('textarea')).toBeFocused();
}

test('ver y ordenar conserva el chat y comparte los filtros del listado', async ({page}) => {
    await login(page);await openChat(page);
    await chip(page,'Ver tareas').click();await chip(page,'Hoy').click();
    await expect(page.locator('.task-row').filter({hasText:'Chat fixture hoy'})).toBeVisible();
    await expect(page.locator('.task-row').filter({hasText:'Chat fixture vencida'})).toHaveCount(0);
    await expect(page.getByText('Cuándo: Hoy',{exact:true})).toBeVisible();
    await expect(chatPanel(page)).toBeVisible();
    await chip(page,'Ordenar').click();await chip(page,'Título (A–Z)').click();
    await expect(page).toHaveURL(/sort=title/);await expect(chatPanel(page)).toBeVisible();
    await chip(page,'Quitar filtro de fecha').click();
    await expect(page.locator('.task-row').filter({hasText:'Chat fixture vencida'})).toBeVisible();
    await chip(page,'Inicio').click();await chip(page,'¿Qué sigue?').click();
    await expect(chatPanel(page).getByText('Tu siguiente: Chat fixture vencida.',{exact:true})).toBeVisible();
    await chip(page,'Abrir').click();
    const editor=page.getByRole('dialog',{name:'Editar tarea',exact:true});
    await expect(editor).toBeVisible();await expect(chatPanel(page)).toBeHidden();
    await page.keyboard.press('Escape');await expect(editor).toBeHidden();
    await page.locator('body > .lt-chat-host .lt-chat__launcher').click();
    await chip(page,'Inicio').click();await chip(page,'¿Qué sigue?').click();await chip(page,'Completar').click();
    await expect(chatPanel(page).getByText('¡Completada! Un pendiente menos.',{exact:true})).toBeVisible();
    await expect(page.locator('.task-row').filter({hasText:'Chat fixture vencida'})).toHaveCount(0);
    await expect(chatPanel(page)).toBeVisible();
});

test('la recurrencia abre su confirmación y no anuncia éxito antes de confirmar', async ({page}) => {
    await login(page);await page.goto('/tasks/list?q=Chat%20fixture%20recurrente');
    await page.locator('body > .lt-chat-host .lt-chat__launcher').click();
    await chip(page,'¿Qué sigue?').click();await chip(page,'Completar').click();
    const recurring=page.getByRole('dialog',{name:'Programar próxima repetición'});
    await expect(recurring).toBeVisible();await expect(chatPanel(page)).toBeHidden();
    await expect(chatPanel(page).getByText('¡Completada! Un pendiente menos.',{exact:true})).toHaveCount(0);
    await recurring.getByRole('button',{name:'Cancelar',exact:true}).click();
    await expect(page.locator('.task-row').filter({hasText:'Chat fixture recurrente'})).toBeVisible();
});

test('sugerencias requieren selección y Más opciones conserva el borrador', async ({page}) => {
    await login(page);await openChat(page);const panel=chatPanel(page),entry=panel.locator('textarea');
    await entry.fill('Ver tareas');await expect(panel.getByRole('option',{name:'Ver tareas'})).toBeVisible();
    await entry.press('ArrowDown');await entry.press('Enter');await expect(chip(page,'Hoy')).toBeVisible();
    await chip(page,'Inicio').click();await entry.fill('Ordenar mi escritorio');await entry.press('Enter');
    await expect(page.locator('.task-row').filter({hasText:'Ordenar mi escritorio'})).toBeVisible();
    await entry.fill('Borrador para el formulario completo');await panel.getByRole('button',{name:'Más opciones'}).click();
    await expect(panel).toBeHidden();
    // The canonical editor owns its field IDs; find the visible title through its label.
    await expect(page.getByRole('dialog').getByRole('textbox',{name:'Título',exact:true})).toHaveValue('Borrador para el formulario completo');
    await page.keyboard.press('Escape');
});

test('chat accesible y responsive con y sin movimiento', async ({page}) => {
    const errors=[];page.on('pageerror',error=>errors.push(error.message));await login(page);
    for(const width of [375,768,1280]){
        await page.setViewportSize({width,height:900});await openChat(page);
        const panel=chatPanel(page);await expect(panel.locator('textarea')).toBeFocused();
        const report=await new AxeBuilder({page}).include('body > .lt-chat-host').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
        expect(report.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}))).toEqual([]);
        const box=await panel.boundingBox();expect(box.x).toBeGreaterThanOrEqual(0);expect(box.x+box.width).toBeLessThanOrEqual(width+1);
        await panel.locator('textarea').fill('ver tareas');await expect(panel.getByRole('option',{name:'Ver tareas'})).toBeVisible();
        const suggestions=await new AxeBuilder({page}).include('body > .lt-chat-host').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(suggestions.violations.map(v=>v.id)).toEqual([]);
        await page.screenshot({path:`storage/framework/testing/playwright/companion-chat/tasks-guided-${width}.png`,fullPage:true});
        await page.keyboard.press('Escape');await expect(page.locator('body > .lt-chat-host .lt-chat__launcher')).toBeFocused();
    }
    await page.emulateMedia({reducedMotion:'no-preference'});await page.goto('/tasks/list');
    await page.locator('body > .lt-chat-host .lt-chat__launcher').click();
    await expect(chatPanel(page).locator('textarea')).toBeFocused();
    await chatPanel(page).locator('textarea').fill('Puedo escribir durante el saludo');
    await expect(chatPanel(page).locator('textarea')).toHaveValue('Puedo escribir durante el saludo');
    await page.emulateMedia({reducedMotion:'reduce'});await page.setViewportSize({width:375,height:500});await openChat(page);
    const smallEntry=chatPanel(page).locator('textarea'),send=chatPanel(page).locator('button[type=submit]');
    await smallEntry.fill('Tarea con teclado móvil');const sendBox=await send.boundingBox();expect(sendBox.y).toBeGreaterThanOrEqual(0);expect(sendBox.y+sendBox.height).toBeLessThanOrEqual(500);
    await smallEntry.press('Enter');await expect(chatPanel(page).getByText('¡Anotada! Ya tiene su lugar.',{exact:true})).toBeVisible();
    await page.screenshot({path:'storage/framework/testing/playwright/companion-chat/tasks-keyboard-375.png',fullPage:true});
    expect(errors).toEqual([]);
});


test('Agua y Ánimo mantienen sus registros rápidos después de ampliar Tareas', async ({page}) => {
    await page.setViewportSize({width:375,height:900});await login(page);
    await page.goto('/water/daily');await page.locator('body > .lt-chat-host .lt-chat__launcher').click();
    await chatPanel(page).getByRole('button',{name:'Registrar 250 ml de Agua de prueba',exact:true}).click();
    await expect(chatPanel(page).getByText(/Vas por 250 ml hoy/)).toBeVisible();
    await expect(chatPanel(page)).toBeVisible();
    await page.screenshot({path:'storage/framework/testing/playwright/companion-chat/water-375.png',fullPage:true});
    await page.goto('/mood');await page.locator('body > .lt-chat-host .lt-chat__launcher').click();
    await chatPanel(page).getByRole('button',{name:'Registrar Calma de prueba',exact:true}).click();
    await expect(chatPanel(page).getByText('¿Y cómo está tu energía?',{exact:true})).toBeVisible();
    await chatPanel(page).getByRole('button',{name:/Energía 4 de 5/}).click();
    await expect(chatPanel(page).getByText(/Anotado. Cuídate mucho/)).toBeVisible();
    await expect(chatPanel(page)).toBeVisible();
    await page.screenshot({path:'storage/framework/testing/playwright/companion-chat/mood-375.png',fullPage:true});
});

test('sin colección conserva el FAB tradicional', async ({page}) => {
    await login(page,'voice-empty@example.test');await page.goto('/tasks/list');
    await expect(page.locator('body > .lt-chat-host')).toHaveCount(0);
    await expect(page.locator('.md-fab-split')).toBeVisible();
});
