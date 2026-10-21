// Panel de pared (tablet): registros rápidos con deshacer, capas del calendario, foco, menús del rail y modos noche/ambiente.
export function mountTabletPanel({root, scope}) {
  const panel = root.querySelector('[data-tp-root]'); if (!panel) return;
  const $ = s => panel.querySelector(s), $$ = s => [...panel.querySelectorAll(s)];
  const toastEl = $('[data-tp-toast]'); let undo = null, toastTimer = 0;
  const notify = (text, revert = null) => { $('[data-tp-toast-text]').textContent = text; undo = revert; toastEl.querySelector('[data-tp-undo]').hidden = !revert; toastEl.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toastEl.hidden = true; undo = null; }, 5000); };
  const open = id => { const el = root.querySelector('#' + id); if (el) el.hidden = false; };
  const close = id => { const el = root.querySelector('#' + id); if (el) el.hidden = true; };
  const picked = (dlg, key) => dlg.querySelector(`[data-tp-pick="${key}"].is-selected`)?.dataset.value;

  let water = 900; const goal = 2000;
  const setWater = ml => { water = Math.max(0, ml); const pct = Math.min(100, Math.round(water / goal * 100)); $('[data-tp-water]').textContent = water.toLocaleString('es-CO'); $('[data-tp-ring]').style.setProperty('--value', pct); $('[data-tp-water-pct]').textContent = pct + '%'; $('[data-tp-water-bar]').style.width = pct + '%'; };
  const addWater = (drink, ml) => { const before = water; setWater(water + ml); notify(`${drink} ${ml} ml registrado`, () => setWater(before)); };

  const setMood = label => { $$('[data-tp-mood]').forEach(b => b.classList.toggle('is-selected', b.dataset.tpMood === label)); $('[data-tp-mood-last]').textContent = label.split(' ')[0] + ' ahora'; };

  const habitCount = () => { const card = $$('.tp-habits [data-tp-habit]'); $('[data-tp-habit-count]').textContent = card.filter(b => b.classList.contains('is-done')).length + '/' + card.length; };
  const setHabit = (id, on) => { $$(`[data-tp-habit="${id}"]`).forEach(b => { b.classList.toggle('is-done', on); b.setAttribute('aria-pressed', String(on)); }); habitCount(); };

  const sheetRow = (html, done) => { const row = document.createElement('div'); row.className = 'tp-sheet-row' + (done ? ' is-done' : ''); row.innerHTML = html; return row; };
  const fillSheet = (title, rows) => { root.querySelector('#tp-day-dialog-title').textContent = title; const body = $('[data-tp-day-body]'); body.replaceChildren(...rows); if (!rows.length) body.innerHTML = '<p class="lt-prose">Día libre: nada programado.</p>'; };
  const fillDay = index => { const col = $(`[data-tp-day-col="${index}"]`); fillSheet(col.dataset.tpDayLabel, [...col.querySelectorAll('.tp-row, .tp-meal')].map(item => sheetRow(item.innerHTML, item.classList.contains('is-done')))); };

  // Semana: cada día muestra las filas que caben y resume el resto en «+N más» (sin scroll interno).
  const fit = box => { const more = box.querySelector('[data-tp-more]'); const all = [...box.querySelectorAll('.tp-row')]; all.forEach(r => r.classList.remove('is-overflow')); more.hidden = true; if (box.scrollHeight <= box.clientHeight) return; more.hidden = false; const rows = all.filter(r => r.offsetParent !== null); let hidden = 0; for (let i = rows.length - 1; i >= 0 && box.scrollHeight > box.clientHeight; i--) { rows[i].classList.add('is-overflow'); hidden++; } more.querySelector('b').textContent = hidden; };
  const fitAll = () => $$('[data-tp-fit]').forEach(fit);
  const observer = new ResizeObserver(fitAll); observer.observe($('.tp-week__days')); scope.cleanup?.(() => observer.disconnect());

  // Línea de tiempo: solo lo que tiene hora, ventana de 8 h alrededor de «ahora», agrupando lo que cae a menos de 45 min.
  const tl = $('[data-tp-timeline]'), DAY_START = 360, DAY_END = 1410, WINDOW = 480, GAP = 45, now = Number(tl.dataset.now);
  const tlItems = [...tl.querySelectorAll('[data-tp-tl-items] li')].map(li => ({min: Number(li.dataset.min), time: li.dataset.time, kind: li.dataset.kind, done: li.hasAttribute('data-done'), title: li.textContent}));
  const icons = {habit: 'bi-check2', meal: 'bi-egg-fried', task: 'bi-list-task', people: 'bi-heart'};
  let start = Math.min(Math.max(Math.floor((now - 120) / 60) * 60, DAY_START), DAY_END - WINDOW), groups = [];
  const pos = m => ((m - start) / WINDOW * 100) + '%';
  const renderTimeline = () => {
    const track = $('[data-tp-tl-track]'); track.querySelectorAll('.tp-pin').forEach(p => p.remove());
    groups = []; tlItems.filter(i => i.min >= start && i.min <= start + WINDOW).forEach(i => { const g = groups.at(-1); if (g && i.min - g[0].min < GAP) g.push(i); else groups.push([i]); });
    groups.forEach((g, index) => { const kinds = g.map(i => i.kind); const kind = kinds.sort((a, b) => kinds.filter(k => k === b).length - kinds.filter(k => k === a).length)[0]; const pin = document.createElement('button'); pin.type = 'button'; pin.className = `tp-pin tp-pin--${kind}` + (g.every(i => i.done || i.min < now) ? ' is-past' : '') + (g.length > 1 ? ' is-cluster' : ''); pin.style.left = pos(g.reduce((s, i) => s + i.min, 0) / g.length); pin.dataset.tpCluster = index; pin.setAttribute('aria-haspopup', 'dialog'); pin.title = g.map(i => `${i.time} · ${i.title}`).join(' / '); pin.setAttribute('aria-label', g.length > 1 ? `${g.length} elementos desde las ${g[0].time}` : `${g[0].time} ${g[0].title}`); pin.innerHTML = g.length > 1 ? `<b>${g.length}</b>` : `<i class="bi ${icons[kind]}"></i>`; track.append(pin); });
    const inside = now >= start && now <= start + WINDOW; const marker = $('[data-tp-tl-now]'); marker.hidden = !inside; marker.style.left = pos(now); $('[data-tp-tl-past]').style.width = now <= start ? '0' : (now >= start + WINDOW ? '100%' : pos(now));
    $('[data-tp-tl-scale]').innerHTML = Array.from({length: WINDOW / 120 + 1}, (_, k) => `<span style="left:${k * 25}%">${String((start + k * 120) / 60).padStart(2, '0')}:00</span>`).join('');
    tl.querySelector('[data-tp-tl-shift="-240"]').disabled = start <= DAY_START; tl.querySelector('[data-tp-tl-shift="240"]').disabled = start >= DAY_END - WINDOW;
  };
  renderTimeline();

  // Sesión de foco a pantalla completa (25 min).
  let focusLeft = 0, focusTimer = 0;
  const renderFocus = () => { $('[data-tp-focus-time]').textContent = String(Math.floor(focusLeft / 60)).padStart(2, '0') + ':' + String(focusLeft % 60).padStart(2, '0'); };
  const stopFocus = () => { clearInterval(focusTimer); focusTimer = 0; };
  const runFocus = () => { stopFocus(); focusTimer = setInterval(() => { focusLeft = Math.max(0, focusLeft - 1); renderFocus(); if (!focusLeft) stopFocus(); }, 1000); $('[data-tp-focus-pause]').textContent = 'Pausar'; };
  scope.cleanup?.(() => { stopFocus(); clearTimeout(toastTimer); });

  const closeFlyouts = except => $$('.tp-flyout').forEach(f => { if (f !== except && !f.hidden) { f.hidden = true; $(`[data-lt-toggle="${f.id}"]`)?.setAttribute('aria-expanded', 'false'); } });

  scope.on(root, 'click', event => {
    const t = event.target;
    if (!t.closest('.tp-rail__group')) closeFlyouts(null);
    const day = t.closest('[data-tp-day]'); if (day) fillDay(day.dataset.tpDay);
    const shift = t.closest('[data-tp-tl-shift]'); if (shift) { start = Math.min(Math.max(start + Number(shift.dataset.tpTlShift), DAY_START), DAY_END - WINDOW); renderTimeline(); return; }
    const cluster = t.closest('[data-tp-cluster]'); if (cluster) { const g = groups[cluster.dataset.tpCluster]; fillSheet(g.length > 1 ? `${g[0].time} – ${g.at(-1).time} · ${g.length} elementos` : g[0].title, g.map(i => sheetRow(`<i class="bi ${icons[i.kind]}"></i><small>${i.time}</small><span>${i.title}</span>`, i.done))); open('tp-day-dialog'); return; }
    const drink = t.closest('[data-tp-drink]'); if (drink) { addWater(drink.dataset.tpDrink, Number(drink.dataset.tpMl)); return; }
    const mood = t.closest('[data-tp-mood]'); if (mood) { const prev = $('[data-tp-mood].is-selected')?.dataset.tpMood; setMood(mood.dataset.tpMood); notify(`Ánimo: ${mood.dataset.tpMood}`, prev ? () => setMood(prev) : null); return; }
    const habit = t.closest('[data-tp-habit]'); if (habit) { const id = habit.dataset.tpHabit, on = !habit.classList.contains('is-done'); setHabit(id, on); notify(`${habit.querySelector('span:not([class])').textContent} ${on ? 'completado' : 'desmarcado'}`, () => setHabit(id, !on)); return; }
    const layer = t.closest('[data-tp-layer]'); if (layer) { const on = layer.getAttribute('aria-pressed') !== 'true'; layer.setAttribute('aria-pressed', String(on)); $('[data-tp-week]').classList.toggle('hide-' + layer.dataset.tpLayer, !on); fitAll(); return; }
    const pick = t.closest('[data-tp-pick]'); if (pick) { pick.parentElement.querySelectorAll(`[data-tp-pick="${pick.dataset.tpPick}"]`).forEach(b => b.classList.toggle('is-selected', b === pick)); return; }
    const opener = t.closest('[data-tp-open]'); if (opener) { close('tp-create-dialog'); open(opener.dataset.tpOpen); return; }
    const snooze = t.closest('[data-tp-snooze]'); if (snooze) { close('tp-task-dialog'); notify(`Pospuesta: ${snooze.dataset.tpSnooze}`, () => {}); return; }
    if (t.closest('[data-tp-complete-next]')) { const ev = $('[data-tp-sprint]'); ev.classList.add('is-done'); notify('Revisión sprint SGX completada', () => ev.classList.remove('is-done')); return; }
    if (t.closest('[data-tp-focus-start]')) { $$('.lt-dialog-layer').forEach(d => { d.hidden = true; }); focusLeft = 25 * 60; renderFocus(); $('[data-tp-focus]').hidden = false; runFocus(); return; }
    if (t.closest('[data-tp-focus-pause]')) { if (focusTimer) { stopFocus(); t.textContent = 'Seguir'; } else runFocus(); return; }
    if (t.closest('[data-tp-focus-stop]')) { stopFocus(); $('[data-tp-focus]').hidden = true; notify(`Sesión de foco registrada: ${25 - Math.ceil(focusLeft / 60)} min`); return; }
    if (t.closest('[data-tp-shop-item]')) { const left = $$('[data-tp-shop-item]').filter(i => !i.checked).length; $('[data-tp-shop-count]').textContent = left + (left === 1 ? ' compra' : ' compras'); return; }
    const action = t.closest('[data-lt-action^="tp-"]');
    if (action) {
      const dlg = action.closest('.lt-dialog-layer'), name = action.dataset.ltAction;
      if (name === 'tp-water-save') { const custom = Number(dlg.querySelector('.tp-custom input').value); addWater(picked(dlg, 'drink') || 'Agua', custom || Number(picked(dlg, 'ml') || 250)); }
      if (name === 'tp-mood-save') { const label = picked(dlg, 'mood'); if (label) setMood(label); notify('Ánimo y energía guardados'); }
      if (name === 'tp-task-done') { const ev = $('[data-tp-sprint]'); ev.classList.add('is-done'); notify('Tarea completada', () => ev.classList.remove('is-done')); }
      if (name === 'tp-goal-save') { const id = picked(dlg, 'goal'); const bar = $(`[data-tp-goal-bar="${id}"]`); const before = bar.style.width; bar.style.width = Math.min(100, parseFloat(before) + 10) + '%'; notify('Avance registrado', () => { bar.style.width = before; }); }
      const messages = {'tp-plan-visit': 'Visita registrada en el plan', 'tp-avoid-log': 'Recaída registrada. Mañana es un nuevo día.', 'tp-journal-save': 'Entrada del diario guardada', 'tp-task-create': 'Tarea creada'};
      if (messages[name]) notify(messages[name]);
      close(dlg.id); return;
    }
    if (t.closest('[data-tp-undo]')) { undo?.(); undo = null; toastEl.hidden = true; return; }
    const night = t.closest('[data-tp-night]'); if (night) { const on = panel.classList.toggle('is-night'); night.setAttribute('aria-pressed', String(on)); night.querySelector('i').className = on ? 'bi bi-sun' : 'bi bi-moon'; return; }
    if (t.closest('[data-tp-ambient]')) { const amb = $('.tp-ambient'); amb.hidden = !amb.hidden; }
  });
}
