import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';
const PORT = 8044;
let server;

test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing');
    const output = path.join(directory, 'playwright', 'panel');
    mkdirSync(output, { recursive: true });
    const compiledViews = path.join(directory, 'panel-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    const database = path.join(directory, 'panel-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = {
        ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false',
        NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-panel-config.php'),
    };
    execFileSync('php', ['tests/Browser/support/panel-fixture.php'], { env, stdio: 'pipe' });
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

for (const width of [1440, 390]) {
 test(`manual focus session ${width}`, async ({page}) => {
  test.setTimeout(120000);
  await page.setViewportSize({width, height: 900});
  await login(page);
  const initial = await page.locator('.wall-ring--focus b').innerText();
  async function open() {
   await page.getByRole('button', {name:'Crear', exact:true}).click();
   await page.getByRole('menuitem', {name:'Registrar sesión de enfoque', exact:true}).click();
  }
  await open();
  const dialog = page.getByRole('dialog', {name:'Registrar sesión de enfoque', exact:true});
  await expect(dialog).toBeVisible();
  await expect(dialog.locator('input[type=date],input[type=datetime-local]')).toHaveCount(0);
  await dialog.getByLabel('Inicio', {exact:true}).fill('00:00');
  await dialog.getByLabel('Fin', {exact:true}).fill('00:01');
  await expect(dialog.getByRole('status')).toContainText('0 h 1 min');
  await dialog.getByLabel('Descripción (opcional)', {exact:true}).fill('Enfoque manual de prueba');
  const violations = await new AxeBuilder({page}).include('#panel-focus-manual-dialog').analyze();
  expect(violations.violations.map(v=>v.id)).toEqual([]);
  expect(await dialog.evaluate(el=>el.scrollWidth <= el.clientWidth)).toBe(true);
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await open();
  await expect(dialog.getByLabel('Inicio', {exact:true})).toHaveValue('');
  await expect(dialog.getByLabel('Descripción (opcional)', {exact:true})).toHaveValue('');
  await dialog.getByLabel('Inicio', {exact:true}).fill('00:00');
  await dialog.getByLabel('Fin', {exact:true}).fill('00:01');
  await expect(dialog.getByRole('status')).toContainText('0 h 1 min');
  await dialog.getByRole('button', {name:'Guardar', exact:true}).click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('.wall-ring--focus b')).not.toHaveText(initial);
  expect(new URL(page.url()).pathname).toBe('/panel');
 });
}


