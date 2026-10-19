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
        async init() {
            const { Calendar, locale, plugins } = await loadFullCalendar();
            const persist = async (info) => {
                try {
                    await wire.moveTask(info.event.id, info.event.startStr, info.event.endStr || null, info.event.allDay);
                } catch {
                    info.revert();
                }
            };

            this.calendar = new Calendar(this.$el, {
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
                editable: true,
                dayMaxEvents: 3,
                events: (info, success, failure) => wire.events(info.startStr, info.endStr).then(success, failure),
                eventClick: (info) => this.$dispatch('task-editor', { action: 'openForm', id: info.event.id }),
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
