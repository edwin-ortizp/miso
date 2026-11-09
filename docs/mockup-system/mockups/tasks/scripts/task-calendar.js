import {Calendar, dayGridPlugin, esLocale, interactionPlugin, listPlugin, timeGridPlugin} from '@project/project-assets/vendor/fullcalendar.js';

const compact = () => window.matchMedia('(max-width: 767.98px)').matches;
const pad = value => String(value).padStart(2, '0');
const isoDay = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const PRIORITIES = {'urgent-important': 'Urgente e importante', 'not-urgent-important': 'Importante, no urgente', 'urgent-not-important': 'Urgente, no importante', 'not-urgent-not-important': 'Ni urgente ni importante'};
// Paleta de ejemplo del calendario; no modifica el catálogo real del usuario.
const CATEGORIES = [
  {name: 'Personal', className: 'personal'},
  {name: 'Trabajo', className: 'work'},
  {name: 'Trabajo / Proyectos', className: 'projects'},
  {name: 'Finanzas', className: 'finance'},
];
const categoryClass = category => `lt-cal-category-${CATEGORIES.find(item => item.name === category)?.className || 'none'}`;
const REMINDER_UNITS = {minutes: 'Minutos', hours: 'Horas', days: 'Días', weeks: 'Semanas', months: 'Meses', years: 'Años'};
const RELATION_MODULES = {relationship: 'Personas', goal: 'Metas', 'health-event': 'Salud'};
const RELATION_OPTIONS = {
  relationship: [{id: 'person-laura', label: 'Laura Gómez'}, {id: 'person-carlos', label: 'Carlos Muñoz'}],
  goal: [{id: 'goal-profile', label: 'Mejorar mi perfil profesional'}, {id: 'goal-health', label: 'Cuidar mi bienestar'}],
  'health-event': [{id: 'health-check', label: 'Control de rutina · 03/11/2026'}],
};
const STATUSES = [
  {id: 'todo', name: 'Borrador', icon: 'bi-circle'},
  {id: 'ready', name: 'Ready', icon: 'bi-check2-circle'},
  {id: 'wip', name: 'WIP', icon: 'bi-play-circle'},
  {id: 'done', name: 'Done', icon: 'bi-check-circle-fill'},
];
const statusOf = id => STATUSES.find(status => status.id === id);

// Fechas relativas al lunes de la semana actual, para que la vista nunca salga vacía.
function day(offset, time) {
  const date = new Date();
  date.setDate(date.getDate() - ((date.getDay() + 6) % 7) + offset);
  return time ? `${isoDay(date)}T${time}:00` : isoDay(date);
}

function taskClasses({completed, recurrent, priority, category}) {
  return ['lt-calendar-task', categoryClass(category), completed && 'is-completed', recurrent && 'is-recurrent', priority && `is-${priority}`].filter(Boolean);
}

function task(id, title, start, end, extra = {}) {
  const props = {type: 'tasks', completed: false, recurrent: false, priority: null, category: null, isPrivate: false, reminders: [], relations: [], recurrenceIntervalDays: 7, estimatedMinutes: 45, description: 'Revisar el material pendiente, realizar los ajustes necesarios y comprobar el resultado antes de dar la tarea por terminada.', status: extra.completed ? 'done' : 'todo', ...extra};
  return {id, title, start, end, allDay: !start.includes('T'), classNames: taskClasses(props), extendedProps: props};
}

function sampleEvents() {
  return [
    task('t1', 'Preparar propuesta', day(0, '09:00'), day(0, '10:30'), {priority: 'urgent-important', category: 'Trabajo', status: 'wip'}),
    task('t2', 'Revisar presupuesto', day(1, '11:00'), day(1, '12:00'), {priority: 'not-urgent-important', category: 'Finanzas', status: 'ready'}),
    task('t3', 'Enviar seguimiento', day(2, '15:00'), day(2, '15:30'), {completed: true, category: 'Trabajo'}),
    task('t4', 'Planificar semana', day(4, '08:00'), day(4, '09:00'), {recurrent: true, category: 'Personal'}),
    task('t5', 'Actualizar portafolio', day(3), day(5), {priority: 'urgent-not-important', category: 'Trabajo', isPrivate: true}),
    {id: 'plan:1', title: 'Paseo a Silvia', start: day(5), end: day(7), allDay: true, editable: false, classNames: ['lt-calendar-plan'], extendedProps: {type: 'plans'}},
    {id: 'birthday:1', title: 'Laura Gómez · 34 años', start: day(2), end: day(3), allDay: true, editable: false, classNames: ['lt-calendar-birthday'], extendedProps: {type: 'birthdays'}},
    {id: 'meal:1', title: 'Almuerzo: arroz con pollo', start: day(1, '12:30'), end: day(1, '13:15'), editable: false, classNames: ['lt-calendar-meal'], extendedProps: {type: 'meals'}},
  ];
}

function timeLabel(event) {
  const label = date => new Intl.DateTimeFormat('es', {weekday: 'short', day: 'numeric', month: 'short'}).format(date);
  const hour = date => `${pad(date.getHours())}:${pad(date.getMinutes())}`;
  const start = event.start;
  if (event.allDay) {
    const end = new Date(event.end || start);
    if (event.end) end.setDate(end.getDate() - 1);
    return isoDay(start) === isoDay(end) ? `${label(start)} · Todo el día` : `${label(start)} – ${label(end)}`;
  }
  return event.end ? `${label(start)} · ${hour(start)}–${hour(event.end)}` : `${label(start)} · ${hour(start)}`;
}

