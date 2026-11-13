import {mountCategoryCatalog} from './category-catalog.js';
export function mountLifeShell({root, scope, toast}) {
  mountCategoryCatalog({root, scope, toast});
  const compact = () => window.matchMedia('(max-width: 767.98px)').matches;
  const closePopups = except => [...new Set([...root.querySelectorAll('[data-lt-toggle]')].map(b => b.dataset.ltToggle))].forEach(id => { const el = root.querySelector('#' + id); if (el && el !== except && !(except && el.contains(except)) && !el.hidden) { el.hidden = true; root.querySelectorAll('[data-lt-toggle="' + id + '"][aria-expanded]').forEach(b => b.setAttribute('aria-expanded', 'false')); } });
  const syncFilterCount = () => { const count = root.querySelectorAll('[data-lt-remove-filter]').length; const badge = root.querySelector('[data-lt-filter-count]'); if (badge) { badge.textContent = count; badge.hidden = count === 0; badge.setAttribute('aria-label', count + (count === 1 ? ' filtro activo' : ' filtros activos')); } const link = root.querySelector('[data-lt-clear-filters].lt-link'); if (link) link.hidden = count === 0; };
  const readStore = key => { try { return sessionStorage.getItem(key); } catch (error) { return null; } };
  const dropStore = key => { try { sessionStorage.removeItem(key); } catch (error) { /* sin almacenamiento */ } };
  const zoneFilter = readStore('lt-health-zone');
  const applied = root.querySelector('.lt-applied-filters');
  if (zoneFilter && applied && root.querySelector('[data-lt-visible-count]') && !applied.querySelector('[data-zone-chip]')) {
    const chip = document.createElement('span');
    chip.className = 'lt-chip lt-chip--input';
    chip.dataset.zoneChip = '';
    chip.innerHTML = '<i class="bi bi-person-standing"></i> <span></span><button class="lt-chip__remove" type="button" data-lt-remove-filter><i class="bi bi-x"></i></button>';
    chip.querySelector('span').textContent = 'Zona del cuerpo: ' + zoneFilter;
    chip.querySelector('button').setAttribute('aria-label', 'Quitar filtro Zona del cuerpo: ' + zoneFilter);
    applied.prepend(chip);
    const rangeFilter = readStore('lt-health-range');
    const rangeLabel = root.querySelector('[data-filter="range"] [data-filter-label]');
    if (rangeFilter && rangeLabel) rangeLabel.textContent = rangeFilter;
    syncFilterCount();
  }
  scope.on(root, 'click', event => {
    if (!root.querySelector('[data-learning-view]') && event.target.closest('[data-learning-action="advice"]')) { const line = root.querySelector('[data-advice]'); if (line) line.textContent = line.textContent.includes('paso pequeño') ? 'Una tarea a la vez. Tu agenda no necesita hacer malabares.' : 'Un paso pequeño también abre camino. ¿Por cuál empezamos?'; return; }
    if (event.target.closest('.lt-multiselect__panel') && !event.target.closest('.lt-multiselect__option')) return;
    const dlg = event.target.closest('.lt-dialog');
    const sidebar = event.target.closest('[data-lt-dialog-sidebar]');
    if (sidebar && dlg) { const open = compact() ? dlg.classList.toggle('is-nav-open') : !dlg.classList.toggle('is-nav-collapsed'); sidebar.setAttribute('aria-expanded', String(open)); return; }
    const expand = event.target.closest('[data-lt-dialog-expand]');
    if (expand && dlg) { const on = dlg.classList.toggle('is-expanded'); expand.setAttribute('aria-pressed', String(on)); expand.setAttribute('aria-label', on ? 'Restaurar' : 'Expandir'); expand.querySelector('i').className = 'bi ' + (on ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'); return; }
    const section = event.target.closest('[data-lt-dialog-section]');
    if (section && dlg) { dlg.querySelectorAll('[data-lt-dialog-section]').forEach(b => b === section ? b.setAttribute('aria-current', 'true') : b.removeAttribute('aria-current')); dlg.querySelectorAll('.lt-dialog__section').forEach(p => { p.hidden = p.dataset.section !== section.dataset.ltDialogSection; }); dlg.classList.remove('is-nav-open'); if (compact()) dlg.querySelector('[data-lt-dialog-sidebar]')?.setAttribute('aria-expanded', 'false'); return; }
    const mSearch = event.target.closest('[data-lt-mcard-search]');
    if (mSearch) { const card = mSearch.closest('.lt-mcard'); const on = card.classList.toggle('is-searching'); card.querySelectorAll('[data-lt-mcard-search]').forEach(b => b.setAttribute('aria-expanded', String(on))); if (on) card.querySelector('.lt-mcard__search input')?.focus(); return; }
    const toggle = event.target.closest('[data-lt-toggle]');
    if (toggle) { const el = root.querySelector('#' + toggle.dataset.ltToggle); if (el) { closePopups(el); if (el.classList.contains('lt-multiselect__panel')) { const rect = toggle.getBoundingClientRect(); const above = !compact() && innerHeight - rect.bottom < 280 && rect.top > innerHeight - rect.bottom; el.style.top = above ? 'auto' : ''; el.style.bottom = above ? 'calc(100% + 4px)' : ''; } el.hidden = !el.hidden; root.querySelectorAll('[data-lt-toggle="' + toggle.dataset.ltToggle + '"][aria-expanded]').forEach(b => b.setAttribute('aria-expanded', String(!el.hidden))); } return; }
    const remove = event.target.closest('[data-lt-remove-filter]');
    const clear = event.target.closest('[data-lt-clear-filters]');
    if (remove || clear) {
      const card = (remove || clear).closest('.lt-mcard'); const area = card || root;
      (clear ? area.querySelectorAll('[data-lt-remove-filter]') : [remove]).forEach(b => { const chip = b.closest('.lt-chip'); if (chip?.hasAttribute('data-zone-chip')) { dropStore('lt-health-zone'); dropStore('lt-health-range'); } chip?.remove(); });
      const count = area.querySelectorAll('[data-lt-remove-filter]').length;
      const badge = area.querySelector('[data-lt-filter-count]'); if (badge) { badge.textContent = count; badge.hidden = count === 0; badge.setAttribute('aria-label', count + (count === 1 ? ' filtro activo' : ' filtros activos')); }
      const link = area.querySelector('[data-lt-clear-filters].lt-link'); if (link) link.hidden = count === 0;
      if (count === 0) {
        const counter = area.querySelector('[data-lt-visible-count]'); if (counter) counter.textContent = '(' + (counter.dataset.total || '') + ' / ' + (counter.dataset.total || '') + ')';
        const foot = card?.querySelector('[data-range-all]');
        if (foot) { card.classList.add('is-unfiltered'); foot.querySelector('.lt-mcard__range').innerHTML = foot.dataset.rangeAll; const compactPager = foot.querySelector('.lt-pager__compact'); if (compactPager) compactPager.textContent = '1 / ' + foot.dataset.pagesAll; const steps = foot.querySelectorAll('.lt-pager__step'); if (steps[1]) steps[1].disabled = false; }
      }
      area.querySelectorAll('.lt-popover').forEach(p => { p.hidden = true; });
      return;
    }
    if (!event.target.closest('.lt-popover,.lt-menu,.lt-dialog')) closePopups(null);
    if (event.target.closest('.lt-multiselect__option')) { const panel = event.target.closest('.lt-multiselect__panel'); const picked = [...panel.querySelectorAll('input:checked')].map(i => i.parentElement.textContent.trim()); root.querySelector('[data-lt-toggle="' + panel.id + '"] span').textContent = picked.length ? picked.join(', ') : 'Todos los tipos'; return; }
    const action = event.target.closest('[data-lt-nav-toggle],[data-lt-open-drawer],[data-lt-close-drawer],[data-lt-static-nav],[data-lt-search]');
    if (!action) return;
    const shell = action.closest('.lt-shell');
    if (!shell) return;
    if (action.hasAttribute('data-lt-nav-toggle')) { shell.classList.toggle(compact() ? 'is-drawer-open' : 'is-rail'); return; }
    if (action.hasAttribute('data-lt-open-drawer')) { shell.classList.add('is-drawer-open'); return; }
    if (action.hasAttribute('data-lt-close-drawer')) { shell.classList.remove('is-drawer-open'); return; }
    if (action.hasAttribute('data-lt-search')) { shell.querySelector('[data-lt-search-input]')?.focus(); return; }
    toast('Este destino se muestra como referencia y no forma parte de este mockup.');
  });
  scope.on(window, 'keydown', event => { if (event.key === 'Escape') { closePopups(null); root.querySelectorAll('.lt-mcard.is-searching').forEach(c => c.classList.remove('is-searching')); } if (event.key === 'Escape') root.querySelector('.lt-shell')?.classList.remove('is-drawer-open'); });
}

// Hoja inferior de filtros en móvil: arrastrar el indicador hacia abajo la cierra.
document.addEventListener('pointerdown', event => {
  const handle = event.target.closest('.lt-popover__handle'); if (!handle || !matchMedia('(max-width: 767.98px)').matches) return;
  const sheet = handle.closest('.lt-popover'); const start = event.clientY; let dy = 0;
  const move = e => { dy = Math.max(0, e.clientY - start); sheet.style.transform = `translateY(${dy}px)`; };
  const up = () => { removeEventListener('pointermove', move); removeEventListener('pointerup', up); sheet.style.transform = ''; if (dy > 80) { sheet.hidden = true; document.querySelectorAll(`[data-lt-toggle="${sheet.id}"][aria-expanded]`).forEach(b => b.setAttribute('aria-expanded', 'false')); } };
  addEventListener('pointermove', move); addEventListener('pointerup', up);
});
// El scrim de la hoja (::before de .lt-popover) recibe el toque: cerrar si cae fuera del panel, sin activar lo que hay debajo.
document.addEventListener('click', event => {
  const sheet = event.target.classList?.contains('lt-popover') ? event.target : null;
  if (!sheet || !matchMedia('(max-width: 767.98px)').matches) return;
  if (event.clientY >= sheet.getBoundingClientRect().top) return;
  event.stopImmediatePropagation(); sheet.hidden = true;
  document.querySelectorAll(`[data-lt-toggle="${sheet.id}"][aria-expanded]`).forEach(b => b.setAttribute('aria-expanded', 'false'));
}, true);
