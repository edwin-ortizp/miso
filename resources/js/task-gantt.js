let ganttPromise;

// Frappe Gantt se carga bajo demanda: solo la pestaña Gantt de Tareas lo usa.
// Su CSS va en app.css: un chunk CSS diferido se pediría a /build, que no se despliega.
function loadGantt() {
    ganttPromise ??= import('frappe-gantt').then(module => module.default);

    return ganttPromise;
}

const ymd = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

export function registerTaskGantt(Alpine) {
    Alpine.data('taskGantt', (wire) => ({
        gantt: null,
        refresh: null,
        redraw: null,
        mode: 'Day',
        empty: false,
        setMode(mode) {
            this.mode = mode;
            this.gantt?.change_view_mode(mode, true);
        },
        today() {
            this.gantt?.scroll_current();
        },
        async load() {
            const tasks = await wire.tasks();
            this.empty = tasks.length === 0;
            return tasks;
        },
        async init() {
            const [Gantt, tasks] = await Promise.all([loadGantt(), this.load()]);
            this.gantt = new Gantt(this.$refs.gantt, tasks, {
                language: 'es',
                view_mode: this.mode,
                view_mode_select: false,
                today_button: false,
                // El editor compartido reemplaza la ventana emergente de la librería.
                popup: false,
                readonly_progress: true,
                container_height: Math.max(360, innerHeight - this.$refs.gantt.getBoundingClientRect().top - 120),
                on_click: (task) => this.$dispatch('task-editor', { action: 'openForm', id: task.id }),
                on_date_change: async (task, start, end) => {
                    try {
                        await wire.moveTask(task.id, ymd(start), ymd(end));
                    } catch {
                        this.refresh();
                    }
                },
            });

            this.refresh = async () => {
                const next = await this.load();
                this.gantt?.refresh(next);
                wire.$refresh();
            };
            // Al cambiar un filtro el componente ya se volvió a renderizar: solo se redibujan las barras.
            this.redraw = async () => this.gantt?.refresh(await this.load());
            window.addEventListener('task-calendar-refresh', this.refresh);
            window.addEventListener('task-gantt-refresh', this.redraw);
        },
        destroy() {
            window.removeEventListener('task-calendar-refresh', this.refresh);
            window.removeEventListener('task-gantt-refresh', this.redraw);
            this.$refs.gantt?.replaceChildren();
            this.gantt = null;
        },
    }));
}