export function mountTaskCalendar({root, scope, toast}) {
  const surface = root.querySelector('[data-task-calendar]');
  if (!surface) return;
  const find = selector => root.querySelector(selector);
  const quick = find('#task-dialog');
  quick.dataset.calQuick = '';
  quick.classList.add('lt-task-dialog-layer');
  quick.querySelector('.lt-dialog').classList.add('lt-task-dialog');
  quick.querySelector('.lt-dialog__head [data-lt-toggle="task-dialog"]').dataset.calCloseQuick = '';
  let taskMode = 'view';
  let taskSnapshot = '';
  let nativeRuleDraft = '';
  let relationRowSerial = 0;
  const draftSelector = '[data-cal-f-title],[data-cal-f-description],[data-cal-f-date],[data-cal-f-time],[data-cal-f-end-date],[data-cal-f-end-time],[data-cal-q-category],[data-cal-f-status],[data-cal-f-priority],[data-cal-f-size],[data-cal-f-estimate],[data-cal-f-private],[data-cal-f-recurrent],[data-cal-f-recur-days],[data-task-reminder-amount],[data-task-reminder-unit],[data-task-relation-type],[data-task-relation-target]';
  const draftSnapshot = () => JSON.stringify({fields: [...quick.querySelectorAll(draftSelector)].map(field => field.type === 'checkbox' ? field.checked : field.value), nativeRule: nativeRuleDraft});
  function refreshDraft(message = null) {
    quick.querySelectorAll('[data-task-duration]').forEach(button => { const active = Number(find('[data-cal-f-estimate]').value) === Number(button.dataset.taskDuration); button.setAttribute('aria-pressed', String(active)); button.classList.toggle('is-selected', active); });
    const dirty = draftSnapshot() !== taskSnapshot;
    find('[data-cal-draft-feedback]').textContent = message || (dirty ? 'Cambios pendientes' : selected ? 'Sin cambios pendientes' : '');
    find('[data-lt-action="calendar-save"]').disabled = !!selected && !dirty;
  }
  function updateRecurrenceFields() {
    find('[data-task-recurrence-fields]').hidden = !find('[data-cal-f-recurrent]').checked;
    find('[data-task-native-rule]').hidden = !nativeRuleDraft;
    find('[data-task-native-rule-text]').textContent = nativeRuleDraft;
    find('[data-task-interval-field]').hidden = !!nativeRuleDraft;
    find('[data-cal-f-recur-days]').disabled = taskMode === 'view' || !!nativeRuleDraft;
  }
  function appendReminder(values = {amount: 15, unit: 'minutes'}) {
    const row = find('[data-task-reminder-template]').content.firstElementChild.cloneNode(true);
    row.querySelector('[data-task-reminder-amount]').value = values.amount;
    row.querySelector('[data-task-reminder-unit]').value = values.unit;
    find('[data-task-reminder-rows]').append(row);
  }
  function fillRelationOptions(row, type, targetLabel = '') {
    const input = row.querySelector('[data-task-relation-target]');
    const list = row.querySelector('[data-task-relation-options]');
    if (!list.id) list.id = `task-relation-options-${++relationRowSerial}`;
    input.setAttribute('list', list.id); input.value = targetLabel;
    input.disabled = taskMode === 'view' || !type;
    input.placeholder = type ? 'Sin relación' : 'Elige primero un módulo';
    list.replaceChildren(...(RELATION_OPTIONS[type] || []).map(item => { const option = document.createElement('option'); option.value = item.label; return option; }));
  }
  function appendRelation(values = {type: '', id: ''}) {
    const row = find('[data-task-relation-template]').content.firstElementChild.cloneNode(true);
    row.querySelector('[data-task-relation-type]').value = values.type;
    fillRelationOptions(row, values.type, RELATION_OPTIONS[values.type]?.find(item => item.id === values.id)?.label || '');
    find('[data-task-relation-rows]').append(row);
  }
  function extraDraftRows() {
    return {
      reminders: [...quick.querySelectorAll('[data-task-reminder-row]')].map(row => ({amount: Number(row.querySelector('[data-task-reminder-amount]').value), raw: row.querySelector('[data-task-reminder-amount]').value, unit: row.querySelector('[data-task-reminder-unit]').value})),
      relations: [...quick.querySelectorAll('[data-task-relation-row]')].map(row => { const type = row.querySelector('[data-task-relation-type]').value; const label = row.querySelector('[data-task-relation-target]').value; return {type, label, id: RELATION_OPTIONS[type]?.find(item => item.label === label)?.id || ''}; }),
    };
  }
  function renderExtraReadOnly() {
    const props = selected?.extendedProps || {};
    find('[data-task-privacy-summary]').textContent = props.isPrivate ? 'Tarea privada' : 'Tarea no privada';
    find('[data-task-schedule-summary]').textContent = selected?.start ? timeLabel(selected) : 'Sin fecha programada.';
    find('[data-task-recurrence-summary]').textContent = props.recurrent ? props.nativeRecurrenceRule || `Se repite cada ${props.recurrenceIntervalDays || 7} días.` : 'Sin recurrencia.';
    const summary = (selector, lines, empty) => { const target = find(selector); target.replaceChildren(...(lines.length ? lines : [empty]).map(text => { const p = document.createElement('p'); p.textContent = text; return p; })); };
    summary('[data-task-reminders-summary]', (props.reminders || []).map(item => `${item.amount} ${REMINDER_UNITS[item.unit]?.toLowerCase()} antes`), 'Sin recordatorios.');
    summary('[data-task-relations-summary]', (props.relations || []).map(item => `${RELATION_MODULES[item.type]} · ${RELATION_OPTIONS[item.type]?.find(option => option.id === item.id)?.label || 'Sin relación'}`), 'Sin relaciones.');
  }
  function sectionError(section, message, control) {
    if (taskMode === 'view') setTaskMode('edit');
    selectTaskSection(section); find('[data-cal-task-error]').textContent = message;
    if (control) control.focus();
  }
  let draftTaskId = null;
  let taskScrollTop = 0;
  let requiresDate = false;
  const notice = find('[data-cal-notice]');
  const layers = {tasks: true, plans: true, birthdays: true, meals: true};
  const filterPanel = find('#calendar-filters');
  const filterToggle = find('[data-cal-filter-open]');
  const fitFilterSheet = () => { if (!filterPanel.hidden) filterPanel.style.setProperty('--cal-filter-height', `${filterPanel.getBoundingClientRect().height}px`); };
  const layerLabels = {tasks: 'Tareas', plans: 'Planes', birthdays: 'Cumpleaños', meals: 'Comidas'};
  const syncLayerDraft = all => root.querySelectorAll('[data-cal-layer]').forEach(input => { input.checked = all || layers[input.dataset.calLayer]; });
  function closeFilters(restoreFocus = false) {
    filterToggle.setAttribute('aria-expanded', 'false');
    filterPanel.hidden = true;
    syncLayerDraft(false);
    if (restoreFocus) filterToggle.focus({preventScroll: true});
  }
  function applyLayers() {
    const active = Object.keys(layers).filter(key => layers[key]);
    const restricted = active.length !== Object.keys(layers).length;
    find('[data-cal-filter-count]').hidden = !restricted;
    find('[data-cal-applied]').hidden = !restricted;
    find('[data-cal-filter-label]').textContent = `Tipos: ${active.length ? active.map(key => layerLabels[key]).join(', ') : 'Ninguno'}`;
    find('[data-cal-filter-empty]').hidden = active.length !== 0;
    closeQuick();
    calendar.batchRendering(() => calendar.getEvents().forEach(item => item.setProp('display', layers[item.extendedProps.type] ? 'auto' : 'none')));
    fitCalendar();
  }

  const unscheduled = [
    {id: 'u1', title: 'Renovar pasaporte', code: 'TAR-00041', extendedProps: {category: 'Personal', priority: 'urgent-important', status: 'ready', estimatedMinutes: 60, description: 'Revisar los requisitos y solicitar la cita para renovar el pasaporte.'}},
    {id: 'u2', title: 'Ordenar fotos del viaje', code: 'TAR-00042', extendedProps: {category: 'Personal', priority: 'not-urgent-important', status: 'todo', estimatedMinutes: 0, description: 'Seleccionar las fotos que queremos conservar y organizar el álbum del viaje.'}},
  ];
  const focusRecords = [];
  const focusDialog = find('#calendar-focus');
  const recurringDialog = find('#calendar-recurring');
  let quickTrigger = null, modalTrigger = null, focusTask = null, focusMode = 'manual', timerStarted = null, timerDeadline = null;
  let timerHandle = null;
  const stopTimer = () => { if (timerHandle !== null) clearInterval(timerHandle); timerHandle = null; };
  const localDateTime = date => `${isoDay(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  function updateFocusSummary() {
    const taskId = selected?.id || draftTaskId;
    const records = focusRecords.filter(record => record.taskId === taskId);
    find('[data-cal-focus-summary]').textContent = records.length ? `${records.length} registro(s) · ${Math.round(records.reduce((sum, record) => sum + record.seconds, 0) / 60)} min de foco` : 'Sin registros de foco.';
  }
  function showModal(modal) {
    modalTrigger = document.activeElement;
    taskScrollTop = quick.querySelector('.lt-dialog__body').scrollTop;
    quick.hidden = true;
    modal.hidden = false;
    modal.querySelector('input,textarea,button:not(.lt-dialog-scrim)').focus();
  }
  function backToTask() {
    focusDialog.hidden = true;
    recurringDialog.hidden = true;
    stopTimer(); timerStarted = null;
    if (selected || taskMode === 'create') {
      updateFocusSummary();
      refreshDraft();
      quick.hidden = false;
      quick.querySelector('.lt-dialog__body').scrollTop = taskScrollTop;
      (modalTrigger?.isConnected ? modalTrigger : find('.lt-dialog__head [data-cal-close-quick]')).focus({preventScroll: true});
    }
  }
  function openFocus(mode) {
    closeMenus();
    {
      const title = find('[data-cal-f-title]').value.trim();
      if (!title) { selectTaskSection('basic'); find('[data-cal-task-error]').textContent = 'Escribe un título antes de iniciar o registrar foco.'; find('[data-cal-f-title]').focus(); return; }
      const date = find('[data-cal-f-date]').value, time = find('[data-cal-f-time]').value;
      const endDate = find('[data-cal-f-end-date]').value, endTime = find('[data-cal-f-end-time]').value;
      const start = date && time ? new Date(`${date}T${time}:00`) : null;
      const end = endDate && endTime ? new Date(`${endDate}T${endTime}:00`) : null;
      focusTask = {id: selected?.id || draftTaskId, title, allDay: !time, start, end: start && end > start ? end : null, extendedProps: {...selected?.extendedProps, estimatedMinutes: Number(find('[data-cal-f-estimate]').value) || null}};
    }
    focusMode = mode;
    stopTimer(); timerStarted = null;
    const hasInterval = !focusTask.allDay && focusTask.start && focusTask.end;
    const minutes = hasInterval ? Math.round((focusTask.end - focusTask.start) / 60000) : focusTask.extendedProps.estimatedMinutes;
    find('[data-cal-focus-task]').textContent = focusTask.title;
    find('[data-cal-focus-minutes]').value = minutes > 0 ? minutes : '';
    find('[data-cal-focus-minutes]').disabled = false;
    find('[data-cal-focus-end]').value = localDateTime(new Date());
    find('[data-cal-focus-end-field]').hidden = mode === 'timer';
    find('[data-cal-focus-hint]').textContent = minutes > 0 ? `Sugerencia: ${minutes} min, según ${hasInterval ? 'el horario de la tarea' : 'su tiempo estimado'}. Puedes ajustarla.` : 'La tarea no tiene duración. Indica cuántos minutos quieres dedicarle.';
    find('[data-cal-focus-notes]').value = '';
    find('[data-cal-focus-error]').textContent = '';
    find('[data-cal-timer]').hidden = true;
    find('[data-lt-action="calendar-focus-save"]').textContent = mode === 'timer' ? 'Iniciar temporizador' : 'Registrar foco';
    closeMenus();
    showModal(focusDialog);
    modalTrigger = find('[data-cal-menu-toggle="focus"]');
    find('[data-cal-focus-minutes]').focus();
  }
  function saveFocus() {
    const minutes = Number(find('[data-cal-focus-minutes]').value);
    const error = find('[data-cal-focus-error]');
    error.textContent = '';
    if (!Number.isInteger(minutes) || minutes < 1 || minutes > 960) { error.textContent = 'Indica una duración entre 1 y 960 minutos.'; return; }
    const record = (start, end) => {
      focusRecords.push({taskId: focusTask.id, title: focusTask.title, start, end, seconds: Math.round((end - start) / 1000), notes: find('[data-cal-focus-notes]').value.trim()});
      backToTask(); toast('Foco registrado en esta tarea.');
    };
    if (focusMode === 'manual') {
      const end = new Date(find('[data-cal-focus-end]').value);
      if (Number.isNaN(end.getTime()) || end > new Date()) { error.textContent = 'Elige una fecha y hora de fin válida que no esté en el futuro.'; return; }
      record(new Date(end.getTime() - minutes * 60000), end); return;
    }
    if (timerStarted) {
      if (Date.now() - timerStarted < 60000) { error.textContent = 'Trabaja al menos un minuto para registrar el foco.'; return; }
      record(new Date(timerStarted), new Date()); return;
    }
    timerStarted = Date.now(); timerDeadline = timerStarted + minutes * 60000;
    find('[data-cal-focus-minutes]').disabled = true;
    find('[data-cal-timer]').hidden = false;
    find('[data-lt-action="calendar-focus-save"]').textContent = 'Finalizar y registrar';
    const tick = () => {
      const seconds = Math.max(0, Math.ceil((timerDeadline - Date.now()) / 1000));
      find('[data-cal-timer-value]').textContent = `${pad(Math.floor(seconds / 60))}:${pad(seconds % 60)}`;
      if (!seconds) { stopTimer(); find('[data-cal-focus-error]').textContent = 'Tiempo cumplido. Finaliza para registrar el trabajo realizado.'; }
    };
    tick(); timerHandle = setInterval(tick, 1000);
  }
  let calendar, selected = null, editing = null, noticeDate = null, nextId = 1;

  const closeQuick = () => {
    if (taskMode === 'create' && draftTaskId) {
      const records = focusRecords.filter(record => record.taskId === draftTaskId);
      records.forEach(record => { record.taskId = null; });
      if (records.length) showNotice('Tarea descartada. El foco registrado se conserva sin asociar a una tarea.', null);
    }
    quick.hidden = true; selected = null; draftTaskId = null; taskMode = 'view'; closeMenus();
    if (quickTrigger?.isConnected) quickTrigger.focus({preventScroll: true});
  };

  function renderQuick() {
    const props = selected.extendedProps;
    const title = find('[data-cal-q-title]');
    title.textContent = selected.title;
    title.classList.toggle('is-completed', props.completed);
    find('[data-cal-q-time]').textContent = selected.start ? timeLabel(selected) : 'Sin fecha programada';
    find('[data-cal-q-description]').textContent = props.description || 'Sin descripción.';
    updateFocusSummary();
    const optional = {recurrent: props.recurrent ? 'Se repite' : null};
    for (const [key, value] of Object.entries(optional)) {
      const row = find(`[data-cal-q-row="${key}"]`);
      row.hidden = !value;
      row.querySelector('span').textContent = value || '';
    }
    renderExtraReadOnly();
    refreshDraft();
  }

  function openQuick(event, el) {
    openForm({}, event);
    quickTrigger = el;
    setTaskMode('view');
    renderQuick();
    find('.lt-dialog__head [data-cal-close-quick]').focus();
  }

  function showNotice(text, date) {
    noticeDate = date;
    find('[data-cal-notice-text]').textContent = text;
    find('[data-cal-notice-view]').hidden = !date;
    notice.hidden = false;
  }

  function renderUnscheduled() {
    const list = find('[data-cal-unscheduled-list]');
    const query = find('[data-cal-pending-search]').value.trim().toLocaleLowerCase('es');
    const pending = unscheduled.filter(item => !item.extendedProps?.completed);
    const visible = pending.filter(item => `${item.title} ${item.code} ${item.extendedProps.category || ''}`.toLocaleLowerCase('es').includes(query));
    find('[data-cal-pending-count]').textContent = `(${visible.length} / ${pending.length})`;
    const empty = find('[data-cal-pending-empty]');
    empty.hidden = visible.length > 0;
    empty.textContent = pending.length ? 'No hay tareas que coincidan con la búsqueda.' : 'No hay tareas sin fecha.';
    list.replaceChildren(...visible.map(item => {
      const row = document.createElement('tr');
      const props = item.extendedProps;
      const cell = (label, text) => { const td = document.createElement('td'); td.dataset.label = label; td.textContent = text; row.append(td); return td; };
      const title = cell('Tarea', '');
      const button = document.createElement('button'); button.type = 'button'; button.className = 'lt-cal-task-link'; button.dataset.calPendingOpen = item.id; button.textContent = item.title;
      const code = document.createElement('small'); code.textContent = item.code;
      title.append(button, code);
      cell('Categoría', props.category || 'Sin categoría');
      cell('Prioridad', PRIORITIES[props.priority] || 'Sin prioridad');
      cell('Estado', statusOf(props.status || 'todo').name);
      const actions = cell('Acciones', ''); actions.className = 'lt-cal-row-actions';
      for (const [action, label, icon] of [['schedule', 'Programar', 'bi-calendar-plus'], ['complete', 'Completar', 'bi-check2-circle'], ['edit', 'Editar', 'bi-pencil']]) {
        const control = document.createElement('button'); control.type = 'button'; control.className = action === 'schedule' ? 'lt-button lt-button--outlined' : 'lt-icon-button';
        control.dataset.calPendingAction = action; control.dataset.taskId = item.id; control.setAttribute('aria-label', `${label}: ${item.title}`);
        const i = document.createElement('i'); i.className = `bi ${icon}`; i.setAttribute('aria-hidden', 'true'); control.append(i);
        if (action === 'schedule') control.append(' Programar'); actions.append(control);
      }
      return row;
    }));
  }

  function selectTaskSection(section) {
    quick.querySelectorAll('[data-lt-dialog-section]').forEach(button => {
      if (button.dataset.ltDialogSection === section) button.setAttribute('aria-current', 'true'); else button.removeAttribute('aria-current');
    });
    quick.querySelectorAll('.lt-dialog__section').forEach(panel => { panel.hidden = panel.dataset.section !== section; });
    quick.querySelector('.lt-dialog').classList.remove('is-nav-open');
  }
  function setTaskMode(mode) {
    taskMode = mode;
    const editable = mode !== 'view';
    quick.dataset.mode = mode;
    quick.querySelectorAll('[data-task-view]').forEach(node => { node.hidden = editable; });
    quick.querySelectorAll('[data-task-edit]').forEach(node => { node.hidden = !editable; });
    quick.querySelectorAll('[data-task-edit] input,[data-task-edit] textarea,[data-task-edit] select,[data-section="schedule"] input').forEach(control => { control.disabled = !editable; });
    const heading = find('#task-dialog-title');
    heading.textContent = mode === 'create' ? 'Nueva tarea' : mode === 'edit' ? 'Editar tarea' : 'Tarea';
    heading.title = heading.textContent;
    find('[data-cal-task-error]').textContent = '';
    closeMenus();
    updateRecurrenceFields();
    quick.querySelectorAll('[data-task-relation-row]').forEach(row => { row.querySelector('[data-task-relation-target]').disabled = !editable || !row.querySelector('[data-task-relation-type]').value; });
    if (!editable) selectTaskSection('basic');
  }
  function openForm(values = {}, target = null) {
    editing = target;
    selected = target;
    draftTaskId = target ? null : `n${nextId++}`;
    if (quick.hidden) quickTrigger = document.activeElement;
    requiresDate = !!values.scheduling;
    const props = target?.extendedProps || {};
    const start = target?.start;
    let end = target?.end ? new Date(target.end) : null;
    if (end && target.allDay) end.setDate(end.getDate() - 1);
    find('[data-cal-f-title]').value = values.title ?? target?.title ?? '';
    find('[data-cal-f-description]').value = props.description || '';
    find('[data-cal-f-date]').value = values.date ?? (start ? isoDay(start) : '');
    find('[data-cal-f-time]').value = values.time ?? (start && !target.allDay ? `${pad(start.getHours())}:${pad(start.getMinutes())}` : '');
    find('[data-cal-f-end-date]').value = end ? isoDay(end) : '';
    find('[data-cal-f-end-time]').value = end && !target.allDay ? `${pad(end.getHours())}:${pad(end.getMinutes())}` : '';
    find('[data-cal-q-category]').value = props.category || '';
    find('[data-cal-f-status]').value = props.status || 'todo';
    find('[data-cal-f-priority]').value = props.priority || '';
    find('[data-cal-f-size]').value = props.size || '';
    find('[data-cal-f-estimate]').value = props.estimatedMinutes || '';
    find('[data-cal-f-private]').checked = !!props.isPrivate;
    find('[data-cal-f-recurrent]').checked = !!props.recurrent;
    find('[data-cal-f-recur-days]').value = props.recurrenceIntervalDays || 7;
    nativeRuleDraft = props.nativeRecurrenceRule || '';
    find('[data-task-reminder-rows]').replaceChildren(); (props.reminders || []).forEach(appendReminder);
    find('[data-task-relation-rows]').replaceChildren(); (props.relations?.length ? props.relations : [{type: '', id: ''}]).forEach(appendRelation);
    setTaskMode(target ? 'edit' : 'create');
    find('[data-cal-f-date]').required = requiresDate;
    selectTaskSection(requiresDate ? 'schedule' : 'basic');
    quick.hidden = false;
    updateFocusSummary();
    taskSnapshot = draftSnapshot(); refreshDraft();
    find(requiresDate ? '[data-cal-f-date]' : '[data-cal-f-title]').focus();
  }
  function cancelEditing() {
    if (selected && taskMode !== 'view') {
      openForm({}, selected); setTaskMode('view'); renderQuick();
      refreshDraft('Cambios descartados'); find('[data-cal-edit]').focus();
    } else closeQuick();
  }
  function saveForm(confirmed = false) {
    const error = find('[data-cal-task-error]');
    error.textContent = '';
    const titleField = find('[data-cal-f-title]');
    const title = titleField.value.trim();
    if (!title || title.length > 255) { selectTaskSection('basic'); error.textContent = 'Indica un título de hasta 255 caracteres.'; titleField.focus(); return; }
    const date = find('[data-cal-f-date]').value;
    const time = find('[data-cal-f-time]').value;
    const endDate = find('[data-cal-f-end-date]').value;
    const endTime = find('[data-cal-f-end-time]').value;
    const estimate = Number(find('[data-cal-f-estimate]').value);
    if (find('[data-cal-f-estimate]').value && (!Number.isInteger(estimate) || estimate < 1)) { error.textContent = 'El tiempo estimado debe ser un número entero positivo.'; find('[data-cal-f-estimate]').focus(); return; }
    const scheduleError = message => sectionError('schedule', message, find('[data-cal-f-date]'));
    const recurrent = find('[data-cal-f-recurrent]').checked;
    const recurrenceDays = Number(find('[data-cal-f-recur-days]').value);
    if (recurrent && !nativeRuleDraft && (!Number.isInteger(recurrenceDays) || recurrenceDays < 1)) { sectionError('schedule', 'Indica un intervalo de al menos un día.', find('[data-cal-f-recur-days]')); return; }
    if (requiresDate && !date) { scheduleError('Indica una fecha para programar la tarea.'); return; }
    if (recurrent && !date) { scheduleError('Una tarea recurrente necesita una fecha de inicio.'); return; }
    if (!date && (time || endDate || endTime)) { scheduleError('Indica la fecha de inicio o borra los campos de programación para dejarla sin fecha.'); return; }
    if (endTime && (!endDate || !time)) { scheduleError('Para indicar una hora de fin, completa ambas fechas y la hora de inicio.'); return; }
    let start = date ? (time ? `${date}T${time}:00` : date) : null;
    let end;
    if (endDate) {
      if (time && !endTime) { scheduleError('Indica la hora de fin.'); return; }
      end = time ? `${endDate}T${endTime}:00` : `${endDate}T00:00:00`;
      if (!time) { const exclusive = new Date(end); exclusive.setDate(exclusive.getDate() + 1); end = isoDay(exclusive); }
      if (new Date(end) <= new Date(time ? start : `${date}T00:00:00`)) { scheduleError('El fin debe ser posterior al inicio.'); return; }
    }
    const extra = extraDraftRows();
    const reminderRows = [...quick.querySelectorAll('[data-task-reminder-row]')];
    for (const [index, item] of extra.reminders.entries()) {
      if (item.raw === '' || !Number.isInteger(item.amount) || item.amount < 0 || item.amount > 4294967295 || !REMINDER_UNITS[item.unit]) { sectionError('reminders', 'Indica una anticipación entera desde cero y una unidad válida.', reminderRows[index].querySelector('input')); return; }
    }
    if (extra.reminders.length && !date) { sectionError('reminders', 'Define primero la fecha del elemento para configurar recordatorios.', reminderRows[0].querySelector('input')); return; }
    if (new Set(extra.reminders.map(item => `${item.amount}:${item.unit}`)).size !== extra.reminders.length) { sectionError('reminders', 'No repitas la misma anticipación.', reminderRows.at(-1).querySelector('input')); return; }
    const invalidRelation = extra.relations.findIndex(item => item.label && !item.id);
    if (invalidRelation >= 0) { sectionError('relations', 'Elige una relación de las opciones del módulo.', quick.querySelectorAll('[data-task-relation-row]')[invalidRelation].querySelector('[data-task-relation-target]')); return; }
    const relations = extra.relations.filter(item => item.type && item.id).filter((item, index, rows) => rows.findIndex(other => other.type === item.type && other.id === item.id) === index).map(({type, id}) => ({type, id}));
    const previous = editing;
    const oldProps = previous?.extendedProps || {};
    const nextStatus = find('[data-cal-f-status]').value;
    const confirmRecurring = recurrent && nextStatus === 'done' && !oldProps.completed;
    if (confirmRecurring && nativeRuleDraft) { sectionError('schedule', 'La regla avanzada se conserva. Usa Cambiar para probar una frecuencia simple en este mockup.', find('[data-task-rule-change]')); return; }
    if (confirmRecurring && !confirmed) {
      const next = new Date(time ? start : `${date}T00:00:00`); next.setDate(next.getDate() + recurrenceDays);
      find('[data-cal-recurring-text]').textContent = `Completar «${title}». Próxima repetición: ${new Intl.DateTimeFormat('es', {dateStyle: 'medium'}).format(next)}.`;
      showModal(recurringDialog); return;
    }
    if (confirmRecurring) {
      const advance = value => { const next = new Date(value.includes('T') ? value : `${value}T00:00:00`); next.setDate(next.getDate() + recurrenceDays); return value.includes('T') ? `${isoDay(next)}T${pad(next.getHours())}:${pad(next.getMinutes())}:00` : isoDay(next); };
      start = advance(start); if (end) end = advance(end);
    }

    const props = {...oldProps, description: find('[data-cal-f-description]').value, category: find('[data-cal-q-category]').value || null, status: confirmRecurring ? (oldProps.status || 'todo') : nextStatus, completed: confirmRecurring ? !!oldProps.completed : nextStatus === 'done', priority: find('[data-cal-f-priority]').value || null, size: find('[data-cal-f-size]').value || null, estimatedMinutes: estimate || null, isPrivate: find('[data-cal-f-private]').checked, reminders: extra.reminders.map(({amount, unit}) => ({amount, unit})), relations, recurrent, recurrenceIntervalDays: recurrent ? recurrenceDays : null, nativeRecurrenceRule: recurrent ? nativeRuleDraft : ''};
    const id = previous?.id || draftTaskId || `n${nextId++}`;
    const code = previous?.code || oldProps.code || `TAR-${String(42 + nextId).padStart(5, '0')}`;
    if (previous?.remove) previous.remove();
    else if (previous) { const index = unscheduled.indexOf(previous); if (index >= 0) unscheduled.splice(index, 1); }
    if (start) {
      selected = calendar.addEvent({...task(id, title, start, end, {...props, code}), display: layers.tasks ? 'auto' : 'none'});
    } else {
      selected = {id, title, code, extendedProps: props}; unscheduled.unshift(selected);
    }
    editing = null; requiresDate = false; draftTaskId = null;
    quick.hidden = false;
    openForm({}, selected); renderUnscheduled(); setTaskMode('view'); renderQuick();
    find('[data-cal-edit]').focus();
    toast(previous ? 'Tarea actualizada.' : 'Tarea creada.');
    if (confirmRecurring) showNotice('Repetición completada. La tarea está en su próxima fecha.', selected.start);
  }

  function goToNow() {
    const now = new Date();
    if (calendar.view.type.startsWith('timeGrid')) calendar.gotoDate(now);
    else calendar.changeView('timeGridDay', now);
    requestAnimationFrame(() => calendar.scrollToTime({minutes: Math.max(0, now.getHours() * 60 + now.getMinutes() - 30)}));
  }

  // La rejilla horaria necesita altura fija para desplazarse por dentro; mes y lista crecen con su contenido.
  function fitCalendar() {
    if (!calendar) return;
    const reserve = compact() ? 104 : 24;
    const toolbar = surface.querySelector('.fc-header-toolbar')?.getBoundingClientRect().height || 0;
    const height = calendar.view.type.startsWith('timeGrid') ? Math.round(Math.max(toolbar + 140, innerHeight - surface.getBoundingClientRect().top - reserve)) : 'auto';
    if (calendar.getOption('height') !== height) calendar.setOption('height', height);
  }

  const categorySelect = find('[data-cal-q-category]');
  for (const category of CATEGORIES) {
    const option = document.createElement('option'); option.value = category.name; option.textContent = category.name; categorySelect.append(option);
  }
  calendar = new Calendar(surface, {
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    locale: esLocale,
    firstDay: 1,
    height: 'auto',
    initialView: compact() ? 'timeGridDay' : 'timeGridWeek',
    headerToolbar: false,
    footerToolbar: false,
    nowIndicator: true,
    slotDuration: '00:15:00',
    slotLabelInterval: '01:00',
    scrollTime: `${pad(Math.max(new Date().getHours() - 1, 0))}:00:00`,
    scrollTimeReset: false,
    slotEventOverlap: false,
    eventMinHeight: 26,
    editable: true,
    dayMaxEvents: 3,
    events: sampleEvents(),
    datesSet: ({view}) => {
      find('[data-cal-period]').textContent = view.title;
      find('[data-cal-view-select]').value = view.type;
      root.querySelectorAll('[data-cal-view]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.calView === view.type)));
      fitCalendar();
    },
    eventDidMount: ({event, el}) => {
      if (event.extendedProps.type !== 'tasks' || event.extendedProps.priority !== 'urgent-important') return;
      const title = el.querySelector('.fc-event-title,.fc-list-event-title');
      if (!title) return;
      const flag = document.createElement('i'); flag.className = 'bi bi-flag-fill lt-cal-priority-marker';
      flag.setAttribute('role', 'img'); flag.setAttribute('aria-label', 'Urgente e importante'); flag.title = 'Urgente e importante'; title.prepend(flag);
    },
    eventClick: info => {
      info.jsEvent.stopPropagation();
      if (info.event.extendedProps.type === 'tasks') { openQuick(info.event, info.el); return; }
      closeQuick();
      toast('En la app abre el detalle; esta pantalla no existe en el prototipo.');
    },
    dateClick: info => { info.jsEvent.stopPropagation(); openForm(info.allDay ? {date: info.dateStr} : {date: info.dateStr.slice(0, 10), time: info.dateStr.slice(11, 16)}); },
  });
  calendar.render();
  renderUnscheduled();
  fitCalendar();

  const taskDialogClick = event => {
    const toggle = event.target.closest('[data-lt-toggle="task-dialog"]');
    if (!toggle) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (toggle.closest('.lt-fab') || quick.hidden) { quickTrigger = toggle; openForm(); }
    else if (taskMode !== 'view') cancelEditing();
    else closeQuick();
  };
  root.addEventListener('click', taskDialogClick, true);
  scope.cleanup(() => root.removeEventListener('click', taskDialogClick, true));
  scope.on(window, 'resize', fitCalendar);
  scope.on(window, 'resize', fitFilterSheet);
  scope.on(find('[data-cal-pending-search]'), 'input', renderUnscheduled);
  scope.on(root, 'keydown', event => {
    if (!filterPanel.hidden) {
      if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); closeFilters(true); return; }
      if (event.key === 'Tab') {
        const controls = [...filterPanel.querySelectorAll('button,input')].filter(el => el.getClientRects().length && !el.disabled);
        const first = controls[0], last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
      return;
    }
    const modal = !focusDialog.hidden ? focusDialog : !recurringDialog.hidden ? recurringDialog : !quick.hidden ? quick : null;
    if (!modal) return;
    if (event.key === 'Escape' && !quick.hidden && taskMode !== 'view' && !find('[data-cal-menu]:not([hidden])')) { event.preventDefault(); event.stopImmediatePropagation(); cancelEditing(); return; }
    if (event.key === 'Tab') {
      const controls = [...modal.querySelectorAll('button,input,select,textarea,[tabindex]')].filter(el => !el.disabled && el.tabIndex >= 0 && el.getClientRects().length);
      if (!controls.length) return;
      const first = controls[0], last = controls.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    if (event.key === 'Escape' && (!focusDialog.hidden || !recurringDialog.hidden)) { event.preventDefault(); event.stopImmediatePropagation(); backToTask(); }
  });
  function closeMenus(restoreFocus = false) {
    root.querySelectorAll('[data-cal-menu]').forEach(menu => {
      if (menu.hidden) return;
      menu.hidden = true;
      const toggle = find(`[data-cal-menu-toggle="${menu.dataset.calMenu}"]`);
      toggle.setAttribute('aria-expanded', 'false');
      if (restoreFocus) toggle.focus();
    });
  }
  scope.on(document, 'click', event => {
    if (!event.target.closest('.lt-cal-dropdown')) closeMenus();
    if (!filterPanel.hidden && !event.target.closest('.lt-cal-filter-anchor')) closeFilters(false);
  });
  scope.on(root, 'focusout', event => {
    const open = find('[data-cal-menu]:not([hidden])');
    if (open && !open.parentElement.contains(event.relatedTarget)) closeMenus();
  });
  scope.on(root, 'keydown', event => {
    if (event.key === 'Escape') {
      if (find('[data-cal-menu]:not([hidden])')) { event.preventDefault(); event.stopPropagation(); closeMenus(true); }
      else if (!quick.hidden) closeQuick();
    }
    const toggle = event.target.closest('[data-cal-menu-toggle]');
    if (toggle && event.key === 'ArrowDown') { event.preventDefault(); if (toggle.getAttribute('aria-expanded') !== 'true') toggle.click(); else find(`[data-cal-menu="${toggle.dataset.calMenuToggle}"]`).querySelector('input,button').focus(); }
  });
  scope.on(quick, 'input', event => { if (event.target.matches(draftSelector)) { find('[data-cal-task-error]').textContent = ''; refreshDraft(); } });
  scope.on(root, 'change', event => {
    if (event.target.matches('[data-task-relation-type]')) fillRelationOptions(event.target.closest('[data-task-relation-row]'), event.target.value);
    if (event.target.matches('[data-cal-f-recurrent]')) updateRecurrenceFields();
    if (event.target.matches(draftSelector)) { find('[data-cal-task-error]').textContent = ''; refreshDraft(); }
    if (event.target.matches('[data-cal-view-select]')) calendar.changeView(event.target.value);
  });
  scope.on(root, 'click', event => {
    const target = event.target;
    if (target.closest('[data-lt-dialog-section],[data-task-reminder-add],[data-task-reminder-remove],[data-task-relation-add],[data-task-relation-remove]')) find('[data-cal-task-error]').textContent = '';
    if (target.closest('[data-task-reminder-add]')) { appendReminder(); refreshDraft(); return; }
    if (target.closest('[data-task-reminder-remove]')) { target.closest('[data-task-reminder-row]').remove(); refreshDraft(); return; }
    if (target.closest('[data-task-relation-add]')) { appendRelation(); refreshDraft(); return; }
    if (target.closest('[data-task-relation-remove]')) { target.closest('[data-task-relation-row]').remove(); if (!find('[data-task-relation-row]')) appendRelation(); refreshDraft(); return; }
    if (target.closest('[data-task-rule-change]')) { nativeRuleDraft = ''; updateRecurrenceFields(); refreshDraft(); return; }
    const duration = target.closest('[data-task-duration]');
    if (duration) {
      const minutes = Number(duration.dataset.taskDuration);
      const date = find('[data-cal-f-date]').value, time = find('[data-cal-f-time]').value;
      const start = date ? new Date(`${date}T${time || '00:00'}:00`) : new Date(); start.setSeconds(0, 0);
      const end = new Date(start.getTime() + minutes * 60000);
      find('[data-cal-f-date]').value = isoDay(start); find('[data-cal-f-time]').value = `${pad(start.getHours())}:${pad(start.getMinutes())}`;
      find('[data-cal-f-end-date]').value = isoDay(end); find('[data-cal-f-end-time]').value = `${pad(end.getHours())}:${pad(end.getMinutes())}`;
      find('[data-cal-f-estimate]').value = minutes;
      quick.querySelectorAll('[data-task-duration]').forEach(button => { const active = button === duration; button.setAttribute('aria-pressed', String(active)); button.classList.toggle('is-selected', active); });
      refreshDraft(); return;
    }

    if (target.closest('[data-cal-filter-open]')) {
      if (!filterPanel.hidden) closeFilters(true);
      else {
        closeMenus(); syncLayerDraft(false); filterPanel.hidden = false; fitFilterSheet();
        filterToggle.setAttribute('aria-expanded', 'true');
        filterPanel.querySelector('input').focus();
      }
      return;
    }
    if (target.closest('[data-cal-filter-close]')) { closeFilters(true); return; }
    if (target.closest('[data-cal-filter-reset]')) { syncLayerDraft(true); return; }
    if (target.closest('[data-cal-filter-apply]')) {
      root.querySelectorAll('[data-cal-layer]').forEach(input => { layers[input.dataset.calLayer] = input.checked; });
      applyLayers(); closeFilters(true); return;
    }
    if (target.closest('[data-cal-filter-remove]')) {
      Object.keys(layers).forEach(key => { layers[key] = true; }); applyLayers(); syncLayerDraft(false); filterToggle.focus(); return;
    }
    const nav = target.closest('[data-cal-nav]')?.dataset.calNav;
    if (nav === 'prev') calendar.prev();
    if (nav === 'next') calendar.next();
    if (nav === 'now') goToNow();
    const view = target.closest('[data-cal-view]')?.dataset.calView;
    if (view) calendar.changeView(view);
    if (nav || view) { closeMenus(true); return; }
    const menuToggle = target.closest('[data-cal-menu-toggle]');
    if (menuToggle) {
      const menu = find(`[data-cal-menu="${menuToggle.dataset.calMenuToggle}"]`);
      const opening = menu.hidden;
      closeMenus();
      menu.hidden = !opening;
      menuToggle.setAttribute('aria-expanded', String(opening));
      if (opening) menu.querySelector('input,button').focus();
      return;
    }
    const focusAction = target.closest('[data-cal-focus]');
    if (focusAction) { openFocus(focusAction.dataset.calFocus); return; }
    if (target.closest('[data-lt-action="calendar-focus-save"]')) { saveFocus(); return; }
    if (target.closest('[data-lt-action="calendar-recurring-complete"]')) { recurringDialog.hidden = true; quick.hidden = false; saveForm(true); find('.lt-dialog__head [data-cal-close-quick]').focus(); return; }
    if (target.closest('[data-lt-toggle="calendar-focus"],[data-lt-toggle="calendar-recurring"]')) { backToTask(); return; }
    if (target.closest('[data-cal-edit]')) {
      const section = quick.querySelector('[data-lt-dialog-section][aria-current="true"]')?.dataset.ltDialogSection || 'basic';
      setTaskMode('edit'); selectTaskSection(section);
      const control = quick.querySelector(`.lt-dialog__section[data-section="${section}"] input:not(:disabled),.lt-dialog__section[data-section="${section}"] select:not(:disabled),.lt-dialog__section[data-section="${section}"] button`);
      control?.focus(); return;
    }
    if (target.closest('[data-cal-edit-cancel]')) { cancelEditing(); return; }
    if (target.closest('[data-cal-close-quick]')) { closeQuick(); return; }
    const pending = target.closest('[data-cal-pending-open],[data-cal-pending-action]');
    if (pending) {
      const item = unscheduled.find(entry => entry.id === (pending.dataset.calPendingOpen || pending.dataset.taskId));
      if (pending.dataset.calPendingOpen) openQuick(item, pending);
      else if (pending.dataset.calPendingAction === 'complete') { item.extendedProps.completed = true; item.extendedProps.status = 'done'; renderUnscheduled(); toast('Tarea completada.'); find('[data-cal-pending-search]').focus(); }
      else openForm({title: item.title, scheduling: pending.dataset.calPendingAction === 'schedule'}, item);
      return;
    }
    if (target.closest('[data-lt-action="calendar-save"]')) { saveForm(); return; }
    if (target.closest('[data-cal-notice-view]')) { calendar.gotoDate(noticeDate); notice.hidden = true; return; }
    if (target.closest('[data-cal-notice-close]')) { notice.hidden = true; return; }


  });
  // La hoja móvil compartida también puede cerrarse al arrastrar o tocar su scrim.
  const filterObserver = new MutationObserver(() => {
    if (filterPanel.hidden && filterToggle.getAttribute('aria-expanded') === 'true') closeFilters(true);
  });
  filterObserver.observe(filterPanel, {attributes: true, attributeFilter: ['hidden']});
  scope.cleanup(() => { filterObserver.disconnect(); stopTimer(); calendar.destroy(); });
}
