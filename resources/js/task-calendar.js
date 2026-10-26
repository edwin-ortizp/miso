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
        toggleLayer(layer) {
            if (!Object.hasOwn(this.layers, layer)) return;
            this.layers[layer] = !this.layers[layer];
            try { localStorage.setItem(layerStorageKey, JSON.stringify(this.layers)); } catch { /* Preferencia solo en memoria. */ }
            this.quickOpen = false;
            this.statusOpen = false;
            this.calendar?.batchRendering(() => {
                for (const event of this.calendar.getEvents()) {
                    event.setProp('display', this.layers[event.extendedProps.type] ? 'auto' : 'none');
                }
            });
            this.fitCalendar();
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
            if (info.event.extendedProps.type === 'tasks') this.openQuick(info.event, info.el);
            else if (info.event.extendedProps.detailUrl) {
                this.quickOpen = false;
                window.location.assign(info.event.extendedProps.detailUrl);
            }
        },
        calendarLoading: false,
        mobileDate: '',
        mobileView: 'Día',
        refresh: null,
        goto: null,
        quickOpen: false,
        selected: null,
        latestProps: {},
        busy: false,
        statusOpen: false,
        statusError: '',
        closeStatusMenu() {
            this.statusOpen = false;
            this.$refs.statusTrigger.focus();
        },
        async openStatusMenu() {
            this.statusOpen = !this.statusOpen;
            if (!this.statusOpen) return;
            await this.$nextTick();
            const menu = this.$refs.statusMenu;
            const rect = this.$refs.statusTrigger.getBoundingClientRect();
            menu.style.right = `${Math.max(8, innerWidth - rect.right)}px`;
            menu.style.bottom = `${innerHeight - rect.top + 4}px`;
            menu.style.maxHeight = `${Math.max(44, rect.top - 12)}px`;
            menu.querySelector('button:not([disabled])')?.focus();
        },
        moveStatusFocus(event, direction) {
            const items = [...this.$refs.statusMenu.querySelectorAll('button:not([disabled])')];
            const index = items.indexOf(event.target);
            items[(index + direction + items.length) % items.length]?.focus();
        },
        // Ancla el popover junto al evento (en escritorio); en móvil el CSS lo convierte en hoja inferior.
        openQuick(event, el) {
            if (this.busy) return;
            const rect = el.getBoundingClientRect();
            const width = 360;
            const left = rect.right + 8 + width <= innerWidth ? rect.right + 8 : Math.max(8, rect.left - width - 8);
            const top = Math.max(8, Math.min(rect.top, innerHeight - 360));
            this.$refs.anchor.style.setProperty('--quick-top', `${top}px`);
            this.$refs.anchor.style.setProperty('--quick-left', `${left}px`);
            this.statusOpen = false;
            this.statusError = '';
            this.selected = { id: event.id, title: event.title, ...(this.latestProps[event.id] ?? event.extendedProps) };
            this.quickOpen = true;
        },
        edit() {
            this.quickOpen = false;
            this.$dispatch('task-editor', { action: 'openForm', id: this.selected.id });
        },
        async changeStatus(statusId) {
            if (this.busy || !this.selected || this.selected.status?.id === statusId) return;
            this.busy = true;
            this.statusError = '';
            this.statusOpen = false;
            try {
                const result = await wire.changeStatus(this.selected.id, statusId);
                if (result) this.latestProps[this.selected.id] = { ...this.selected, ...result };
                const event = this.calendar?.getEventById(this.selected.id);
                if (result && event) {
                    event.setExtendedProp('status', result.status);
                    event.setExtendedProp('completed', result.completed);
                }
                this.quickOpen = false;
            } catch {
                this.statusError = 'No se pudo cambiar el estado. Inténtalo de nuevo.';
            } finally {
                this.busy = false;
                if (this.statusError) {
                    await this.$nextTick();
                    this.$refs.statusTrigger.focus();
                }
            }
        },
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
            const { Calendar, locale, plugins } = await loadFullCalendar();

            this.calendar = new Calendar(this.$refs.calendar, {
                plugins,
                locale,
                firstDay: 1,
                height: 'auto',
                initialView: compact() ? 'timeGridDay' : 'timeGridWeek',
                headerToolbar: { left: 'prev,next today now refresh', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
                footerToolbar: false,
                // Recarga eventos y la lista «Sin fecha» sin recargar la página, como «Actualizar tabla» en las vistas de gestión.
                customButtons: {
                    now: { text: 'Ahora', hint: 'Ir a hoy y a la hora actual', click: () => this.goToNow() },
                    refresh: {
                        icon: 'arrow-clockwise',
                        hint: 'Actualizar calendario',
                        click: () => { this.calendar.refetchEvents(); wire.$refresh(); },
                    },
                },
                loading: (isLoading) => {
                    this.calendarLoading = isLoading;
                    const button = this.$refs.calendar.querySelector('.fc-refresh-button');
                    if (button) button.disabled = isLoading;
                },
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
                    this.mobileView = { timeGridDay: 'Día', timeGridWeek: 'Semana', dayGridMonth: 'Mes', listWeek: 'Agenda' }[view.type];
                    const format = new Intl.DateTimeFormat('es', view.type === 'dayGridMonth' ? { month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' });
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
                    this.latestProps = Object.fromEntries(events.map(event => [event.id, event.extendedProps]));
                    success(events.map(event => ({ ...event, display: this.layers[event.extendedProps.type] ? 'auto' : 'none' })));
                }, failure),
                eventClick: (info) => this.clickEvent(info),
                // El clic en otro evento no debe contar como «clic afuera» del popover abierto.
                eventDidMount: (info) => { info.el.dataset.popoverTrigger = ''; if (info.event.extendedProps.type === 'meals') { info.el.title = `${info.event.extendedProps.timeLabel} · ${info.event.title}`; info.el.setAttribute('aria-label', info.el.title); } },
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
