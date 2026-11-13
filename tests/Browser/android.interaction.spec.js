import {test,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
for (const width of [390,1440]) {
 test(`Android guard: cierre, descarte y foco a ${width}px`,async({page})=>{
  await page.setViewportSize({width,height:844});
  await page.goto('/ui-catalog/form-dialog');
  await page.getByRole('button',{name:'Probar formulario',exact:true}).click();
  const form=page.getByRole('dialog',{name:'Formulario de prueba',exact:true});
  await expect(form).toBeVisible();
  await form.getByLabel('Texto de prueba').fill('Diario 🌱 sin guardar');
  await form.getByRole('button',{name:'Cerrar',exact:true}).click();
  const confirm=page.getByRole('dialog',{name:'Tienes cambios sin guardar',exact:true});
  await expect(confirm).toBeVisible();
  if (width === 390) await page.screenshot({path:'storage/framework/testing/android-discard-mobile.png'});
  await confirm.getByRole('button',{name:'Seguir editando',exact:true}).click();
  await expect(confirm).toHaveCount(0);
  await expect(form.getByLabel('Texto de prueba')).toHaveValue('Diario 🌱 sin guardar');
  await form.getByRole('button',{name:'Cancelar',exact:true}).click();
  await expect(confirm).toBeVisible();
  await confirm.getByRole('button',{name:'Descartar cambios',exact:true}).click();
  await expect(form).toBeHidden();
  await expect(page.getByRole('button',{name:'Probar formulario',exact:true})).toBeFocused();
 });
}
test('puente: Atrás protege el formulario y no navega al cancelar descarte',async({page})=>{
 await page.addInitScript(()=>{window.nativeMessages=[];window.MisoNative={postMessage:raw=>window.nativeMessages.push(JSON.parse(raw))};});
 await page.goto('/ui-catalog/form-dialog');
 await page.getByRole('button',{name:'Probar formulario',exact:true}).click();
 const form=page.getByRole('dialog',{name:'Formulario de prueba',exact:true});
 await form.getByLabel('Texto de prueba').fill('Pendiente');
 await form.getByRole('button',{name:'Opciones del formulario',exact:true}).click();
 await expect(form.getByRole('menu')).toBeVisible();
 await page.evaluate(()=>window.MisoAndroid.back('nested-menu'));
 await expect(form.getByRole('menu')).toBeHidden();
 await expect(page.getByRole('dialog',{name:'Tienes cambios sin guardar',exact:true})).toHaveCount(0);
 await page.evaluate(()=>window.MisoAndroid.back('one'));
 const confirm=page.getByRole('dialog',{name:'Tienes cambios sin guardar',exact:true});
 await expect(confirm).toBeVisible();
 const results=await new AxeBuilder({page}).include('[aria-modal="true"]').analyze();
 expect(results.violations).toEqual([]);
 await confirm.getByRole('button',{name:'Seguir editando',exact:true}).click();
 await expect(form).toBeVisible();
 await page.evaluate(()=>window.MisoAndroid.back('two'));
 await expect(confirm).toBeVisible();
 await confirm.getByRole('button',{name:'Descartar cambios',exact:true}).click();
 await expect(form).toBeHidden();
 await expect.poll(()=>page.evaluate(()=>window.nativeMessages.filter(m=>m.type==='backResult').length)).toBe(3);
});

test('puente: Atrás cierra el menú superior sin abandonar la pantalla',async({page})=>{
 await page.addInitScript(()=>{window.nativeMessages=[];window.MisoNative={postMessage:raw=>window.nativeMessages.push(JSON.parse(raw))};});
 await page.goto('/ui-catalog/menu');
 await page.getByRole('button',{name:'Más opciones del evento',exact:true}).click();
 await expect(page.getByRole('menu')).toBeVisible();
 await page.evaluate(()=>window.MisoAndroid.back('menu'));
 await expect(page.getByRole('menu')).toBeHidden();
 await expect(page).toHaveURL(/ui-catalog\/menu/);
});
test('guardar correctamente reinicia la protección y cerrar limpio no confirma',async({page})=>{
 await page.goto('/ui-catalog/form-dialog');
 await page.getByRole('button',{name:'Probar formulario',exact:true}).click();
 const form=page.getByRole('dialog',{name:'Formulario de prueba',exact:true});
 await form.getByLabel('Texto de prueba').fill('Texto guardado');
 await form.getByRole('button',{name:'Guardar',exact:true}).click();
 await expect(form).toBeHidden();
 await page.getByRole('button',{name:'Probar formulario',exact:true}).click();
 await form.getByRole('button',{name:'Cerrar',exact:true}).click();
 await expect(form).toBeHidden();
 await expect(page.getByRole('dialog',{name:'Tienes cambios sin guardar',exact:true})).toHaveCount(0);
});

test('abrir un aviso conserva la edición si se cancela el descarte',async({page})=>{
 await page.addInitScript(()=>{window.MisoNative={postMessage:()=>{}};});
 let opened=0;
 await page.route('**/android/notifications/*',route=>{opened++;return route.fulfill({body:'Destino de prueba'});});
 await page.goto('/ui-catalog/form-dialog');
 await page.getByRole('button',{name:'Probar formulario',exact:true}).click();
 const form=page.getByRole('dialog',{name:'Formulario de prueba',exact:true});
 await form.getByLabel('Texto de prueba').fill('Borrador conservado');
 await page.evaluate(()=>{window.MisoAndroid.openNotice('123e4567-e89b-42d3-a456-426614174000');});
 const confirm=page.getByRole('dialog',{name:'Tienes cambios sin guardar',exact:true});
 await expect(confirm).toBeVisible();
 await confirm.getByRole('button',{name:'Seguir editando',exact:true}).click();
 await expect(form.getByLabel('Texto de prueba')).toHaveValue('Borrador conservado');
 expect(opened).toBe(0);
 await page.evaluate(()=>{window.MisoAndroid.openNotice('123e4567-e89b-42d3-a456-426614174000');});
 await confirm.getByRole('button',{name:'Descartar cambios',exact:true}).click();
 await expect(page).toHaveURL(/android\/notifications\/123e4567/);
 expect(opened).toBe(1);
});