async function login(page) {
 await page.goto('/login');
 await page.locator('input[type=email]').fill('panel-browser@example.test');
 await page.locator('input[type=password]').fill('browser-test-password');
 await page.locator('button[type=submit]').click();
 await page.waitForURL(url => !url.pathname.includes('/login'));
 await page.goto('/panel');
 await expect(page.locator('.wall-clock strong')).toHaveText(/\d{2}:\d{2}/);
}
for (const [width, height] of [[1440,900],[1280,800],[768,1024],[390,844]]) {
 test(`panel responsive and accessible ${width}`, async ({ page }) => {
  const errors=[]; page.on('pageerror',error=>errors.push(error.message));
  await page.setViewportSize({width,height}); await login(page);
  await expect(page.getByRole('heading',{name:'Panel de pared',exact:true})).toBeAttached();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  if(width>=1200) expect(await page.evaluate(()=>document.documentElement.scrollHeight <= innerHeight + 1)).toBe(true);
  await expect(page.locator('.wall-day')).toHaveCount(5);
  expect(await page.locator('.wall-card').first().evaluate(el=>getComputedStyle(el).borderRadius)).toBe('12px');
  expect(await page.locator('.wall-goal').first().evaluate(el=>getComputedStyle(el).borderRadius)).toBe('0px');
  await page.locator('.wall-day__head').first().click();
  const agenda=page.getByRole('dialog',{name:'Agenda',exact:true});
  await expect(agenda.locator('.md-form-dialog__head')).toBeVisible();
  await expect(agenda.locator('.md-form-dialog__actions')).toBeVisible();
  // En el panel los diálogos abren ampliados.
  await expect(agenda).toHaveClass(/is-expanded/);
  await agenda.getByRole('button',{name:'Restaurar',exact:true}).click();
  await agenda.getByRole('button',{name:'Cerrar',exact:true}).first().click();
  await expect(agenda).toBeHidden();
  await page.getByRole('button',{name:'Modo noche',exact:true}).click();
  await expect(page.locator('body')).toHaveAttribute('data-panel-night','');
  const nightResult=await new AxeBuilder({page}).exclude('.phpdebugbar').analyze();
  expect(nightResult.violations.map(v=>({id:v.id,targets:v.nodes.map(n=>n.target)}))).toEqual([]);
  await page.getByRole('button',{name:'Modo noche',exact:true}).click();
  await page.getByRole('button',{name:'Modo ambiente',exact:true}).click();
  await expect(page.locator('.wall-ambient')).toBeVisible();
  await page.getByRole('button',{name:'Salir del modo ambiente',exact:true}).click();
  await expect(page.locator('.wall-ambient')).toBeHidden();
  await page.screenshot({path:`storage/framework/testing/playwright/panel/panel-${width}.png`,fullPage:true});
  const results = await new AxeBuilder({page}).exclude('.phpdebugbar').analyze();
  expect(results.violations.map(v=>({id:v.id,targets:v.nodes.map(n=>n.target)}))).toEqual([]);
  await page.screenshot({path:`storage/framework/testing/playwright/panel/panel-${width}.png`,fullPage:true});
  expect(errors).toEqual([]);
 });
}
test('panel actions and editors persist without navigation',async({page})=>{
 const errors=[]; page.on('pageerror',e=>errors.push(e.message)); await login(page);
 await page.getByRole('button',{name:/Agua 250 ml/}).click();
 await expect(page.locator('.wall-card').filter({has:page.getByRole('heading',{name:'Agua',exact:true})})).toContainText('250 ml');
 await page.getByRole('button',{name:'📚 Leer 20 minutos',exact:true}).click();
 await expect(page.getByRole('button',{name:'📚 Leer 20 minutos',exact:true})).toHaveAttribute('aria-pressed','true');
 await page.getByRole('button',{name:'Bien',exact:true}).click();
 await expect(page.getByRole('status').filter({hasText:'Registraste'})).toBeVisible();
 await page.getByRole('button',{name:'Diario · Una línea de hoy',exact:true}).click();
 let dialog=page.getByRole('dialog',{name:'Diario del día',exact:true}); await expect(dialog).toBeVisible();
 await expect(dialog.getByRole('textbox',{name:'¿Qué quieres recordar de hoy?'})).toHaveValue('Texto existente del panel');
 await dialog.getByRole('textbox',{name:'¿Qué quieres recordar de hoy?'}).fill('Diario actualizado desde el panel');
 await dialog.getByRole('button',{name:'Guardar',exact:true}).click(); await expect(dialog).toBeHidden();
 await page.getByRole('button',{name:'Lecturas del panel'}).click();
 dialog=page.getByRole('dialog',{name:'Registrar medición',exact:true}); await expect(dialog).toBeVisible();
 await dialog.getByRole('spinbutton',{name:'Valor libros'}).fill('3');await dialog.getByRole('button',{name:'Guardar',exact:true}).click();await expect(dialog).toBeHidden();
 await expect(page.getByRole('button',{name:'Lecturas del panel'})).toContainText('3 / 12');
 await page.getByRole('button',{name:'Iniciar foco',exact:true}).click();
 dialog=page.getByRole('dialog',{name:'Foco de hoy',exact:true});await expect(dialog).toBeVisible();
 await expect(dialog.getByRole('button',{name:'Pausar',exact:true})).toBeVisible();
 await dialog.getByRole('button',{name:'Volver al panel',exact:true}).click();await expect(dialog).toBeHidden();
 await page.getByRole('button',{name:'Crear',exact:true}).click();
 await page.getByRole('menuitem',{name:'Crear tarea',exact:true}).click();
 dialog=page.locator('#task-editor');await expect(dialog).toBeVisible();await dialog.getByRole('button',{name:'Cancelar',exact:true}).click();await expect(dialog).toBeHidden();
 expect(new URL(page.url()).pathname).toBe('/panel');expect(errors).toEqual([]);
});

