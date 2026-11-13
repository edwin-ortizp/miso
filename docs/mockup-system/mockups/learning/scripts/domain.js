import {creationDefaults} from './task-creation-domain.js';
export const iso = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
export const dateAt = s => new Date(s+'T12:00:00');
export function offset(day, n) { const d=dateAt(day); d.setDate(d.getDate()+n); return iso(d); }
export function seed(settings={}) {
 const today=iso(new Date()); const monday=offset(today,-((dateAt(today).getDay()+6)%7));
 const state={id:'learning-state',taskCreation:creationDefaults(),collection:settings.collectionEmpty?[]:[25,133,63],favorite:settings.collectionEmpty||settings.noFavorite?null:25,companionHidden:false,selected:'english',selectedNode:'',pins:[],team:settings.team!==false,tab:'route',layers:{tasks:true,slots:true,plans:true,birthdays:true,meals:true},calendarDate:today,
 learnings:settings.empty?[]:[{id:'master',title:'Maestría en gestión de proyectos',type:'Formación universitaria',category:'Profesional',status:'En curso',description:'Organizar el semestre y avanzar con las entregas.',goal:'career'},{id:'english',title:'Ruta de inglés B1',type:'Idioma',category:'Personal',status:'En curso',description:'Aprender paso a paso, con práctica y tiempo reservado.',goal:'english-goal'}],
 nodes:settings.empty?[]:[{id:'semester',learning:'master',parent:'',title:'Semestre 1',status:'En curso',materials:''},{id:'research',learning:'master',parent:'semester',title:'Metodología de investigación',status:'En curso',materials:'Guía de investigación · capítulos 1 y 2'},{id:'finance',learning:'master',parent:'semester',title:'Finanzas de proyectos',status:'Pendiente',materials:''},{id:'foundation',learning:'english',parent:'',title:'Etapa 1 · Bases para conversar',status:'En curso',materials:'Cuaderno de vocabulario personal'},{id:'conversation',learning:'english',parent:'foundation',title:'Presentarme y hablar de mi semana',status:'En curso',materials:'Practicar una presentación de dos minutos'}],
 goals:[{id:'english-goal',title:'Aprender inglés',description:'Mantener una conversación cotidiana en inglés.',progress:35},{id:'career',title:'Fortalecer mi perfil profesional',description:'Aplicar lo aprendido en proyectos reales.',progress:20}],
 tasks:[{id:'t1',title:'Practicar mi presentación en inglés',description:'Preparar una presentación de dos minutos y grabarla.',date:offset(monday,4),endDate:offset(monday,4),time:'',endTime:'',status:'ready',category:'Personal',estimate:30,priority:'not-urgent-important',size:'S',private:false,recurrent:false,reminders:[],relations:[]},{id:'t2',title:'Escuchar y resumir un episodio',description:'Anotar cinco expresiones nuevas.',date:offset(monday,5),endDate:offset(monday,5),time:'',endTime:'',status:'todo',category:'Personal',estimate:25,reminders:[],relations:[]},{id:'t3',title:'Entregar propuesta de investigación',description:'Completar problema, pregunta y referencias.',date:offset(monday,4),endDate:offset(monday,4),time:'',endTime:'',status:'wip',category:'Profesional',estimate:120,reminders:[],relations:[]},{id:'t4',title:'Revisar pendientes de la semana',description:'Acción recurrente: se muestra su ocurrencia actual.',date:monday,endDate:monday,time:'09:00',endTime:'09:30',status:'ready',category:'Personal',estimate:30,recurrent:true,recurDays:7,reminders:[],relations:[]}],
 habits:[{id:'h1',title:'Practicar inglés cuatro días por semana',done:false},{id:'h2',title:'Leer veinte minutos',done:false}],
 links:settings.empty?[]:[{id:'a1',node:'conversation',kind:'task',target:'t1'},{id:'a2',node:'conversation',kind:'task',target:'t2'},{id:'a3',node:'research',kind:'task',target:'t3'},{id:'a4',node:'foundation',kind:'habit',target:'h1'}],
 grades:settings.empty?[]:[{id:'g1',node:'research',title:'Propuesta inicial',weight:30,min:0,max:5,score:4.2},{id:'g2',node:'research',title:'Trabajo final',weight:70,min:0,max:5,score:null}],
 slots:settings.empty?[]:[{id:'s1',title:'Inglés · práctica y escucha',learning:'english',date:monday,start:'16:00',end:'18:00',frequency:'weekly',interval:1,days:[1,3],until:offset(monday,90),tasks:['t1','t2'],exceptions:{}},{id:'s2',title:'Investigación · avanzar propuesta',learning:'master',date:offset(monday,1),start:'17:00',end:'18:30',frequency:'weekly',interval:1,days:[2,4],until:offset(monday,90),tasks:['t3'],exceptions:{}}],
 sessions:settings.empty?[]:[{id:'f1',task:'t1',minutes:25,date:today}],focus:{task:'t1',startedAt:null,elapsed:0},next:100};
 if(!settings.empty){
  state.learnings[0].type='Maestría';state.learnings[1].type='Ruta personal';state.learnings[1].category='Idiomas';
  state.learnings.push({id:'scrum',title:'Scrum: fundamentos y práctica',type:'Curso',category:'Agilidad',status:'En curso',description:'Comprender Scrum y practicar sus eventos.',goal:'career'}, {id:'degree',title:'Ingeniería de Sistemas',type:'Carrera universitaria',category:'Tecnología',status:'En curso',description:'Organizar periodos, materias y entregas de la carrera.',goal:''});
  const kinds={semester:'period',research:'subject',finance:'subject',foundation:'stage',conversation:'topic'};
  state.nodes.forEach(n=>n.kind=kinds[n.id]);
  state.nodes.push({id:'scrum-events',learning:'scrum',parent:'',kind:'module',title:'Eventos de Scrum',status:'En curso',description:'Propósito y práctica de cada evento.'},{id:'degree-period',learning:'degree',parent:'',kind:'period',title:'Semestre 1',status:'En curso'},{id:'degree-math',learning:'degree',parent:'degree-period',kind:'subject',title:'Cálculo diferencial',status:'En curso'});
 }
 // Fictional examples chosen manually; creation and status changes never pin anything.
 if(!settings.empty){
  state.learnings.push({id:'guitar',title:'Clases de guitarra',type:'Curso',category:'Personal',status:'En curso',description:'Aprender acordes y practicar canciones.',goal:''});
  state.pins=['node:degree-period','formation:english','formation:guitar'];
 }
 state.materials=state.nodes.filter(n=>n.materials).map((n,i)=>({id:'m'+i,node:n.id,title:n.materials,type:'Apunte',content:n.materials}));
 return state;
}
export function occurrences(slot,start,end) {
 const result=[];const anchor=dateAt(slot.date);const range=dateAt(start);const finish=dateAt(end);const interval=Number(slot.interval)||1;
 const monday=d=>{const x=new Date(d);x.setDate(x.getDate()-((x.getDay()+6)%7));x.setHours(12,0,0,0);return x;};
 // Iterate only the visible range. DST-safe calendar-day differences, not elapsed local hours.
 const ordinal=d=>Date.UTC(d.getFullYear(),d.getMonth(),d.getDate())/86400000;
 for(let d=new Date(range);d<finish;d.setDate(d.getDate()+1)){
  const key=iso(d);if(key<slot.date||(slot.until&&key>slot.until))continue;
  const delta=ordinal(d)-ordinal(anchor);
  const matches=slot.frequency==='none'?key===slot.date:slot.frequency==='daily'?delta%interval===0:((ordinal(monday(d))-ordinal(monday(anchor)))/7)%interval===0&&(slot.days.length?slot.days:[anchor.getDay()]).includes(d.getDay());
  if(matches&&!Object.hasOwn(slot.exceptions||{},key))result.push({...slot,occurrence:key,date:key});
 }
 for(const [key,change] of Object.entries(slot.exceptions||{}))if(change&&!change.cancelled&&change.date>=start&&change.date<end)result.push({...slot,...change,occurrence:key});
 return result;
}
export function gradeResult(grades){
 const weight=grades.reduce((s,g)=>s+Number(g.weight),0);const pending=grades.some(g=>g.score===null);const normalized=grades.reduce((s,g)=>s+(g.score===null?0:((g.score-g.min)/(g.max-g.min))*g.weight),0);
 return {weight,pending,final:grades.length>0&&!pending&&Math.abs(weight-100)<0.001,percent:normalized};
}
export const FORMATION_TYPES=['Carrera universitaria','Especialización','Maestría','Doctorado','Curso','Ruta personal'];
export const NODE_LABELS={period:'Periodo',subject:'Materia',module:'Módulo',topic:'Tema',stage:'Etapa',course:'Curso'};
export function formationKind(type){return type==='Curso'?'course':type==='Ruta personal'?'personal':'academic';}
export function childKinds(kind){return ({academic:['period','subject'],period:['subject'],course:['module'],personal:['stage','course','topic'],stage:['course','topic'],subject:['topic'],module:['topic'],topic:[]})[kind]||[];}
export const resourceKinds=new Set(['course','subject','module','topic','stage']);
export function ownerIds(state,learning,node=''){
 if(!node)return new Set(['formation:'+learning,...state.nodes.filter(n=>n.learning===learning).map(n=>n.id)]);
 const ids=new Set([node]);let changed=true;while(changed){changed=false;state.nodes.filter(n=>n.learning===learning&&ids.has(n.parent)&&!ids.has(n.id)).forEach(n=>{ids.add(n.id);changed=true;});}return ids;
}
export function learningTaskIds(state,id,node=''){const owners=ownerIds(state,id,node);return [...new Set(state.links.filter(l=>l.kind==='task'&&owners.has(l.node)).map(l=>l.target))];}
export function focusMinutes(state,id,node=''){const tasks=new Set(learningTaskIds(state,id,node));return state.sessions.filter(s=>tasks.has(s.task)).reduce((sum,s)=>sum+s.minutes,0);}
export function validNodeContext(state,value){
 const learning=state.learnings.find(l=>l.id===value.learning);if(!learning)return false;
 const parent=value.parent?state.nodes.find(n=>n.id===value.parent&&n.learning===value.learning):null;
 if(value.parent&&!parent)return false;
 if(parent&&value.id&&ownerIds(state,value.learning,value.id).has(parent.id))return false;
 return childKinds(parent?.kind||formationKind(learning.type)).includes(value.kind);
}
export function saveSlot(state,value){const index=state.slots.findIndex(s=>s.id===value.id);if(index<0){state.slots.push({...value,id:'s'+state.next++,exceptions:{}});return;}
 const original=state.slots[index];if(value.scope==='one'){original.exceptions[value.occurrence] = {...value};}else{state.slots[index]={...value,exceptions:{}};}}
