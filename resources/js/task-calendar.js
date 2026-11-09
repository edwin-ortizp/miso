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
                eventDidMount: (info) => { info.el.dataset.popoverTrigger = ''; if (info.event.extendedProps.type === 'tasks') { const color = info.event.extendedProps.categoryColor; if (['gray', 'blue', 'indigo', 'violet', 'pink', 'red', 'orange', 'yellow', 'green', 'teal'].includes(color)) info.el.classList.add(`md-catalog-chip--${color}`); } if (info.event.extendedProps.type === 'meals') { info.el.title = `${info.event.extendedProps.timeLabel} · ${info.event.title}`; info.el.setAttribute('aria-label', info.el.title); } },
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

            this.refresh = () => this.calendar?.refetchEvents();
            window.addEventListener('task-calendar-refresh', this.refresh);
            this.goto = (event) => this.calendar?.gotoDate(event.detail.date);
            window.addEventListener('task-calendar-goto', this.goto);
        },
        destroy() {
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