test('all isolated editors work and drafts survive parent refresh',async({page})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));await login(page);
 async function fab(name){await page.getByRole('button',{name:'Crear',exact:true}).click();await page.getByRole('menuitem',{name,exact:true}).click();}
 await fab('Agregar plan');let dialog=page.getByRole('dialog',{name:'Agregar plan',exact:true});await expect(dialog).toBeVisible();
 await dialog.getByRole('textbox',{name:'Nombre',exact:true}).fill('Plan creado en panel');
 await page.evaluate(()=>{const root=document.querySelector('.wall-panel');window.Livewire.find(root.getAttribute('wire:id')).$call('refreshPanel');});
 await expect(dialog.getByRole('textbox',{name:'Nombre',exact:true})).toHaveValue('Plan creado en panel');
 await dialog.getByRole('button',{name:'Guardar',exact:true}).click();await expect(dialog).toBeHidden();
 await fab('Agregar hábito');dialog=page.getByRole('dialog',{name:'Nuevo hábito',exact:true});await expect(dialog).toBeVisible();
 await dialog.getByRole('textbox',{name:'Nombre',exact:true}).fill('Hábito creado en panel');await dialog.getByRole('button',{name:'Crear hábito',exact:true}).click();await expect(dialog).toBeHidden();
 await page.getByRole('button',{name:'1 compras pendientes',exact:true}).click();dialog=page.getByRole('dialog',{name:'Lista de compras',exact:true});
 await dialog.getByRole('button',{name:'Marcar comprado',exact:true}).click();dialog=page.getByRole('dialog',{name:'Comprado: Pan del panel',exact:true});await expect(dialog).toBeVisible();
 await dialog.getByRole('button',{name:'Marcar comprado',exact:true}).click();await expect(dialog).toBeHidden();
 await page.getByRole('button',{name:'Aprender del panel'}).click();dialog=page.getByRole('dialog',{name:'Registrar avance',exact:true});
 await dialog.getByRole('textbox',{name:'¿Qué avanzaste?'}).fill('Avance narrativo desde panel');await dialog.getByRole('button',{name:'Guardar',exact:true}).click();await expect(dialog).toBeHidden();
 await page.getByRole('button',{name:'Hábitos a evitar',exact:true}).click();dialog=page.getByRole('dialog',{name:'Hábitos a evitar',exact:true});
 await dialog.getByRole('button',{name:'Registrar recaída',exact:true}).click();dialog=page.getByRole('dialog',{name:'Registrar recaída',exact:true});
 await dialog.getByRole('textbox',{name:'Nota (opcional)'}).fill('Contexto de prueba');await dialog.getByRole('button',{name:'Guardar',exact:true}).click();await expect(dialog).toBeHidden();
 await page.getByRole('button',{name:'Posponer',exact:true}).click();dialog=page.getByRole('dialog',{name:'Posponer tarea',exact:true});await expect(dialog).toBeVisible();
 await dialog.getByRole('button',{name:'Cancelar',exact:true}).click();
 expect(new URL(page.url()).pathname).toBe('/panel');expect(errors).toEqual([]);
});

for (const width of [1280,768,390]) {
 test(`shared navigation switches layouts ${width}`,async({page})=>{
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setViewportSize({width,height:900});await login(page);
  const sidebar=page.locator('#app-navigation');
  const panelLinks=await sidebar.locator('a').evaluateAll(links=>links.map(link=>new URL(link.href).pathname));
  await expect(sidebar).toHaveClass(/lt-sidebar--right/);
  if(width>=768) await expect.poll(()=>page.evaluate(()=>document.querySelector('.md-fab-host').getBoundingClientRect().right <= document.querySelector('#app-navigation').getBoundingClientRect().left)).toBe(true);
  await expect(sidebar.locator('[aria-current=page]')).toHaveText('Panel de pared');
  if(width<768){
   await expect(sidebar).toHaveAttribute('aria-hidden','true');
   await page.getByRole('button',{name:'Mostrar los módulos',exact:true}).click();
   await expect(sidebar).toHaveAttribute('aria-hidden','false');
   await expect(sidebar.locator('a').first()).toBeFocused();
   await page.keyboard.press('Escape');
   await expect(sidebar).toHaveAttribute('aria-hidden','true');
   await expect(page.getByRole('button',{name:'Mostrar los módulos',exact:true})).toBeFocused();
   await page.getByRole('button',{name:'Mostrar los módulos',exact:true}).click();
   await page.locator('.lt-scrim').click({position:{x:16,y:200}});
   await expect(sidebar).toHaveAttribute('aria-hidden','true');
   await page.getByRole('button',{name:'Mostrar los módulos',exact:true}).click();
  } else {
   expect(await sidebar.evaluate(el=>Math.abs(el.getBoundingClientRect().right-innerWidth)<1)).toBe(true);
   if(width>=1024){
    await page.getByRole('button',{name:'Expandir la barra de módulos',exact:true}).click();
    await expect(sidebar).toHaveCSS('width','282px');
    await page.reload();
    await expect(page.locator('.wall-clock strong')).toHaveText(/\d{2}:\d{2}/);
    await expect(sidebar).toHaveCSS('width','282px');
    await page.getByRole('button',{name:'Colapsar la barra de módulos',exact:true}).click();
    await expect(sidebar).toHaveCSS('width','92px');
   }
  }
  await sidebar.getByRole('link',{name:'Mi día',exact:true}).click();await expect(page).toHaveURL(url=>url.pathname === '/');
  await expect(sidebar).toHaveClass(/lt-sidebar--left/);
  if(width>=1024) await expect(sidebar).toHaveCSS('width','92px');
  expect(await sidebar.locator('a').evaluateAll(links=>links.map(link=>new URL(link.href).pathname))).toEqual(panelLinks);
  if(width<768) await page.getByRole('button',{name:'Mostrar los módulos',exact:true}).click();
  await sidebar.getByRole('link',{name:'Panel de pared',exact:true}).click();await expect(page).toHaveURL(url=>url.pathname === '/panel');
  await expect(sidebar).toHaveClass(/lt-sidebar--right/);
  await expect(page.locator('.wall-clock strong')).toHaveText(/\d{2}:\d{2}/);
  expect(errors).toEqual([]);
 });
}

