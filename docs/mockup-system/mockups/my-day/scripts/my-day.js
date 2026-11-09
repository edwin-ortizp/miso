const pad = value => String(value).padStart(2, '0');
const dayKey = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const shiftDay = (key, offset) => { const date = new Date(`${key}T12:00:00`); date.setDate(date.getDate() + offset); return dayKey(date); };
const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
const TYPES = {task: ['Tarea', 'bi-list-task'], meal: ['Comida', 'bi-egg-fried'], plan: ['Plan', 'bi-map'], birthday: ['Cumpleaños', 'bi-cake2']};

export function mountMyDay({root, scope, toast, store}) {
  const surface = root.querySelector('[data-my-day]');
  if (!surface) return;
  const find = selector => surface.querySelector(selector);
  const today = dayKey(new Date());
  document.querySelectorAll('#andamio-escenario option').forEach(option => {
    option.textContent = option.value === 'alternate' ? 'Sin registros' : 'Con agenda';
  });
  let selectedDate = today, editing = null, current = null, destination = null, serial = 0;
  let activeDialog = null, returnFocus = null;
  const days = new Map();
  const entries = [];
  const blank = () => ({water: 0, mood: '', energy: null, journal: '', habits: []});
  const daily = () => { if (!days.has(selectedDate)) days.set(selectedDate, blank()); return days.get(selectedDate); };
  function endOf(entry) {
    if (!entry.time || !entry.duration) return '';
    const end = new Date(`${entry.date}T${entry.time}:00`);
    end.setMinutes(end.getMinutes() + entry.duration);
    return `${dayKey(end)} ${pad(end.getHours())}:${pad(end.getMinutes())}`;
  }
  function seed() {
    // El escenario alternativo permite revisar el estado vacío. Ambos se reinician al cambiar dataset.
    if (store.read().id === 'alternate') return;
    const add = (id, title, type, date, time = '', duration = null, description = '') => entries.push({id, title, type, date, time, duration, description, completed: false});
    add('task-1', 'Preparar propuesta de trabajo', 'task', today, '09:00', 90, 'Revisar alcance y preparar el borrador para la reunión.');
    add('task-2', 'Revisar presupuesto del mes', 'task', today, '16:00', 45, 'Comparar gastos previstos y compras pendientes.');
    add('task-3', 'Pedir cita de control', 'task', today, '', null, 'Consultar disponibilidad de la próxima semana.');
    add('meal-1', 'Almuerzo: arroz con pollo', 'meal', today, '12:30', 45, 'Preparación lista para compartir en casa.');
    add('plan-1', 'Café con Laura', 'plan', today, '', null, 'Conversar y organizar el paseo del fin de semana.');
    add('birthday-1', 'Cumpleaños de Andrés', 'birthday', today, '', null, 'Un momento para saludar a Andrés.');
    add('task-4', 'Ordenar las fotos del paseo', 'task', shiftDay(today, 1), '18:00', 30);
    add('task-5', 'Revisar pendientes de la semana', 'task', shiftDay(today, -1), '08:30', 20);
    entries[entries.length - 1].completed = true;
    for (const offset of [-1, 0, 1]) days.set(shiftDay(today, offset), {...blank(), habits: [
      {id: 'meditate', title: 'Meditar 10 minutos', done: offset <= 0},
      {id: 'walk', title: 'Caminar 30 minutos', done: false},
      {id: 'read', title: 'Leer antes de dormir', done: false},
    ]});
    Object.assign(days.get(today), {water: 750, mood: 'En calma', energy: 3, journal: 'Hoy quiero avanzar con calma en la propuesta y reservar un momento para caminar.'});
  }
  seed();
  function render() {
    const state = daily();
    find('[data-day-date]').value = selectedDate;
    find('[data-day-label]').textContent = new Intl.DateTimeFormat('es', {weekday: 'long', day: 'numeric', month: 'long'}).format(new Date(`${selectedDate}T12:00:00`));
    const agenda = entries.filter(entry => entry.date === selectedDate);
    const tasks = agenda.filter(entry => entry.type === 'task');
    find('[data-day-count]').textContent = `${agenda.length} elementos · ${tasks.filter(entry => entry.completed).length} de ${tasks.length} tareas completadas`;
    function rows(list) {
      if (!list.length) return '<p class="lt-day__empty">No hay elementos en este bloque.</p>';
      return list.map(entry => {
        const [label, icon] = TYPES[entry.type];
        return `<button type="button" class="lt-day__entry${entry.completed ? ' is-done' : ''}" data-day-entry="${escape(entry.id)}"><span class="lt-day__time">${entry.time || 'Sin hora'}</span><i class="bi ${icon}" aria-hidden="true"></i><span class="lt-day__entry-body"><strong>${escape(entry.title)}</strong><small>${label}${entry.duration ? ` · ${entry.duration} min` : ''}${entry.completed ? ' · Completada' : ''}</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></button>`;
      }).join('');
    }
    find('[data-day-timed]').innerHTML = rows(agenda.filter(entry => entry.time).sort((a, b) => a.time.localeCompare(b.time)));
    find('[data-day-untimed]').innerHTML = rows(agenda.filter(entry => !entry.time));
    find('[data-day-habit-count]').textContent = `${state.habits.filter(habit => habit.done).length} de ${state.habits.length} completados`;
    find('[data-day-habits]').innerHTML = state.habits.length ? state.habits.map(habit => `<label class="lt-day__habit"><input type="checkbox" data-day-habit="${habit.id}" ${habit.done ? 'checked' : ''}><span>${escape(habit.title)}</span></label>`).join('') : '<p class="lt-day__empty">No hay hábitos programados para este día.</p>';
    find('[data-day-water]').textContent = `${state.water} ml`;
    find('[data-day-mood]').textContent = state.mood || 'Sin registro';
    find('[data-day-energy]').textContent = state.energy ? `${state.energy} / 5` : 'Sin registro';
    find('[data-day-journal]').textContent = state.journal || 'Todavía no has escrito para este día. Puedes hacerlo desde el botón +.';
    find('[data-day-edit-journal]').hidden = !state.journal;
  }
  function setDate(date) { selectedDate = date; find('[data-day-notice]').hidden = true; render(); }
  const background = () => [...surface.children].filter(node => !node.classList.contains('lt-dialog-layer'));
  function closeDialog(restore = true) {
    if (!activeDialog) return;
    activeDialog.hidden = true;
    activeDialog = null;
    background().forEach(node => { node.inert = false; });
    root.querySelectorAll('.lt-sidebar,.lt-topbar,.lt-bottom').forEach(node => { node.inert = false; });
    if (restore) {
      const target = returnFocus?.isConnected && returnFocus.getClientRects().length ? returnFocus : find('.lt-fab[aria-haspopup="menu"]');
      (target || find('[data-day-date]')).focus({preventScroll: true});
    }
  }
  function openDialog(id, trigger = document.activeElement) {
    closeDialog(false);
    returnFocus = trigger;
    activeDialog = find(`#${id}`);
    activeDialog.hidden = false;
    background().forEach(node => { node.inert = true; });
    root.querySelectorAll('.lt-sidebar,.lt-topbar,.lt-bottom').forEach(node => { node.inert = true; });
    const preferred = [...activeDialog.querySelectorAll('input:not([readonly]),select,textarea,[data-day-complete]')].find(node => node.getClientRects().length);
    (preferred || activeDialog.querySelector('button:not([tabindex="-1"])'))?.focus();
  }
  function taskForm(entry = null) {
    editing = entry;
    const form = find('#day-task-form');
    form.elements.title.value = entry?.title || '';
    form.elements.date.value = entry?.date || selectedDate;
    form.elements.time.value = entry?.time || '';
    form.elements.title.readOnly = Boolean(entry);
    form.elements.title.removeAttribute('aria-invalid');
    form.elements.date.removeAttribute('aria-invalid');
    find('#day-task-error').textContent = '';
    find('#day-task-title').textContent = entry ? 'Cambiar fecha y hora' : 'Nueva tarea';
    openDialog('day-task');
  }
  function journalForm() {
    find('#day-journal-editor').elements.text.value = daily().journal;
    find('#day-journal-error').textContent = '';
    find('#day-journal-editor').elements.text.removeAttribute('aria-invalid');
    openDialog('day-journal-form');
  }
  function showDetail(id) {
    current = entries.find(entry => entry.id === id);
    if (!current) return;
    find('[data-day-detail-title]').textContent = current.title;
    find('[data-day-detail-meta]').textContent = `${TYPES[current.type][0]} · ${current.date} · ${current.time || 'Sin hora'}${current.duration ? ` · ${current.duration} min` : ''}${endOf(current) ? ` · Fin: ${endOf(current)}` : ''}`;
    find('[data-day-detail-description]').textContent = current.description || 'Sin notas adicionales.';
    find('[data-day-task-actions]').hidden = current.type !== 'task';
    find('[data-day-complete]').textContent = current.completed ? 'Reabrir tarea' : 'Completar tarea';
    openDialog('day-detail');
  }
  function announce(message, date = null) {
    destination = date;
    const notice = find('[data-day-notice]');
    notice.hidden = false;
    notice.querySelector('span').textContent = message;
    find('[data-day-destination]').hidden = !date;
  }
  function invalid(form, name, errorId, message) {
    find(errorId).textContent = message;
    form.elements[name].setAttribute('aria-invalid', 'true');
    form.elements[name].focus();
  }
  function save(kind) {
    const state = daily();
    if (kind === 'task') {
      const form = find('#day-task-form');
      const title = form.elements.title.value.trim(), date = form.elements.date.value, time = form.elements.time.value;
      if (!title) return invalid(form, 'title', '#day-task-error', 'Escribe el título de la tarea.');
      if (!date || !form.elements.date.validity.valid) return invalid(form, 'date', '#day-task-error', 'Selecciona una fecha válida.');
      if (!form.elements.time.validity.valid) return invalid(form, 'time', '#day-task-error', 'Indica una hora válida.');
      if (editing) Object.assign(editing, {date, time});
      else entries.push({id: `new-${++serial}`, title, type: 'task', date, time, duration: null, completed: false, description: ''});
      closeDialog(); render();
      announce(`${editing ? 'Tarea reprogramada' : 'Tarea creada'} para ${date}${time ? ` a las ${time}` : ' sin hora'}.`, date !== selectedDate ? date : null);
      return;
    }
    if (kind === 'water') {
      const form = find('#day-water-form'), amount = Number(form.elements.amount.value);
      if (!Number.isSafeInteger(amount) || amount <= 0 || !Number.isSafeInteger(state.water + amount)) return invalid(form, 'amount', '#day-water-error', 'Escribe una cantidad positiva de agua en ml enteros.');
      state.water += amount;
    }
    if (kind === 'mood') state.mood = find('#day-mood-form').elements.mood.value;
    if (kind === 'energy') {
      const value = Number(find('#day-energy-form').elements.energy.value);
      if (!Number.isInteger(value) || value < 1 || value > 5) return;
      state.energy = value;
    }
    if (kind === 'journal') {
      const form = find('#day-journal-editor'), text = form.elements.text.value;
      if (!text.trim()) return invalid(form, 'text', '#day-journal-error', 'Escribe algo antes de guardar tu entrada.');
      state.journal = text;
    }
    closeDialog(); render(); announce('Registro guardado para el día seleccionado.');
  }
  // Captura solo las acciones locales para no duplicar el manejo de diálogos del shell.
  const abort = new AbortController();
  root.addEventListener('click', event => {
    const toggle = event.target.closest('[data-lt-toggle]');
    if (toggle && toggle.dataset.ltToggle.startsWith('day-')) {
      event.preventDefault(); event.stopImmediatePropagation();
      const id = toggle.dataset.ltToggle;
      if (activeDialog?.id === id) return closeDialog();
      // El menú del FAB sigue a cargo del shell; aquí solo se interceptan diálogos.
      root.querySelectorAll('.lt-fab-dial__menu').forEach(menu => { menu.hidden = true; });
      root.querySelectorAll('.lt-fab[aria-expanded]').forEach(button => button.setAttribute('aria-expanded', 'false'));
      if (id === 'day-task') return taskForm();
      if (id === 'day-journal-form') return journalForm();
      if (id === 'day-water') { find('#day-water-error').textContent = ''; find('#day-water-form').elements.amount.removeAttribute('aria-invalid'); find('#day-water-form').elements.amount.value = '250'; }
      openDialog(id, toggle);
      return;
    }
    const action = event.target.closest('[data-lt-action^="day-save-"]');
    if (action) { event.preventDefault(); event.stopImmediatePropagation(); return save(action.dataset.ltAction.slice(9)); }
    const step = event.target.closest('[data-day-step]');
    if (step) return setDate(shiftDay(selectedDate, Number(step.dataset.dayStep)));
    if (event.target.closest('[data-day-today]')) return setDate(today);
    if (event.target.closest('[data-day-destination]') && destination) return setDate(destination);
    const entry = event.target.closest('[data-day-entry]');
    if (entry) { event.stopImmediatePropagation(); return showDetail(entry.dataset.dayEntry); }
    if (event.target.closest('[data-day-edit-journal]')) { event.stopImmediatePropagation(); return journalForm(); }
    if (event.target.closest('[data-day-complete]') && current?.type === 'task') {
      current.completed = !current.completed; closeDialog(); render(); announce(current.completed ? 'Tarea completada.' : 'Tarea reabierta.');
    }
    if (event.target.closest('[data-day-reschedule]') && current?.type === 'task') taskForm(current);
  }, {capture: true, signal: abort.signal});
  scope.cleanup(() => { abort.abort(); closeDialog(false); });
  scope.on(surface, 'change', event => {
    if (event.target.matches('[data-day-date]')) { if (event.target.value && event.target.validity.valid) setDate(event.target.value); else event.target.value = selectedDate; }
    if (event.target.matches('[data-day-habit]')) {
      const habit = daily().habits.find(item => item.id === event.target.dataset.dayHabit);
      habit.done = event.target.checked;
      find('[data-day-habit-count]').textContent = `${daily().habits.filter(item => item.done).length} de ${daily().habits.length} completados`;
      toast(habit.done ? 'Hábito completado.' : 'Hábito pendiente.');
    }
  });
  scope.on(surface, 'submit', event => {
    event.preventDefault();
    const kinds = {'day-task-form': 'task', 'day-water-form': 'water', 'day-mood-form': 'mood', 'day-energy-form': 'energy', 'day-journal-editor': 'journal'};
    if (kinds[event.target.id]) save(kinds[event.target.id]);
  });
  scope.on(document, 'keydown', event => {
    if (!activeDialog) return;
    if (event.key === 'Escape') { event.preventDefault(); closeDialog(); return; }
    if (event.key !== 'Tab') return;
    const focusable = [...activeDialog.querySelectorAll('button,input,select,textarea,a[href]')].filter(node => !node.disabled && node.tabIndex >= 0 && node.getClientRects().length);
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  render();
}
