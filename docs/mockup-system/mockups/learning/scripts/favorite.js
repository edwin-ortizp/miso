import {images} from './favorite-art.js';
// Prototype only: the collection and favorite live in the learning transaction store.
const species={25:{name:'Pikachu',description:'Una chispa de compañía para tus días.'},133:{name:'Eevee',description:'Muchos caminos por explorar, uno a la vez.'},63:{name:'Abra',description:'Compañía tranquila para avanzar a tu ritmo.'}};
export function mountFavorite({root,scope,getState,update,open,close,view}){
 const $=s=>root.querySelector(s),dock=$('[data-favorite-dock]');
 // Floating companion, opposite the creation FAB; shared across this prototype only.
 $('.lt-shell').append(dock);
 const account=$('.lt-profile');account.removeAttribute('data-lt-static-nav');account.removeAttribute('aria-haspopup');account.setAttribute('aria-haspopup','dialog');account.dataset.favoriteAction='account';
 const art=id=>`<img src="${images[id]}" width="48" height="48" alt="">`;
 root.querySelectorAll('[data-guardian-art]').forEach(el=>{el.innerHTML=art(63);});
 let chooserFromProfile=false;
 const bubble=$('[data-favorite-bubble]');
 const quiet=()=>{bubble.hidden=true;dock.querySelector('[data-favorite-action=open]').setAttribute('aria-expanded','false');};
 const presence=()=>view==='tasks'?(root.querySelector('[data-learning-fab] .lt-fab-split__main')||dock.querySelector('[data-favorite-action=open]')):dock.querySelector('[data-favorite-action=open]');
 function render(){
  const state=getState(),buddy=species[state.favorite];
  dock.hidden=state.companionHidden||view==='tasks';
  $('[data-favorite-image]').innerHTML=buddy?art(state.favorite):'<i class="bi bi-heart" aria-hidden="true"></i>';
  dock.querySelector('[data-favorite-action=open]').setAttribute('aria-label',buddy?`Tu compañero: ${buddy.name}`:'Elegir compañero');
  $('[data-favorite-profile]').innerHTML=buddy?`${art(state.favorite)}<h3>${buddy.name}</h3><p>${buddy.description}</p><span class="lt-chip">Tu favorito · Líder de corazón</span>`:'';
  $('[data-favorite-choices]').innerHTML=state.collection.map(id=>`<button type="button" class="lt-favorite-choice" data-favorite-action="select" data-species="${id}" aria-pressed="${state.favorite===id}">${art(id)}<strong>${species[id].name}</strong><small>${id===63?'Equipo semanal · Guardián':'Fuera del equipo semanal'}</small></button>`).join('');
  $('[data-favorite-empty]').hidden=state.collection.length>0;
  $('[data-favorite-restore]').textContent=state.companionHidden?'Mostrar compañero':buddy?'Ver compañero':'Elegir compañero';
  $('[data-favorite-cry]').textContent=buddy?({25:'¡Pika, pika!',133:'¡Vui!',63:'¡Abra!'}[state.favorite]):'¿Vamos juntos?';
  $('[data-favorite-line]').textContent=buddy?({focus:'Una cosa a la vez. Yo te acompaño.',tasks:'Una tarea a la vez. ¿Por cuál empezamos?',habits:'Un paso pequeño también cuenta.',goals:'Tus próximos pasos tienen compañía.'}[view]||'¡Listo para explorar contigo!'):'Elige quién te acompaña de tu colección.';
  $('[data-favorite-details]').textContent=buddy?'Ver compañero':'Elegir compañero';
  const focus=$('[data-favorite-focus]');
  if(focus){focus.hidden=true;focus.replaceChildren();}
 }
 function returnFromDialog(id){
  queueMicrotask(()=>{if(id==='favorite-chooser'&&chooserFromProfile){open('favorite-profile');queueMicrotask(()=>$('[data-favorite-action=choose]').focus());}else if(id==='favorite-account')account.focus();else presence()?.focus();});
 }
 scope.on(root,'keydown',event=>{if(event.key==='Escape'&&!bubble.hidden){event.preventDefault();event.stopImmediatePropagation();quiet();presence().focus();return;}if(event.key==='Escape'){const layer=[...root.querySelectorAll('.lt-dialog-layer')].find(el=>!el.hidden&&el.id.startsWith('favorite-'));if(layer){event.preventDefault();event.stopImmediatePropagation();close(layer.id);returnFromDialog(layer.id);}}});
 scope.on(root,'click',event=>{
  if(!dock.contains(event.target))quiet();
  const toggle=event.target.closest('[data-lt-toggle]');if(toggle?.dataset.ltToggle.startsWith('favorite-')&&toggle.closest('.lt-dialog-layer'))returnFromDialog(toggle.dataset.ltToggle);
  const target=event.target.closest('[data-favorite-action]');if(!target)return;
  const action=target.dataset.favoriteAction;
  if(action==='account')open('favorite-account');
  if(action==='open'){const expanded=bubble.hidden;bubble.hidden=!expanded;presence().setAttribute('aria-expanded',String(expanded));if(expanded)bubble.querySelector('button').focus();}
  if(action==='quiet'){quiet();presence().focus();}
  if(action==='profile'){quiet();chooserFromProfile=false;open(getState().favorite?'favorite-profile':'favorite-chooser');}
  if(action==='choose'){chooserFromProfile=!$('#favorite-profile').hidden;open('favorite-chooser');}
  if(action==='select'){
   const id=Number(target.dataset.species);if(!getState().collection.includes(id))return;
   update(s=>{s.favorite=id;s.companionHidden=false;},false);render();close('favorite-chooser');root.dispatchEvent(new CustomEvent('favorite-changed'));presence()?.focus();
  }
  if(action==='hide'){quiet();update(s=>{s.companionHidden=true;},false);render();close('favorite-profile');root.dispatchEvent(new CustomEvent('favorite-changed'));account.focus();}
  if(action==='restore'){
   const wasHidden=getState().companionHidden;
   update(s=>{s.companionHidden=false;},false);render();close('favorite-account');root.dispatchEvent(new CustomEvent('favorite-changed'));
   if(wasHidden)presence()?.focus();else open(getState().favorite?'favorite-profile':'favorite-chooser');
  }
 });
 return {render};
}