test('refresh waits five minutes while the clock remains local',async({page})=>{
 await page.clock.install({time:new Date()});await login(page);
 const requests=[];
 page.on('request',req=>{
  if(req.method()!=='POST'||!req.url().endsWith('/update')) return;
  if(req.postDataJSON()?.components?.some(c=>c.calls?.some(call=>call.method==='refreshPanel')))requests.push(req);
 });
 await expect(page.locator('.wall-update-status')).toContainText('Última actualización:');
 await page.clock.fastForward(240000);expect(requests).toHaveLength(0);
 await page.clock.fastForward(60000);await expect.poll(()=>requests.length).toBe(1);
 await expect(page.getByRole('button',{name:'Actualizar panel',exact:true})).toBeEnabled();
 await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
 await page.clock.fastForward(1000);expect(requests).toHaveLength(1);
 // Stale hidden tabs wait until returning, then make one request.
 await page.evaluate(()=>Object.defineProperty(document,'hidden',{configurable:true,value:true}));
 await page.clock.fastForward(300000);expect(requests).toHaveLength(1);
 await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:false});document.dispatchEvent(new Event('visibilitychange'));});
 await expect.poll(()=>requests.length).toBe(2);
});
test('manual refresh preserves drafts, reports failure and allows retry',async({page})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));await login(page);
 const root=page.locator('.wall-panel');
 const stamp=()=>root.evaluate(el=>window.Livewire.find(el.getAttribute('wire:id')).lastUpdatedAt);
 const original=await stamp();
 let refreshRequests=0;
 await page.route('**/update',async route=>{
  const payload=route.request().postDataJSON();
  if(payload?.components?.some(c=>c.calls?.some(call=>call.method==='refreshPanel'))){
   refreshRequests++;
   await route.fulfill({status:503,contentType:'text/html',body:'Servicio no disponible'});
  } else await route.continue();
 });
 await page.getByRole('button',{name:'Actualizar panel',exact:true}).click();
 await expect(page.locator('.wall-update-status')).toContainText('No se pudo actualizar');
 expect(await stamp()).toBe(original);expect(refreshRequests).toBe(1);
 await expect(page.getByRole('button',{name:'Actualizar panel',exact:true})).toBeEnabled();
 await page.unroute('**/update');
 await page.getByRole('button',{name:'Actualizar panel',exact:true}).click();
 await expect(page.locator('.wall-update-status')).not.toContainText('No se pudo actualizar');
 await expect.poll(stamp).not.toBe(original);
 await page.getByRole('button',{name:'Diario · Una línea de hoy',exact:true}).click();
 const dialog=page.getByRole('dialog',{name:'Diario del día',exact:true});await expect(dialog).toBeVisible();
 await dialog.getByRole('textbox',{name:'¿Qué quieres recordar de hoy?'}).fill('Borrador que debe conservarse');
 const before=await stamp();
 await root.evaluate(el=>window.Alpine.$data(el).refreshData());
 await expect.poll(stamp).not.toBe(before);
 await expect(dialog.getByRole('textbox',{name:'¿Qué quieres recordar de hoy?'})).toHaveValue('Borrador que debe conservarse');
 await dialog.getByRole('button',{name:'Cancelar',exact:true}).click();expect(errors).toEqual([]);
});

