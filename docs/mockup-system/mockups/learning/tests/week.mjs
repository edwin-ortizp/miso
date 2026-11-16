// CHG-121: lógica de «Esta semana» sin navegador. Ejecutar: node docs/mockup-system/mockups/learning/tests/week.mjs
import assert from 'node:assert/strict';
import {seed,weekAgenda,iso,offset} from '../scripts/domain.js';
const today=iso(new Date());
const state=seed({});
const agenda=weekAgenda(state,today);
assert(agenda.classes.length>=2,'hay clases hoy y mañana');
assert(agenda.classes.every(c=>['Hoy','Mañana'].includes(c.day)),'solo hoy y mañana');
assert.equal(agenda.classes[0].where,'Aula 3');assert.equal(agenda.classes[0].who,'Prof. Ruiz');
const english=agenda.classes.find(c=>c.title==='Clase de inglés');assert.equal(english.who,'','sin profesor no hay texto sobrante');
assert(agenda.deadlines.some(g=>g.title==='Trabajo final'&&g.important),'fecha límite importante');
// Sin fecha, o fuera de los 60 días, no aparece.
state.grades.push({id:'x1',node:'research',title:'Sin fecha',weight:10,min:0,max:5,score:null,date:'',important:false},{id:'x2',node:'research',title:'Lejana',weight:10,min:0,max:5,score:null,date:offset(today,120),important:false},{id:'x3',node:'research',title:'Pasada',weight:10,min:0,max:5,score:4,date:offset(today,-3),important:false});
const again=weekAgenda(state,today).deadlines.map(g=>g.title);
assert(!again.includes('Sin fecha')&&!again.includes('Lejana')&&!again.includes('Pasada'));
// Orden por fecha.
const dates=weekAgenda(state,today).deadlines.map(g=>g.date);assert.deepEqual(dates,[...dates].sort());
// Escenario vacío.
const empty=weekAgenda(seed({empty:true}),today);assert.equal(empty.classes.length,0);assert.equal(empty.deadlines.length,0);
console.log('week.mjs OK');
