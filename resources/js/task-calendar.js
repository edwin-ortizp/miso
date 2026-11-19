let fullCalendarPromise;

// FullCalendar se carga bajo demanda: solo la pestaña Calendario de Tareas lo usa.
function loadFullCalendar() {
    fullCalendarPromise ??= Promise.all([
        import('@fullcalendar/core'),
        import('@fullcalendar/core/locales/es'),
        import('@fullcalendar/daygrid'),
        import('@fullcalendar/timegrid'),
        import('@fullcalendar/list'),
        import('@fullcalendar/interaction'),
    ]).then(([core, es, ...plugins]) => ({ Calendar: core.Calendar, locale: es.default, plugins: plugins.map(module => module.default) }));

    return fullCalendarPromise;
}

const compact = () => matchMedia('(max-width: 767.98px)').matches;
const selectedViews = new Map();
const layerStorageKey = 'miso.task-calendar.layers.v1';
const labels = { tasks: 'Tareas', plans: 'Planes', birthdays: 'Cumpleaños', meals: 'Comidas', habits: 'Hábitos', reservations: 'Reservas', evaluations: 'Evaluaciones', health: 'Salud' };
const defaultLayers = () => ({ tasks: true, plans: true, birthdays: true, meals: true, habits: true, reservations: true, evaluations: true, health: false });

export function readCalendarLayers(account = null) {
    try {
        const stored = account && localStorage.getItem(`${layerStorageKey}:${account}`);
        const legacy = !stored;
        const value = JSON.parse(stored || localStorage.getItem(layerStorageKey));
        if (legacy && value) {
            delete value.health;
            delete value.habits;
            delete value.reservations;
            delete value.evaluations;
        }
        if (value && ['tasks', 'plans', 'birthdays'].every(key => typeof value[key] === 'boolean')) {
            return Object.fromEntries(Object.keys(defaultLayers()).map(key => [key, typeof value[key] === 'boolean' ? value[key] : defaultLayers()[key]]));
        }
    } catch { /* El calendario funciona también sin almacenamiento local. */ }
    return defaultLayers();
}

