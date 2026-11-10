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
const layerStorageKey = 'miso.task-calendar.layers.v1';
const defaultLayers = () => ({ tasks: true, plans: true, birthdays: true, meals: true });

export function readCalendarLayers() {
    try {
        const value = JSON.parse(localStorage.getItem(layerStorageKey));
        if (value && ['tasks', 'plans', 'birthdays'].every(key => typeof value[key] === 'boolean')) {
            return Object.fromEntries(Object.keys(defaultLayers()).map(key => [key, typeof value[key] === 'boolean' ? value[key] : true]));
        }
    } catch { /* El calendario funciona también sin almacenamiento local. */ }
    return defaultLayers();
}

export function registerTaskCalendar(Alpine) {
    Alpine.data('taskCalendar', (wire) => ({
        calendar: null,
        actionEvent: null,
        actionTrigger: null,
        actionBusy: false,
        actionError: '',
        menuLeft: 0,
        menuTop: 0,
        closeActions(restore = true) {
            this.actionEvent = null;
            this.actionTrigger?.setAttribute('aria-expanded', 'false');
            if (restore && this.actionTrigger?.isConnected) this.actionTrigger.focus();
            this.actionTrigger = null;
        },
        openActions(event, trigger) {
            this.closeActions(false);
            this.actionEvent = { id: event.id, title: event.title, ...event.extendedProps };
            this.actionTrigger = trigger;
            this.actionError = '';
            trigger.setAttribute('aria-expanded', 'true');
            this.$nextTick(() => {
                const menu = this.$refs.actionMenu;
                const rect = trigger.getBoundingClientRect();
                this.menuLeft = Math.max(8, Math.min(rect.right - menu.offsetWidth, innerWidth - menu.offsetWidth - 8));
                this.menuTop = Math.max(8, Math.min(rect.bottom + 4, innerHeight - menu.offsetHeight - 8));
                menu.style.left = this.menuLeft + 'px';
                menu.style.top = this.menuTop + 'px';
                menu.querySelector('[role="menuitem"]')?.focus();
            });
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
        layers: readCalendarLayers(),
        draftLayers: defaultLayers(),
        filtersOpen: false,
        get hasLayerFilter() { return Object.values(this.layers).some(value => !value); },
        openFilters() {
            this.draftLayers = { ...this.layers };
            this.filtersOpen = true;
        },
        applyLayers() {
            this.layers = { ...this.draftLayers };
            try { localStorage.setItem(layerStorageKey, JSON.stringify(this.layers)); } catch { /* Solo memoria. */ }
            this.calendar?.batchRendering(() => {
                for (const event of this.calendar.getEvents()) event.setProp('display', this.layers[event.extendedProps.type] ? 'auto' : 'none');
            });
            this.filtersOpen = false;
            this.fitCalendar();
            this.$nextTick(() => this.$refs.filterTrigger?.focus());
        },
        clearLayerFilter() {
            this.draftLayers = defaultLayers();
            this.applyLayers();
        },
        async persistEvent(info) {
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
            else if (info.event.extendedProps.detailUrl) {
                window.location.assign(info.event.extendedProps.detailUrl);
            }
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
                initialView: compact() ? 'timeGridDay' : 'timeGridWeek',
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
                events: (info, success, failure) => wire.events(info.startStr, info.endStr).then(events => {
                    success(events.map(event => ({ ...event, display: this.layers[event.extendedProps.type] ? 'auto' : 'none' })));
                }, failure),
                eventClick: (info) => this.clickEvent(info),
                // El clic en otro evento no debe contar como «clic afuera» del popover abierto.
                eventDidMount: (info) => { this.mountActionTrigger(info); info.el.dataset.popoverTrigger = ''; if (info.event.extendedProps.type === 'tasks') { const color = info.event.extendedProps.categoryColor; if (['gray', 'blue', 'indigo', 'violet', 'pink', 'red', 'orange', 'yellow', 'green', 'teal'].includes(color)) info.el.classList.add(`md-catalog-chip--${color}`); } if (info.event.extendedProps.type === 'meals') { info.el.title = `${info.event.extendedProps.timeLabel} · ${info.event.title}`; info.el.setAttribute('aria-label', info.el.title); } },
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
