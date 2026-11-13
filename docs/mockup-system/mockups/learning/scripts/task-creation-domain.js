export const emptyTaskDraft = bulk => bulk ? {titles:'',category:''} : {title:'',category:''};
export const creationDefaults = () => ({mode:'pokemon',single:emptyTaskDraft(false),bulk:emptyTaskDraft(true)});
export const taskDefaults = () => ({title:'',description:'',date:'',endDate:'',time:'',endTime:'',status:'todo',estimate:25,category:'Personal',priority:'',size:'',private:false,recurrent:false,recurDays:7,reminders:[],relations:[]});
export const taskTitles = (text,bulk=false) => (bulk ? text.split(/\r?\n/) : [text]).map(t=>t.trim()).filter(Boolean);
export function validateTitles(titles){
 if(!titles.length)return 'Escribe al menos un título.';
 const index=titles.findIndex(t=>[...t].length>255);
 return index<0?'':`El título ${index+1} supera los 255 caracteres. Conservamos el texto completo para que puedas ajustarlo.`;
}
export function appendTasks(state,titles,values={},owner=''){
 const error=validateTitles(titles);if(error)throw new Error(error);
 const records=titles.map(title=>({...taskDefaults(),...values,title,id:'t'+state.next++}));
 state.tasks.push(...records);
 if(owner)records.forEach(t=>state.links.push({id:'a'+state.next++,node:owner,kind:'task',target:t.id}));
 return records;
}
