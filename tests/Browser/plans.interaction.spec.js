import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync,closeSync,openSync } from 'node:fs';
import path from 'node:path';
let server;
test.beforeAll(async()=>{
 test.setTimeout(60000);const directory=path.resolve('storage/framework/testing');mkdirSync(directory,{recursive:true});const database=path.join(directory,'plan-browser.sqlite');closeSync(openSync(database,'a'));
 const env={...process.env,APP_ENV:'testing',DB_CONNECTION:'sqlite',DB_DATABASE:database,DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',DEBUGBAR_ENABLED:'false',TELESCOPE_ENABLED:'false',NIGHTWATCH_ENABLED:'false',APP_CONFIG_CACHE:path.join(directory,'unused-plan-config.php')};
 execFileSync('php',['tests/Browser/support/plan-fixture.php'],{env,stdio:'pipe'});
 server=spawn('php',['-S','127.0.0.1:8028',path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.resolve('public'),env,stdio:'pipe',windowsHide:true});
 for(let i=0;i<60;i++){try{if((await fetch('http://127.0.0.1:8028/login')).ok)return;}catch{}await new Promise(resolve=>setTimeout(resolve,500));}throw Error('Server did not start');
});
test.afterAll(()=>server?.kill());
async function login(page, email = 'plan-browser@example.test') {
 await page.goto('/login');
 await page.locator('#login-email').fill(email);
 await page.locator('#login-pw').fill('browser-test-password');
 await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
 await page.waitForURL(url => !url.pathname.includes('/login'));
}

for(const width of [1440,390]) test(`plans real interactions ${width}`,async({page})=>{
 test.setTimeout(90000);page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setViewportSize({width,height:1000});await login(page);
 await page.goto('/plans');expect(await page.locator('.plans-kpis').evaluate(el=>getComputedStyle(el).borderTopWidth)).toBe('0px');await page.screenshot({path:`storage/framework/testing/playwright/plans/all-${width}.png`,fullPage:true});await expect(page.locator('.plan-row__person').first()).toHaveAttribute('href',/relationships\//);await page.locator('.plan-row__person').first().click();await expect(page).toHaveURL(/relationships\//);await page.goBack();await expect(page.locator('.plans-list')).toBeVisible();
 const nav=()=>page.getByRole('navigation',{name:'Vistas del módulo'});
 await nav().getByRole('link',{name:'Programados',exact:true}).click();await expect(page.getByRole('link',{name:'Cena programada',exact:true})).toBeVisible();await expect(page.getByRole('link',{name:'Plan vencido',exact:true})).toHaveCount(0);
 await page.getByRole('button',{name:/Filtros/}).first().click();await page.getByLabel('Desde',{exact:true}).fill('2000-01-01');await expect(page.getByRole('link',{name:'Plan vencido',exact:true})).toBeVisible();await page.keyboard.press('Escape');
 await nav().getByRole('link',{name:'Historial',exact:true}).click();await expect(page.getByText('Comentario de visita '+width,{exact:true})).toBeVisible();const visit=page.locator('.md-list-item').filter({hasText:'Comentario de visita '+width});
 if(width===390){await visit.locator('.md-row-actions__more').click();await page.getByRole('menuitem',{name:'Ver detalle',exact:true}).click();}else await visit.getByRole('button',{name:'Ver detalle',exact:true}).click();
 await expect(page.getByRole('dialog')).toBeVisible();await page.getByRole('dialog').getByRole('button',{name:'Cerrar',exact:true}).click();
 await nav().getByRole('link',{name:'Ajustes',exact:true}).click();await page.getByRole('button',{name:'Categorías de planes',exact:true}).click();await expect(page.getByRole('cell',{name:'Gastronomía',exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Crear categoría',exact:true}).click();const dialog=page.getByRole('dialog');await expect(dialog).toBeVisible();await dialog.getByLabel('Nombre',{exact:false}).fill('Categoría navegador '+width);await dialog.getByLabel('Buscar icono',{exact:true}).fill('corazón');await dialog.getByRole('button',{name:'heart',exact:true}).click();
 await dialog.getByLabel('Buscar icono',{exact:true}).fill('zzzzzz');await expect(dialog.getByText('No se encontraron iconos.',{exact:true})).toBeVisible();await dialog.getByLabel('Buscar icono',{exact:true}).fill('calendar');await expect(dialog.locator('.plan-icon-picker__results button').first()).toBeVisible();await dialog.getByLabel('Buscar icono',{exact:true}).press('ArrowDown');await expect(dialog.locator('.plan-icon-picker__results button:focus')).toHaveCount(1);await page.keyboard.press('Enter');
 const a11y=await new AxeBuilder({page}).include('[role="dialog"]').withRules(['button-name','label','aria-valid-attr-value']).analyze();expect(a11y.violations).toEqual([]);
 await dialog.getByRole('button',{name:'Guardar'}).click();await expect(dialog).toHaveCount(0);await expect(page.getByRole('cell',{name:'Categoría navegador '+width,exact:true})).toBeVisible();
 await expect(nav().getByRole('link',{name:'Ajustes',exact:true})).toHaveAttribute('aria-current','page');expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);expect(errors).toEqual([]);
 await page.screenshot({path:`storage/framework/testing/playwright/plans/settings-${width}.png`,fullPage:true});
 const settingsNav=await page.locator('.module-settings-nav').boundingBox();const settingsCard=await page.locator('#plan-settings').boundingBox();expect(width>768?settingsNav.x>settingsCard.x:settingsNav.y<settingsCard.y).toBe(true);
 await page.getByRole('button',{name:'Tags de planes',exact:true}).click();await page.getByRole('button',{name:'Crear tag',exact:true}).click();await page.getByRole('dialog').getByLabel('Nombre',{exact:false}).fill('Tag navegador '+width);await page.getByRole('dialog').getByRole('button',{name:'Guardar'}).click();await expect(page.getByRole('cell',{name:'Tag navegador '+width,exact:true})).toBeVisible();
 await nav().getByRole('link',{name:'Historial',exact:true}).click();const deletionRow=page.locator('.md-list-item').filter({hasText:'Comentario de visita '+width});await expect(deletionRow).toBeVisible();
 await deletionRow.locator('.md-row-actions__more').click();page.once('dialog',dialog=>dialog.dismiss());await page.getByRole('menuitem',{name:'Eliminar del historial'}).click();await expect(deletionRow).toBeVisible();
 await deletionRow.locator('.md-row-actions__more').click();page.once('dialog',dialog=>dialog.accept());await page.getByRole('menuitem',{name:'Eliminar del historial'}).click();await expect(deletionRow).toHaveCount(0);
 await nav().getByRole('link',{name:'Todos',exact:true}).click();await expect(page.getByRole('link',{name:'Plan realizado',exact:true})).toBeVisible();expect(errors).toEqual([]);
});

for (const width of [320, 375, 390, 768, 1440]) test(`plans fit viewport ${width}`, async ({page}) => {
 await page.setViewportSize({width,height:1000});
 await login(page);
 await page.goto('/plans?sort=name');
 const row=page.locator('.plan-row').filter({hasText:'Evento extenso'});
 await expect(row).toBeVisible();await expect(row.getByText('Vencido',{exact:true})).toHaveCount(0);
 const assertFit=async()=>{
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  const outside=await page.locator('.plan-row, .plan-row__aside, .plan-row__stats, .plan-row > :last-child, .plan-card, .md-mcard__head, .md-mcard__foot').evaluateAll(elements=>elements.filter(el=>{const r=el.getBoundingClientRect();return r.width>0&&(r.left < -1 || r.right > innerWidth+1);}).map(el=>el.className));
  expect(outside).toEqual([]);
 };
 await assertFit();await page.screenshot({path:`storage/framework/testing/playwright/plans/fit-${width}.png`,fullPage:true});
 await row.getByRole('button',{name:/Más opciones/}).click();await expect(page.getByRole('menuitem',{name:'Ver detalle',exact:true})).toBeVisible();await page.keyboard.press('Escape');
 await page.getByRole('button',{name:/Filtros/}).first().click();await expect(page.getByLabel('Ciudad',{exact:true})).toBeVisible();await assertFit();await page.keyboard.press('Escape');
 const search=page.getByPlaceholder('Buscar planes');
 if(!await search.isVisible()) await page.getByRole('button',{name:'Buscar',exact:true}).click();
 await search.fill('Evento extenso');await expect(page.locator('.plan-row')).toHaveCount(1);await assertFit();
 await search.fill('');await expect(page.locator('.plan-row')).toHaveCount(10);
 await page.getByRole('button',{name:/Siguiente/}).click();await expect(page.locator('.plan-row')).toHaveCount(6);await assertFit();
});

for (const width of [1440, 390]) {
 test(`reloads Plans when the published bundle version changes at ${width}px`, async ({ page }) => {
  await page.setViewportSize({ width, height: 1000 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
   if (/Detected multiple instances of (Livewire|Alpine)|Alpine Expression Error/.test(message.text())) {
    errors.push(message.text());
   }
  });

  await login(page, 'plan-bundle-browser@example.test');
  await page.goto('/plans');
  await page.waitForFunction(() => Boolean(window.Livewire?.first()));

  await page.evaluate(() => { window.planNavigationMarker = true; });
  await page.getByRole('link', { name: 'Cena programada', exact: true }).click();
  await expect(page).toHaveURL(/\/plans\/[^/?]+/);
  expect(await page.evaluate(() => window.planNavigationMarker)).toBe(true);
  await page.goBack();
  await expect(page.locator('.plans-list')).toBeVisible();
  expect(await page.evaluate(() => window.planNavigationMarker)).toBe(true);

  const destination = '/plans?sort=name';
  const isDestination = url => url.pathname === '/plans' && url.searchParams.get('sort') === 'name';
  const documentRequests = [];
  page.on('request', request => {
   if (request.resourceType() === 'document' && isDestination(new URL(request.url()))) {
    documentRequests.push(request.url());
   }
  });
  await page.route(isDestination, async route => {
   const response = await route.fetch();
   const html = await response.text();
   await route.fulfill({
    response,
    body: html.replace(/(\/js\/app\.js\?v=)\d+/g, (_, prefix) => prefix + '9876543210'),
   });
  });

  await page.evaluate(url => window.Livewire.navigate(url), destination);
  await expect(page).toHaveURL(isDestination);
  await expect.poll(() => documentRequests.length).toBe(1);
  await expect(page.locator('.plans-list')).toBeVisible();
  await page.waitForFunction(() => Boolean(window.Livewire?.first()));
  await page.locator('.md-module-primary-fab[aria-label="Agregar plan"]').click();
  const form = page.getByRole('dialog', { name: 'Agregar plan', exact: true });
  const isLivewireUpdate = response => response.request().method() === 'POST'
   && new URL(response.url()).pathname.includes('/livewire');
  const category = form.getByRole('combobox', { name: 'Categoría' });
  await category.click();
  await category.fill('Gastronomía');
  const categoryUpdate = page.waitForResponse(isLivewireUpdate);
  await form.getByRole('option', { name: 'Gastronomía', exact: true }).click();
  expect((await categoryUpdate).ok()).toBe(true);
  await expect(category).toHaveValue('Gastronomía');

  for (const [field, option] of [
   ['Etiquetas', 'Etiqueta de prueba'],
   ['Círculos', 'Círculo de prueba'],
   ['Personas', 'Contacto de planes'],
  ]) {
   if (field === 'Círculos') {
    await form.getByRole('button', { name: 'Personas y círculos', exact: true }).click();
   }
   const trigger = form.getByRole('button', { name: field, exact: true });
   await trigger.click();
   const panel = form.getByRole('dialog', { name: field, exact: true });
   const checkbox = panel.getByRole('checkbox', { name: option });
   const update = page.waitForResponse(isLivewireUpdate);
   await checkbox.check();
   expect((await update).ok()).toBe(true);
   await expect(checkbox).toBeChecked();
   await checkbox.press('Escape');
   await expect(panel).toBeHidden();
   await expect(trigger).toHaveText(option);
  }
  await form.getByRole('button', { name: 'Información básica', exact: true }).click();
  await expect(category).toHaveValue('Gastronomía');
  await expect(form.getByRole('button', { name: 'Etiquetas', exact: true })).toHaveText('Etiqueta de prueba');
  expect(errors).toEqual([]);
 });
}

for (const width of [1440, 390]) {
 test(`plan detail actions and shared calendar ${width}`, async ({ page }) => {
  test.setTimeout(60000);
  page.setDefaultTimeout(12000);
  await page.setViewportSize({ width, height: 950 });
  await login(page, 'plan-bundle-browser@example.test');
  await page.goto('/plans?sort=name');
  await page.getByRole('link', { name: `Detalle de prueba ${width}`, exact: true }).click();

  const form = page.getByRole('dialog', { name: 'Editar plan', exact: true });
  const submit = form.getByRole('button', { name: 'Guardar', exact: true });
  const current = name => expect(form.locator('.md-form-dialog__nav-item[aria-current=true]')).toHaveText(name);
  await page.getByRole('button', { name: 'Asociar personas', exact: true }).click();
  await current('Personas y círculos');
  await form.getByRole('button', { name: 'Personas', exact: true }).click();
  const contact = form.getByRole('checkbox', { name: 'Contacto de planes' });
  await contact.check();
  await contact.press('Escape');
  await submit.click();
  await current('Información básica');
  await expect(form.getByRole('button', { name: 'Quitar ciudad anterior' })).toBeFocused();
  await expect(form.getByText('Selecciona una ciudad del catálogo o quita la ciudad anterior.', { exact: true })).toBeVisible();

  const country = form.getByRole('combobox', { name: 'País' });
  await country.click();
  await form.getByRole('option', { name: 'Colombia', exact: true }).click();
  const city = form.getByRole('combobox', { name: 'Ciudad' });
  await expect(city).toBeEnabled();
  await city.fill('Popayan');
  await form.getByRole('option', { name: 'Popayán · Cauca', exact: true }).click();
  await expect(city).toHaveValue('Popayán · Cauca');
  await form.getByLabel('Hora de inicio', { exact: true }).fill('19:00');
  await submit.click();

  await expect(form).toBeHidden();
  await expect(page.getByRole('link', { name: /Contacto de planes/ })).toBeVisible();
  await expect(page.getByText(/7:00 p\. m\./)).toBeVisible();
  const address = page.getByRole('button', { name: /Abrir dirección en mapas/ });
  expect(await address.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
  await address.click();
  await expect(page.getByRole('menuitem', { name: 'Abrir en Google Maps' })).toHaveAttribute('href', /Calle%201%20%232.*%2C%20Popay%C3%A1n/);
  await expect(page.getByRole('menuitem', { name: 'Abrir en Waze' })).toHaveAttribute('href', /^https:\/\/waze.com\/ul\?/);
  await page.keyboard.press('Escape');

  await page.getByRole('button', { name: 'Compartir enlace', exact: true }).click();
  await current('Enlaces');
  const link = form.getByLabel('Enlace', { exact: true }).first();
  const description = form.getByLabel('Descripción (opcional)', { exact: true }).first();
  await expect(link).toHaveAttribute('placeholder', 'https://ejemplo.com');
  await expect(description).toHaveAttribute('placeholder', 'Menú del restaurante');
  await expect(link).toHaveValue('');
  await expect(description).toHaveValue('');
  await link.fill('https://example.test/referencia');
  await expect(form.locator('input[placeholder="https://ejemplo.com"]:placeholder-shown')).toHaveCount(0);
  await link.clear();
  await expect(form.locator('input[placeholder="https://ejemplo.com"]:placeholder-shown')).toHaveCount(1);
  await link.fill('https://example.test/referencia');
  await description.fill('Referencia');
  await form.getByRole('button', { name: 'Añadir enlace' }).click();
  await expect(form.locator('input[placeholder="https://ejemplo.com"]:placeholder-shown')).toHaveCount(1);
  await form.getByRole('button', { name: 'Imágenes', exact: true }).click();
  await form.getByRole('button', { name: 'Añadir imagen' }).click();
  await expect(form.locator('input[placeholder="https://ejemplo.com/imagen.jpg"]:placeholder-shown')).toHaveCount(2);
  await submit.click();
  await expect(page.getByRole('link', { name: /Referencia/ })).toBeVisible();
  for (const [label, section] of [['personas', 'Personas y círculos'], ['enlaces', 'Enlaces']]) {
   await page.getByRole('button', { name: `Más opciones de ${label}`, exact: true }).click();
   await page.getByRole('menuitem', { name: `Gestionar ${label}`, exact: true }).click();
   await current(section);
   if (section === 'Enlaces') {
    await expect(link).toHaveValue('https://example.test/referencia');
    await expect(description).toHaveValue('Referencia');
    await expect(form.getByLabel('Enlace', { exact: true })).toHaveCount(1);
   }
   await form.getByRole('button', { name: 'Cancelar', exact: true }).click();
   await expect(form).toBeHidden();
  }

  const primaryNav = width === 390
   ? page.getByRole('navigation', { name: 'Destinos principales' })
   : page.locator('#app-navigation');
  if (width === 390) {
   await expect(primaryNav.locator('.lt-bottom__item')).toHaveCount(5);
   expect(await primaryNav.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
  }
  await primaryNav.getByRole('link', { name: 'Calendario', exact: true }).click();
  await expect(page).toHaveURL(/\/tasks\/calendar$/);
  await expect(primaryNav.getByRole('link', { name: 'Calendario', exact: true })).toHaveAttribute('aria-current', 'page');
  const timedPlan = page.locator('.fc-timegrid-event.md-calendar-plan').filter({ hasText: `Detalle de prueba ${width}` });
  await expect(timedPlan).toHaveCount(1);
  await expect(timedPlan.locator('.fc-event-time')).toHaveText(/19:00|7:00|7\s*p/);
  await expect(page.getByRole('navigation', { name: 'Vistas del módulo' }).getByRole('link', { name: 'Calendario', exact: true })).toHaveAttribute('aria-current', 'page');

  if (width === 390) {
   await page.locator('#calendar-view').selectOption('dayGridMonth');
  } else {
   await page.getByRole('button', { name: 'Mes', exact: true }).click();
  }
  await expect(page.locator('.fc-daygrid-event.md-calendar-plan').filter({ hasText: `Detalle de prueba ${width}` })).toHaveCount(1);
  if (width === 390) {
   await page.setViewportSize({ width: 320, height: 950 });
   expect(await primaryNav.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
  }
  await primaryNav.getByRole('link', { name: 'Tareas', exact: true }).click();
  await expect(page).toHaveURL(/\/tasks\/list/);
  await page.getByRole('navigation', { name: 'Vistas del módulo' }).getByRole('link', { name: 'Calendario', exact: true }).click();
  await expect(page).toHaveURL(/\/tasks\/calendar$/);
  await expect(timedPlan).toHaveCount(1);
 });
}
