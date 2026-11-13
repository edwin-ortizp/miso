import {images} from './favorite-art.js';
import {appendTasks,creationDefaults,emptyTaskDraft,taskTitles,validateTitles} from './task-creation-domain.js';

export function mountTaskCreation({root,scope,getState,update,open,close,openTask,feedback,view}){
 if(view!=='tasks')return {render(){},singleSaved(){}};
 const $=s=>root.querySelector(s),panel=$('#quick-task-panel'),form=$('[data-quick-form]'),bulkForm=$('[data-bulk-task-form]');
 panel.classList.add('lt-quick-layer');panel.querySelector('[role=dialog]').removeAttribute('aria-modal');panel.querySelector('.lt-dialog-scrim').hidden=true;panel.querySelectorAll('[data-lt-toggle=quick-task-panel]').forEach(b=>{b.removeAttribute('data-lt-toggle');b.dataset.quickAction='close';});
 let kind='single',fromSingle=false,pendingPokemon=false,submissionLocked=false,savedAt=-Infinity;
 const suppressRepeatedClick=event=>{if(event.detail>1&&performance.now()-savedAt<500){event.preventDefault();event.stopImmediatePropagation();}};
 root.addEventListener('click',suppressRepeatedClick,true);scope.cleanup(()=>root.removeEventListener('click',suppressRepeatedClick,true));
 const creation=()=>getState().taskCreation;
 const main=()=>$('[data-learning-fab] .lt-fab-split__main');
 const pokemon=()=>creation().mode==='pokemon'&&getState().favorite&&!getState().companionHidden;
 const draft=()=>creation()[kind];
 const focusTitle=()=>form.elements[kind==='single'?'title':'titles'].focus();
 const focusMain=()=>main()?.focus();
 const syncLauncher=()=>{const fab=$('[data-learning-fab]');fab.classList.toggle('is-chat-open',!panel.hidden);main()?.setAttribute('aria-expanded',String(!panel.hidden));};
 function saveDraft(){update(s=>{s.taskCreation[kind]={...(kind==='single'?{title:form.elements.title.value}:{titles:form.elements.titles.value}),category:form.elements.category.value};},false);}
 function syncPanel(){
  const bulk=kind==='bulk',data=draft();
  $('[data-quick-single]').hidden=bulk;$('[data-quick-bulk]').hidden=!bulk;
  form.elements.title.value=bulk?'':data.title;form.elements.titles.value=bulk?data.titles:'';form.elements.category.value=data.category;
  $('#quick-task-panel-title').textContent={25:'Pikachu',133:'Eevee',63:'Abra'}[getState().favorite]||'Tu compañero';
  $('[data-quick-greeting]').textContent=bulk?'Pásame tu lista. Una tarea por línea y les hacemos espacio.':'¿Qué quieres sacar de tu cabeza? Lo anotamos aquí.';
  root.querySelectorAll('[data-quick-action=single],[data-quick-action=bulk]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.quickAction===kind)));
  $('[data-quick-error]').textContent='';$('[data-quick-result]').textContent=creation().lastMessage||'';submissionLocked=false;updateCount();
 }
 function updateCount(){
  const count=taskTitles(form.elements.titles.value,true).length;
  $('[data-quick-action=create]').textContent=kind==='bulk'?`Crear ${count} tareas`:'Crear tarea';
  $('[data-quick-action=create]').disabled=submissionLocked;
 }
 function launch(next){
  kind=next;$('#lt-task-create-menu').hidden=true;
  root.querySelectorAll('[aria-controls=lt-task-create-menu], [data-lt-toggle=lt-task-create-menu]').forEach(b=>b.setAttribute('aria-expanded','false'));
  if(pokemon()){
   syncPanel();open('quick-task-panel');syncLauncher();focusTitle();queueMicrotask(focusTitle);
  }else advanced(next);
 }
 function advanced(next=kind){
  panel.hidden=true;syncLauncher();kind=next;const data=draft();
  if(kind==='single'){
   openTask(null);fromSingle=true;
   $('[data-cal-f-title]').value=data.title;$('[data-cal-q-category]').value=data.category||'Personal';
   queueMicrotask(()=>$('[data-cal-f-title]').focus());
  }else{
   bulkForm.reset();$('#bulk-task-editor').querySelectorAll('.lt-dialog__section').forEach(el=>el.hidden=el.dataset.section!=='basic');$('#bulk-task-editor').querySelectorAll('[data-lt-dialog-section]').forEach(el=>el.dataset.ltDialogSection==='basic'?el.setAttribute('aria-current','true'):el.removeAttribute('aria-current'));Object.assign(bulkForm.elements.titles,{value:data.titles});bulkForm.elements.category.value=data.category;
   $('[data-bulk-task-error]').textContent='';$('[data-quick-action=bulk-create]').disabled=false;
   open('bulk-task-editor');queueMicrotask(()=>bulkForm.elements.titles.focus());
  }
 }
 function complete(titles,values,source){
  savedAt=performance.now();
  update(s=>{appendTasks(s,titles,{...values,category:values.category||'Personal'});s.taskCreation[kind]=emptyTaskDraft(kind==='bulk');s.taskCreation.lastMessage=titles.length===1?'¡Anotada! Ya tiene su lugar.':`¡Listas! ${titles.length} tareas en tu lista.`;});
  feedback(`${titles.length===1?'Tarea creada':titles.length+' tareas creadas'}.`);
  if(source==='quick'){
   syncPanel();submissionLocked=true;updateCount();
   $('[data-quick-result]').textContent=titles.length===1?'¡Anotada! Ya tiene su lugar.':`¡Listas! ${titles.length} tareas en tu lista.`;
   focusTitle();
  }else{close('bulk-task-editor');focusMain();}
 }
 function quickSave(){
  if(panel.hidden||submissionLocked)return;
  saveDraft();const titles=taskTitles(kind==='single'?draft().title:draft().titles,kind==='bulk'),error=validateTitles(titles);
  if(error){$('[data-quick-error]').textContent=error;focusTitle();return;}
  complete(titles,{category:draft().category},'quick');
 }
 function bulkSave(){
  if($('#bulk-task-editor').hidden||$('[data-quick-action=bulk-create]').disabled)return;
  const values=Object.fromEntries(new FormData(bulkForm)),titles=taskTitles(values.titles,true);
  const error=validateTitles(titles)||(values.date&&values.endDate&&values.endDate<values.date?'La fecha final no puede ser anterior al inicio.':'');
  if(error){$('[data-bulk-task-error]').textContent=error;return;}
  values.private=bulkForm.elements.private.checked;delete values.titles;
  $('[data-quick-action=bulk-create]').disabled=true;kind='bulk';complete(titles,values,'bulk');
 }
 function render(){
  if(!creation())update(s=>{s.taskCreation=creationDefaults();},false);
  $('[data-favorite-dock]').hidden=true;
  const settings=$('[data-task-creation-settings]');settings.hidden=false;
  const select=$('[data-task-creation-mode]');select.value=pokemon()?'pokemon':'traditional';select.querySelector('[value=pokemon]').disabled=!getState().collection.length;
  $('[data-task-creation-hint]').textContent=getState().collection.length?'Elige cómo quieres crear tus tareas.':'Descubre tu primer Pokémon para activar esta presentación.';
  const fab=$('[data-learning-fab]');fab.classList.toggle('lt-task-create--pokemon',!!pokemon());
  fab.querySelectorAll('[data-lt-toggle="task-dialog"],[data-lt-toggle="bulk-task-editor"]').forEach(b=>{b.dataset.quickAction=b.dataset.ltToggle==='task-dialog'?'launch-single':'launch-bulk';b.removeAttribute('data-lt-toggle');});
  if(pokemon()){
   main().innerHTML=`<img src="${images[getState().favorite]}" width="48" height="48" alt="">`;
   main().setAttribute('aria-label','Abrir creación de tareas con tu Pokémon');main().setAttribute('aria-controls','quick-task-panel');syncLauncher();
   $('[data-quick-art]').innerHTML=`<img src="${images[getState().favorite]}" width="48" height="48" alt="">`;
   panel.querySelector('.lt-dialog__icon').innerHTML=`<img src="${images[getState().favorite]}" width="40" height="40" alt="">`;
  }
 }
 scope.on(root,'favorite-changed',()=>{if(pendingPokemon){pendingPokemon=false;update(s=>{s.taskCreation.mode='pokemon';s.companionHidden=false;});focusMain();}else render();});
 scope.on(root,'change',event=>{
  if(event.target.matches('[data-task-creation-mode]')){
   if(event.target.value==='pokemon'&&!getState().favorite){pendingPokemon=true;event.target.value='traditional';open('favorite-chooser');return;}
   const mode=event.target.value;update(s=>{s.taskCreation.mode=mode;if(mode==='pokemon')s.companionHidden=false;});
  }
 });
 scope.on(root,'input',event=>{
  if(form.contains(event.target)){saveDraft();submissionLocked=false;updateCount();$('[data-quick-error]').textContent='';}
  if(bulkForm.contains(event.target))update(s=>{s.taskCreation.bulk={titles:bulkForm.elements.titles.value,category:bulkForm.elements.category.value};},false);
  if(fromSingle&&event.target.closest('#task-dialog'))update(s=>{s.taskCreation.single={title:$('[data-cal-f-title]').value,category:$('[data-cal-q-category]').value};},false);
 });
 scope.on(root,'submit',event=>{if(event.target===form){event.preventDefault();quickSave();}if(event.target===bulkForm){event.preventDefault();bulkSave();}});
 scope.on(root,'click',event=>{
  if(!panel.hidden&&!panel.contains(event.target)&&!event.target.closest('[data-learning-fab]')){panel.hidden=true;syncLauncher();}
  const b=event.target.closest('button');if(!b)return;
  if(b.dataset.ltToggle==='favorite-chooser')pendingPokemon=false;
  if(!panel.hidden&&!panel.contains(b)&&!b.closest('[data-learning-fab]')){panel.hidden=true;syncLauncher();}
  const action=b.dataset.quickAction;
  if(action==='close'||b.dataset.ltToggle==='lt-task-create-menu'){panel.hidden=true;syncLauncher();if(action==='close')focusMain();}
  if(action==='launch-single'||action==='launch-bulk'){if(!panel.hidden&&b===main()){panel.hidden=true;syncLauncher();focusMain();}else launch(action==='launch-single'?'single':'bulk');}
  if(action==='single'||action==='bulk'){saveDraft();kind=action;syncPanel();focusTitle();}
  if(action==='advanced'){saveDraft();advanced();}
  if(action==='create')quickSave();if(action==='bulk-create')bulkSave();
  if(['quick-task-panel','bulk-task-editor','task-dialog'].includes(b.dataset.ltToggle)&&b.closest('.lt-dialog-layer')){if(b.dataset.ltToggle==='task-dialog')fromSingle=false;syncLauncher();queueMicrotask(focusMain);}
  if(b.dataset.learningAction==='task-open')fromSingle=false;syncLauncher();
 });
 scope.on(root,'keydown',event=>{if(event.key!=='Escape')return;if(!$('#favorite-chooser').hidden)pendingPokemon=false;const layer=[panel,$('#bulk-task-editor'),$('#task-dialog')].find(el=>!el.hidden);if(layer){fromSingle=false;event.preventDefault();event.stopImmediatePropagation();close(layer.id);syncLauncher();focusMain();}});
 // visualViewport follows the on-screen keyboard without reflowing the application shell.
 const viewport=window.visualViewport;
 if(viewport){const resize=()=>{panel.style.setProperty('--quick-bottom',Math.max(0,innerHeight-viewport.height-viewport.offsetTop)+'px');};viewport.addEventListener('resize',resize);scope.cleanup(()=>viewport.removeEventListener('resize',resize));resize();}
 return {render,singleSaved(){if(fromSingle){savedAt=performance.now();update(s=>{s.taskCreation.single=emptyTaskDraft(false);},false);fromSingle=false;}queueMicrotask(focusMain);}};
}