export function registerTaskCalendar(Alpine) {
    Alpine.data('taskCalendar', (wire, account) => ({
        calendar: null,
        actionEvent: null,
        actionTrigger: null,
        actionBusy: false,
        actionError: '',
        get mobileAgenda() { return compact() && this.calendar?.view.type === 'listWeek'; },
        onActionScroll() {
            if (!this.actionEvent) return;
            if (this.mobileAgenda) {
                this.positionActions();
            } else {
                this.closeActions(false);
            }
        },
        closeActions(restore = true) {
            this.actionEvent = null;
            this.actionTrigger?.setAttribute('aria-expanded', 'false');
            if (restore && this.actionTrigger?.isConnected) this.actionTrigger.focus({ preventScroll: this.mobileAgenda });
            this.actionTrigger = null;
        },
        openActions(event, trigger) {
            this.closeActions(false);
            this.actionEvent = { id: event.id, title: event.title, ...event.extendedProps };
            this.actionTrigger = trigger;
            this.actionError = '';
            trigger.setAttribute('aria-expanded', 'true');
            this.$nextTick(() => {
                if (this.actionTrigger !== trigger) return;
                this.positionActions();
                if (this.actionEvent) {
                    this.$refs.actionMenu.querySelector('[role="menuitem"]')?.focus({ preventScroll: this.mobileAgenda });
                }
            });
        },
        positionActions() {
            const trigger = this.actionTrigger;
            if (!trigger?.isConnected) {
                this.closeActions(false);
                return;
            }
            const menu = this.$refs.actionMenu;
            const rect = trigger.getBoundingClientRect();
            const view = window.visualViewport;
            const viewLeft = view?.offsetLeft ?? 0;
            const viewTop = view?.offsetTop ?? 0;
            const viewWidth = Math.min(view?.width ?? innerWidth, document.documentElement.clientWidth || innerWidth);
            const viewHeight = view?.height ?? innerHeight;
            if (this.mobileAgenda && (rect.bottom <= viewTop || rect.top >= viewTop + viewHeight)) {
                this.closeActions(false);
                return;
            }
            const left = Math.max(viewLeft + 8, Math.min(rect.right - menu.offsetWidth, viewLeft + viewWidth - menu.offsetWidth - 8));
            const top = Math.max(viewTop + 8, Math.min(rect.bottom + 4, viewTop + viewHeight - menu.offsetHeight - 8));
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        },
        menuKey(event) {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const items = [...this.$refs.actionMenu.querySelectorAll('[role="menuitem"]')].filter(item => !item.disabled && item.getClientRects().length);
            const index = items.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next]?.focus();
        },
        async runAction(action) {
            if (!this.actionEvent || this.actionBusy) return;
            const selected = this.actionEvent;
            if (action === 'delete' && !window.confirm(`La tarea «${selected.title}» se elimina de forma permanente.`)) return;
            if (action === 'edit') {
                this.closeActions();
                this.$dispatch('task-editor', { action: 'openForm', id: selected.id, mode: 'edit' });
                return;
            }
            if (action === 'findSlot') {
                this.closeActions();
                this.$dispatch('task-find-slot', { id: selected.id });
                return;
            }
            this.actionBusy = true;
            this.actionError = '';
            try {
                await wire.calendarAction(selected.id, action);
                this.closeActions();
            } catch {
                this.actionError = 'No se pudo realizar la acción. Inténtalo de nuevo.';
            } finally { this.actionBusy = false; }
        },
        mountActionTrigger(info) {
            if (info.event.extendedProps.type !== 'tasks') return;
            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'md-calendar-task-actions md-btn-icon';
            trigger.setAttribute('aria-label', `Más acciones de ${info.event.title}`);
            trigger.setAttribute('aria-haspopup', 'menu');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.innerHTML = '<i class="bi bi-three-dots-vertical" aria-hidden="true"></i>';
            for (const name of ['pointerdown', 'mousedown', 'touchstart', 'dblclick']) trigger.addEventListener(name, event => event.stopPropagation());
            trigger.addEventListener('keydown', event => { if (event.key !== 'Escape') event.stopPropagation(); });
            trigger.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                if (this.actionTrigger === trigger) this.closeActions();
                else this.openActions(info.event, trigger);
            });
            const host = info.el.querySelector('.fc-event-main, .fc-list-event-title') || info.el;
            host.classList.add('md-calendar-action-host');
            host.append(trigger);
        },
        layers: readCalendarLayers(account),
        requestedLayers: [],
        requestVersion: 0,
        resetDraftLayers() { this.draftLayers = defaultLayers(); },
        draftLayers: defaultLayers(),
        filtersOpen: false,
        get hasLayerFilter() { return Object.keys(defaultLayers()).some(key => (this.layers[key] ?? defaultLayers()[key]) !== defaultLayers()[key]); },
        get layerFilterLabel() {
            const hidden = Object.keys(labels).filter(key => defaultLayers()[key] && this.layers[key] === false).map(key => labels[key]);
            return [hidden.length ? `Ocultos: ${hidden.join(', ')}` : '', this.layers.health ? 'Salud visible' : ''].filter(Boolean).join(' · ');
        },
        openFilters() {
            this.draftLayers = { ...this.layers };
            this.filtersOpen = true;
        },
        applyLayers() {
            this.layers = { ...this.draftLayers };
            try { localStorage.setItem(`${layerStorageKey}:${account}`, JSON.stringify(this.layers)); } catch { /* Solo memoria. */ }
            this.calendar?.batchRendering(() => {
                for (const event of this.calendar.getEvents()) event.setProp('display', this.layers[event.extendedProps.type] ? 'auto' : 'none');
            });
            if (Object.keys(this.layers).some(key => this.layers[key] && !this.requestedLayers.includes(key))) this.calendar?.refetchEvents();
            this.filtersOpen = false;
            this.fitCalendar();
            this.$nextTick(() => this.$refs.filterTrigger?.focus());
        },
        clearLayerFilter() {
            this.draftLayers = defaultLayers();
            this.applyLayers();
        },
        async persistEvent(info) {
            if (info.event.extendedProps.type === 'reservations') {
                try {
                    await wire.moveReservation(info.event.extendedProps.reservation, info.event.extendedProps.occurrence, info.event.startStr, info.event.endStr);
                    this.calendar.refetchEvents();
                } catch { info.revert(); }
                return;
            }
            if (info.event.extendedProps.type !== 'tasks') {
                info.revert();
                return;
            }
            try {
                await wire.moveTask(info.event.id, info.event.startStr, info.event.endStr || null, info.event.allDay);
            } catch {
                info.revert();
            }
        },
        clickEvent(info) {
            if (info.jsEvent?.target?.closest('.md-calendar-task-actions')) return;
            this.closeActions(false);
            if (info.event.extendedProps.type === 'tasks') this.$dispatch('task-editor', { action: 'openForm', id: info.event.id });
            else if (info.event.extendedProps.type === 'reservations') {
                const { reservation, occurrence } = info.event.extendedProps;
                this.$dispatch('study-reservation-edit', { reservation, occurrence });
            }
            else if (info.event.extendedProps.detailUrl) {
                window.location.assign(info.event.extendedProps.detailUrl);
            }
        },
        selectView(view) {
            if (!this.calendar) return;
            selectedViews.set(account, view);
            this.calendar.changeView(view);
        },
        mountAgendaRow(info) {
            if (info.view.type !== 'listWeek') return;
            // La cuadrícula móvil cambia el display de la fila. Mantener sus roles de tabla.
            info.el.setAttribute('role', 'row');
            for (const cell of info.el.cells) cell.setAttribute('role', 'cell');

            if (info.event.extendedProps.type !== 'tasks') return;
            const { completed, status } = info.event.extendedProps;
            const indicator = document.createElement('span');
            indicator.className = 'md-calendar-agenda-status';
            const label = completed ? 'Completada' : (status?.name || 'Pendiente');
            indicator.setAttribute('role', 'img');
            indicator.setAttribute('aria-label', label);
            indicator.title = label;
            const icon = document.createElement('i');
            icon.className = completed ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            icon.setAttribute('aria-hidden', 'true');
            indicator.append(icon);
            info.el.querySelector('.fc-list-event-title').append(indicator);
        },
        calendarLoading: false,
        mobileDate: '',
        mobileView: 'Día',
        viewType: 'timeGridWeek',
        refresh: null,
        goto: null,
        goToNow(now = new Date()) {
            if (!this.calendar) return;
            if (this.calendar.view.type.startsWith('timeGrid')) this.calendar.gotoDate(now);
            else this.calendar.changeView('timeGridDay', now);
            // Esperar al montaje de la nueva rejilla antes de mover su scroll interno.
            cancelAnimationFrame(this.nowFrame);
            this.nowFrame = requestAnimationFrame(() => {
                const minutes = Math.max(0, now.getHours() * 60 + now.getMinutes() - 30);
                this.calendar?.scrollToTime({ minutes });
                // En ventanas bajas puede caber menos de media hora: mantener «ahora» visible.
                const line = this.$refs.calendar?.querySelector('.fc-timegrid-now-indicator-line');
                const scroller = line?.closest('.fc-scroller');
                if (scroller) {
                    const offset = line.getBoundingClientRect().bottom - scroller.getBoundingClientRect().bottom + 16;
                    if (offset > 0) scroller.scrollTop += offset;
                    const styles = getComputedStyle(this.$refs.calendar);
                    const reserve = compact() ? (parseFloat(styles.getPropertyValue('--calendar-bottom-reserve')) || 80) + 24 + (parseFloat(styles.getPropertyValue('--lt-safe-bottom')) || 0) : 24;
                    const bottom = innerHeight - reserve;
                    const hidden = line.getBoundingClientRect().bottom - bottom + 8;
                    if (hidden > 0) window.scrollBy({ top: hidden, behavior: 'instant' });
                }
            });
        },
        fitCalendar() {
            if (!this.calendar) return;
            const surface = this.$refs.calendar;
            const styles = getComputedStyle(surface);
            const reserve = (parseFloat(styles.getPropertyValue('--calendar-bottom-reserve')) || 24) + (compact() ? 24 + (parseFloat(styles.getPropertyValue('--lt-safe-bottom')) || 0) : 0);
            const viewport = window.visualViewport;
            const bottom = viewport ? viewport.height + viewport.offsetTop : innerHeight;
            const toolbar = surface.querySelector('.fc-header-toolbar')?.getBoundingClientRect().height || 0;
            const height = this.calendar.view.type.startsWith('timeGrid')
                ? Math.round(Math.max(toolbar + 140, bottom - surface.getBoundingClientRect().top - reserve))
                : 'auto';
            if (this.calendar.getOption('height') !== height) this.calendar.setOption('height', height);
        },
        async init() {
            this.$watch('filtersOpen', open => { if (!open) this.$nextTick(() => this.$refs.filterTrigger?.focus()); });
            const { Calendar, locale, plugins } = await loadFullCalendar();

            this.calendar = new Calendar(this.$refs.calendar, {
                plugins,
                locale,
                firstDay: 1,
                height: 'auto',
                initialView: selectedViews.get(account) ?? (compact() ? 'listWeek' : 'timeGridWeek'),
                headerToolbar: false,
                footerToolbar: false,
                loading: (isLoading) => { this.calendarLoading = isLoading; },
                nowIndicator: true,
                // Día y semana: cuartos de hora con altura legible, abriendo una hora antes de ahora.
                slotDuration: '00:15:00',
                slotLabelInterval: '01:00',
                scrollTime: `${String(Math.max(new Date().getHours() - 1, 0)).padStart(2, '0')}:00:00`,
                scrollTimeReset: false,
                slotEventOverlap: false,
                eventMinHeight: 26,
                // La rejilla horaria necesita altura fija para desplazarse por dentro; mes y lista crecen con su contenido.
                datesSet: ({ view }) => {
                    this.closeActions(false);
                    const date = this.calendar?.getDate() || view.currentStart;
                    this.viewType = view.type;
                    this.mobileView = { timeGridDay: 'Día', timeGridWeek: 'Semana', dayGridMonth: 'Mes', listWeek: 'Agenda' }[view.type];
                    const format = new Intl.DateTimeFormat('es', view.type === 'dayGridMonth' ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short', year: 'numeric' });
                    if (view.type === 'timeGridWeek' || view.type === 'listWeek') {
                        const end = new Date(view.currentEnd);
                        end.setDate(end.getDate() - 1);
                        this.mobileDate = `${view.currentStart.getDate()}–${format.format(end)}`;
                    } else this.mobileDate = format.format(date);
                    this.fitCalendar();
                },
                editable: true,
                dayMaxEvents: 3,
                events: (info, success, failure) => {
                    const requestVersion = ++this.requestVersion;
                    const requested = Object.keys(this.layers).filter(key => this.layers[key]);
                    this.requestedLayers = requested;
                    wire.events(info.startStr, info.endStr, requested).then(events => {
                        success(events.map(event => ({ ...event, display: this.layers[event.extendedProps.type] ? 'auto' : 'none' })));
                    }, error => {
                        if (this.requestVersion === requestVersion) this.requestedLayers = [];
                        failure(error);
                    });
                },
                dayHeaderDidMount: ({ el, view }) => {
                    if (view.type !== 'listWeek') return;
                    el.closest('table')?.setAttribute('role', 'table');
                    el.parentElement?.setAttribute('role', 'rowgroup');
                    el.setAttribute('role', 'row');
                    el.cells[0].setAttribute('role', 'columnheader');
                },
                eventClick: (info) => this.clickEvent(info),
                // El clic en otro evento no debe contar como «clic afuera» del popover abierto.
                eventDidMount: (info) => { this.mountActionTrigger(info); this.mountAgendaRow(info); if (info.event.extendedProps.locked) { const lock = document.createElement('i'); lock.className = 'bi bi-lock-fill md-calendar-lock'; lock.setAttribute('role', 'img'); lock.setAttribute('aria-label', 'Horario fijo'); lock.title = 'Horario fijo'; (info.el.querySelector('.fc-event-time, .fc-list-event-time') ?? info.el.querySelector('.fc-event-title') ?? info.el).prepend(lock); } info.el.dataset.popoverTrigger = ''; if (info.event.extendedProps.type === 'tasks') { const color = info.event.extendedProps.categoryColor; if (['gray', 'blue', 'indigo', 'violet', 'pink', 'red', 'orange', 'yellow', 'green', 'teal'].includes(color)) info.el.classList.add(`md-catalog-chip--${color}`); } if (info.event.extendedProps.type === 'meals') { info.el.title = `${info.event.extendedProps.timeLabel} · ${info.event.title}`; info.el.setAttribute('aria-label', info.el.title); } },
                dateClick: (info) => this.$dispatch('task-editor', {
                    action: 'openForm',
                    prefill: info.allDay
                        ? { startDate: info.dateStr }
                        : { startDate: info.dateStr.slice(0, 10), startTime: info.dateStr.slice(11, 16) },
                }),
                eventDrop: info => this.persistEvent(info),
                eventResize: info => this.persistEvent(info),
            });
            this.calendar.render();
            this.resize = () => {
                cancelAnimationFrame(this.resizeFrame);
                this.resizeFrame = requestAnimationFrame(() => this.fitCalendar());
            };
            window.addEventListener('resize', this.resize);
            window.visualViewport?.addEventListener('resize', this.resize);
            this.resize();

            this.refresh = () => { this.closeActions(); this.calendar?.refetchEvents(); };
            window.addEventListener('task-calendar-refresh', this.refresh);
            this.goto = (event) => this.calendar?.gotoDate(event.detail.date);
            window.addEventListener('task-calendar-goto', this.goto);
        },
        destroy() {
            this.closeActions(false);
            cancelAnimationFrame(this.nowFrame);
            cancelAnimationFrame(this.resizeFrame);
            window.removeEventListener('resize', this.resize);
            window.visualViewport?.removeEventListener('resize', this.resize);
            window.removeEventListener('task-calendar-refresh', this.refresh);
            window.removeEventListener('task-calendar-goto', this.goto);
            this.calendar?.destroy();
            this.calendar = null;
        },
    }));
}