test('panel consultation dialogs keep canonical chrome and keyboard access', async ({page}) => {
 await login(page);
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const goal=page.locator('.wall-goal').first();
 await goal.locator('b').evaluate(el=>el.textContent='Un objetivo con un título suficientemente largo para ocupar varias líneas completas sin perder palabras');
 expect(await goal.evaluate(el=>el.scrollWidth<=el.clientWidth && el.scrollHeight<=el.clientHeight)).toBe(true);
 await page.getByRole('button',{name:'Ver ánimo y energía',exact:true}).click();
 let dialog=page.getByRole('dialog',{name:'Ánimo y energía',exact:true});
 await expect(dialog).toBeVisible();
 await dialog.getByRole('button',{name:'Más emociones',exact:true}).click();
 dialog=page.getByRole('dialog',{name:'Más emociones',exact:true});
 await expect(dialog.locator('.md-form-dialog__head')).toBeVisible();
 await page.keyboard.press('Escape');await expect(dialog).toBeHidden();
 await page.locator('.wall-emoji').first().click();
 await page.getByRole('button',{name:'Añadir contexto',exact:true}).click();
 dialog=page.getByRole('dialog',{name:'Añadir contexto',exact:true});
 await expect(dialog.locator('.md-form-dialog__actions')).toBeVisible();
 await dialog.getByRole('textbox',{name:'¿Qué pasó?',exact:true}).fill('Contexto conservado al expandir');
 await dialog.getByRole('button',{name:'Expandir',exact:true}).click();
 await expect(dialog.getByRole('textbox',{name:'¿Qué pasó?',exact:true})).toHaveValue('Contexto conservado al expandir');
 await dialog.getByRole('button',{name:'Cerrar',exact:true}).click();
 await expect(dialog).toBeHidden();expect(errors).toEqual([]);
});

for (const width of [1440, 768, 390]) {
 test(`focus task context ${width}`, async ({page}) => {
  test.setTimeout(90000);
  const errors=[];page.on('pageerror', e=>errors.push(e.message));
  await page.setViewportSize({width,height:900});await login(page);
  await page.getByRole('button',{name:'Iniciar foco',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Foco de hoy',exact:true});
  const title=dialog.locator('.wall-focus-task h3');
  await expect(title).toHaveText(/Panel tarea/);
  await expect(dialog.locator('.wall-focus-task strong')).toHaveText('Acuerdos importantes');
  const original=await title.innerText();
  await dialog.getByLabel('Descripción del registro de foco',{exact:true}).fill('Notas independientes');
  await dialog.getByRole('button',{name:'Pausar',exact:true}).click();
  await dialog.getByRole('button',{name:'Volver al panel',exact:true}).click();
  await page.reload();
  await page.getByRole('button',{name:'Iniciar foco',exact:true}).click();
  await expect(title).toHaveText(original);
  await expect(dialog.getByLabel('Descripción del registro de foco',{exact:true})).toHaveValue('Notas independientes');
  await expect(dialog.getByRole('button',{name:'Iniciar',exact:true})).toBeVisible();
  expect(await dialog.evaluate(el=>el.scrollWidth<=el.clientWidth)).toBe(true);
  const timer=await dialog.getByRole('timer').boundingBox();
  const heading=await title.boundingBox();
  if(width>=1024) {
   expect(heading.x).toBeGreaterThan(timer.x+timer.width);
   const region=dialog.getByRole('region',{name:'Descripción de la tarea'});
   expect(await region.evaluate(el=>el.scrollHeight>el.clientHeight)).toBe(true);
   await region.evaluate(el=>el.scrollTop=300);
   expect((await dialog.getByRole('timer').boundingBox()).y).toBe(timer.y);
  }
  else expect(heading.y).toBeGreaterThan(timer.y+timer.height);
  expect((await new AxeBuilder({page}).include('.wall-focus-dialog').analyze()).violations.map(v=>v.id)).toEqual([]);
  await page.screenshot({path:`storage/framework/testing/playwright/panel/focus-${width}.png`,fullPage:true});
  await page.keyboard.press('Escape');await expect(dialog).toBeHidden();
  expect(errors).toEqual([]);
 });
}
