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

export function registerTaskCalendar(Alpine) {
    Alpine.data('taskCalendar', (wire) => ({
        calendar: null,
        refresh: null,
        quickOpen: false,
        selected: null,
        busy: false,
        // Ancla el popover junto al evento (en escritorio); en móvil el CSS lo convierte en hoja inferior.
        openQuick(event, el) {
            const rect = el.getBoundingClientRect();
            const width = 360;
            const left = rect.right + 8 + width <= innerWidth ? rect.right + 8 : Math.max(8, rect.left - width - 8);
            const top = Math.min(Math.max(8, rect.top), innerHeight - 260);
            this.$refs.anchor.style.setProperty('--quick-top', `${top}px`);
            this.$refs.anchor.style.setProperty('--quick-left', `${left}px`);
            this.selected = { id: event.id, title: event.title, ...event.extendedProps };
            this.quickOpen = true;
        },
        edit() {
            this.quickOpen = false;
            this.$dispatch('task-editor', { action: 'openForm', id: this.selected.id });
        },
        async toggleComplete() {
            this.busy = true;
            try {
                await wire.toggleComplete(this.selected.id);
                this.quickOpen = false;
            } finally {
                this.busy = false;
            }
        },
        async init() {
            const { Calendar, locale, plugins } = await loadFullCalendar();
            const persist = async (info) => {
                try {
                    await wire.moveTask(info.event.id, info.event.startStr, info.event.endStr || null, info.event.allDay);
                } catch {
                    info.revert();
                }
            };

            this.calendar = new Calendar(this.$refs.calendar, {
                plugins,
                locale,
                firstDay: 1,
                height: 'auto',
                initialView: compact() ? 'listWeek' : 'dayGridMonth',
                headerToolbar: compact()
                    ? { left: 'prev,next', center: 'title', right: 'today' }
                    : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
                footerToolbar: compact() ? { center: 'dayGridMonth,timeGridWeek,listWeek' } : false,
                nowIndicator: true,
                // Día y semana: cuartos de hora con altura legible, desde las 5 a. m., abriendo una hora antes de ahora.
                slotDuration: '00:15:00',
                slotLabelInterval: '01:00',
                slotMinTime: '05:00:00',
                scrollTime: `${String(Math.max(new Date().getHours() - 1, 5)).padStart(2, '0')}:00:00`,
                scrollTimeReset: false,
                slotEventOverlap: false,
                eventMinHeight: 26,
                // La rejilla horaria necesita altura fija para desplazarse por dentro; mes y lista crecen con su contenido.
                datesSet: ({ view }) => {
                    const height = view.type.startsWith('timeGrid')
                        ? Math.max(480, innerHeight - this.$refs.calendar.getBoundingClientRect().top - 24)
                        : 'auto';
                    if (this.calendar && this.calendar.getOption('height') !== height) this.calendar.setOption('height', height);
                },
                editable: true,
                dayMaxEvents: 3,
                events: (info, success, failure) => wire.events(info.startStr, info.endStr).then(success, failure),
                eventClick: (info) => this.openQuick(info.event, info.el),
                // El clic en otro evento no debe contar como «clic afuera» del popover abierto.
                eventDidMount: (info) => { info.el.dataset.popoverTrigger = ''; },
                dateClick: (info) => this.$dispatch('task-editor', {
                    action: 'openForm',
                    prefill: info.allDay
                        ? { startDate: info.dateStr }
                        : { startDate: info.dateStr.slice(0, 10), startTime: info.dateStr.slice(11, 16) },
                }),
                eventDrop: persist,
                eventResize: persist,
            });
            this.calendar.render();

            this.refresh = () => this.calendar?.refetchEvents();
            window.addEventListener('task-calendar-refresh', this.refresh);
        },
        destroy() {
            window.removeEventListener('task-calendar-refresh', this.refresh);
            this.calendar?.destroy();
            this.calendar = null;
        },
    }));
}