export function cancelSlot(state,id,day,scope){const slot=state.slots.find(s=>s.id===id);if(scope==='series')state.slots=state.slots.filter(s=>s.id!==id);else slot.exceptions[day]={cancelled:true};}

// Resolve current labels at render time so renaming a formation updates its shortcuts.
export function pinTarget(state,key){
 const split=key.indexOf(':');const type=key.slice(0,split),id=key.slice(split+1);
 if(type==='formation'){
  const formation=state.learnings.find(l=>l.id===id);
  return formation?{key,formation:formation.id,node:'',title:formation.title,type:formation.type,origin:'Formación',href:'/learning-detail?formation='+encodeURIComponent(id)+'&unit='}:null;
 }
 if(type!=='node')return null;
 const node=state.nodes.find(n=>n.id===id),formation=node&&state.learnings.find(l=>l.id===node.learning);
 if(!node||!formation)return null;
 const parents=[],seen=new Set([id]);let parent=node.parent;
 while(parent){if(seen.has(parent))break;seen.add(parent);const item=state.nodes.find(n=>n.id===parent&&n.learning===formation.id);if(!item)break;parents.unshift(item.title);parent=item.parent;}
 return {key,formation:formation.id,node:id,title:node.title,type:NODE_LABELS[node.kind],origin:[formation.title,...parents].join(' / '),href:'/learning-detail?formation='+encodeURIComponent(formation.id)+'&unit='+encodeURIComponent(id)};
}
export function pinnedTargets(state){return [...new Set(state.pins||[])].map(key=>pinTarget(state,key)).filter(Boolean);}
export function togglePin(state,key){
 if(!pinTarget(state,key))return false;
 state.pins ||= [];
 if(state.pins.includes(key)){state.pins=state.pins.filter(item=>item!==key);return false;}
 state.pins.push(key);return true;
}
