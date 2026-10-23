export function zonedClock(date, timeZone) {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(date);
    const p = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
    return { clock: `${p.hour}:${p.minute}`, date: `${p.year}-${p.month}-${p.day}`, minute: Number(p.hour) * 60 + Number(p.minute) };
}

export function timelineGroups(items, start) {
    const visible = items.map(item => ({ ...item, minute: Number(item.time.slice(0, 2)) * 60 + Number(item.time.slice(3, 5)) }))
        .filter(item => item.minute >= start && item.minute <= start + 480).sort((a, b) => a.minute - b.minute);
    const groups = [];
    for (const item of visible) {
        const group = groups.at(-1);
        if (group && item.minute - group[0].minute < 45) group.push(item);
        else groups.push([item]);
    }
    return groups;
}

export const PANEL_REFRESH_INTERVAL = 5 * 60 * 1000;

export function createPanelRefresh({ request, now = () => Date.now() }) {
    return {
        refreshing: false, refreshError: '', lastSuccessAt: null, lastAttemptAt: null,
        updated(timestamp) {
            if (!timestamp) return;
            this.lastSuccessAt = now();
            this.refreshError = '';
        },
        due() {
            const reference = Math.max(this.lastSuccessAt ?? 0, this.lastAttemptAt ?? 0);
            return !this.refreshing && ((this.lastSuccessAt === null && this.lastAttemptAt === null) || now() - reference >= PANEL_REFRESH_INTERVAL);
        },
        async refresh() {
            if (this.refreshing) return;
            this.refreshing = true;
            this.lastAttemptAt = now();
            this.refreshError = '';
            try {
                await request();
                this.lastSuccessAt = now();
            } catch {
                this.refreshError = 'No se pudo actualizar';
            } finally {
                this.refreshing = false;
            }
        },
    };
}

export function registerWallPanel(Alpine) {
    Alpine.data('wallDay', rows => ({
        rows, limit: 5, observer: null,
        init() {
            this.observer = new ResizeObserver(() => {
                this.limit = Math.max(1, Math.floor((this.$el.clientHeight - 44) / 48));
            });
            this.observer.observe(this.$el);
        },
        destroy() { this.observer?.disconnect(); },
    }));
    Alpine.data('wallPanel', timeZone => ({
        clock: '', dateLabel: '', date: '', interval: null, visibilityHandler: null,
        refreshState: null, panelVisible: false, visibilityObserver: null, stopRefreshInterceptor: null,
        pendingDayRefresh: false,
        night: false, ambient: false, agendaOpen: false, habitsOpen: false, waterOpen: false,
        moodOpen: false, goalsOpen: false, shoppingOpen: false, avoidOpen: false, dayIndex: 0,
        layers: { task: true, plan: true, person: true, meal: true },
        init() {
            this.refreshState = createPanelRefresh({ request: () => this.$wire.refreshPanel() });
            this.refreshState.updated(this.$wire.lastUpdatedAt);
            this.$watch('$wire.lastUpdatedAt', timestamp => this.refreshState.updated(timestamp));
            this.stopRefreshInterceptor = this.$wire.$intercept('refreshPanel', ({ onError }) => {
                onError(({ preventDefault }) => preventDefault());
            });
            this.visibilityObserver = new IntersectionObserver(entries => {
                this.panelVisible = entries[0]?.isIntersecting ?? false;
                this.checkRefresh();
            });
            this.visibilityObserver.observe(this.$el);
            try { this.night = localStorage.getItem('miso:panel:night') === '1'; } catch {}
            document.body.toggleAttribute('data-panel-night', this.night);
            this.tick();
            this.interval = setInterval(() => this.tick(), 1000);
            this.visibilityHandler = () => { if (!document.hidden) this.tick(); };
            document.addEventListener('visibilitychange', this.visibilityHandler);
            this.$watch('ambient', value => {
                this.$el.querySelector('main').inert = value;
                if (value) this.$nextTick(() => this.$el.querySelector('.wall-ambient button')?.focus());
            });
        },
        tick() {
            const now = new Date();
            const current = zonedClock(now, timeZone);
            if (this.date && this.date !== current.date) this.pendingDayRefresh = true;
            this.date = current.date;
            this.clock = current.clock;
            this.dateLabel = new Intl.DateTimeFormat('es-CO', { timeZone, weekday: 'long', day: 'numeric', month: 'long' }).format(now);
            this.checkRefresh();
        },
        get lastUpdateLabel() {
            return this.$wire.lastUpdatedAt ? zonedClock(new Date(this.$wire.lastUpdatedAt), timeZone).clock : '—';
        },
        refreshData() { return this.refreshState.refresh(); },
        checkRefresh() {
            if (document.hidden || !this.panelVisible || this.refreshState.refreshing) return;
            if (this.pendingDayRefresh || this.refreshState.due()) {
                this.pendingDayRefresh = false;
                void this.refreshData();
            }
        },
        toggleNight() {
            this.night = !this.night;
            document.body.toggleAttribute('data-panel-night', this.night);
            try { localStorage.setItem('miso:panel:night', this.night ? '1' : '0'); } catch {}
        },
        destroy() {
            clearInterval(this.interval);
            document.removeEventListener('visibilitychange', this.visibilityHandler);
            this.visibilityObserver?.disconnect();
            this.stopRefreshInterceptor?.();
            document.body.removeAttribute('data-panel-night');
        },
    }));
    Alpine.data('wallTimeline', (items, timeZone) => ({
        items, start: Math.min(960, Math.max(0, zonedClock(new Date(), timeZone).minute - 120)),
        cluster: [], clusterOpen: false, interval: null, nowMinute: 0,
        get groups() { return timelineGroups(this.items, this.start); },
        shift(amount) { this.start = Math.min(960, Math.max(0, this.start + amount)); },
        formatMinute(minute) { return `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`; },
        init() { this.nowMinute = zonedClock(new Date(), timeZone).minute; this.start = Math.floor(this.start / 60) * 60; this.interval = setInterval(() => { this.nowMinute = zonedClock(new Date(), timeZone).minute; }, 1000); },
        destroy() { clearInterval(this.interval); },
    }));
}
