const MODES = ['work', 'shortBreak', 'longBreak'];

function defaultDependencies() {
    return {
        now: () => Date.now(),
        storage: window.localStorage,
        setInterval: (callback, delay) => window.setInterval(callback, delay),
        clearInterval: (id) => window.clearInterval(id),
        uuid: () => window.crypto.randomUUID(),
        requestNotificationPermission: () => {
            if ('Notification' in window && Notification.permission === 'default') {
                return Notification.requestPermission();
            }

            return Promise.resolve();
        },
        notify: (title, body) => {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, { body });
            }
        },
        document: window.document,
    };
}

export function createPomodoroTimer(options, providedDependencies = {}) {
    const dependencies = typeof window === 'undefined'
        ? providedDependencies
        : { ...defaultDependencies(), ...providedDependencies };
    const now = dependencies.now;
    const storage = dependencies.storage;
    const setTimerInterval = dependencies.setInterval;
    const clearTimerInterval = dependencies.clearInterval;
    const createUuid = dependencies.uuid;
    const documentObject = dependencies.document;
    const durations = {
        work: Number(options.workDuration) * 60,
        shortBreak: Number(options.shortBreak) * 60,
        longBreak: Number(options.longBreak) * 60,
    };

    return {
        mode: 'work',
        isRunning: false,
        isCompleting: false,
        pendingCompletion: false,
        timeLeft: durations.work,
        totalTime: durations.work,
        interval: null,
        startedAt: null,
        endsAt: null,
        cycleId: null,
        description: '',
        errorMessage: '',
        visibilityHandler: null,

        get displayTime() {
            return `${String(Math.floor(this.timeLeft / 60)).padStart(2, '0')}:${String(this.timeLeft % 60).padStart(2, '0')}`;
        },

        get progress() {
            return this.totalTime > 0 ? (this.totalTime - this.timeLeft) / this.totalTime : 0;
        },

        setMode(mode) {
            if (!MODES.includes(mode) || this.isCompleting || this.pendingCompletion) return;

            this.clearInterval();
            this.mode = mode;
            this.isRunning = false;
            this.totalTime = durations[mode];
            this.timeLeft = this.totalTime;
            this.startedAt = null;
            this.endsAt = null;
            this.cycleId = null;
            this.errorMessage = '';
            this.persist();
        },

        toggle() {
            if (this.isCompleting) return;
            if (this.pendingCompletion) {
                void this.complete();
                return;
            }

            this.isRunning ? this.pause() : this.start();
        },

        start() {
            if (this.isRunning || this.isCompleting) return;

            if (this.timeLeft <= 0) this.timeLeft = this.totalTime;

            const currentTime = now();
            const elapsed = this.totalTime - this.timeLeft;
            this.startedAt = currentTime - (elapsed * 1000);
            this.endsAt = currentTime + (this.timeLeft * 1000);
            this.cycleId ??= createUuid();
            this.isRunning = true;
            this.errorMessage = '';
            this.persist();
            this.startInterval();
            void dependencies.requestNotificationPermission?.();
        },

        pause() {
            if (!this.isRunning) return;

            this.syncTime();
            this.isRunning = false;
            this.endsAt = null;
            this.clearInterval();
            this.persist();
        },

        reset() {
            if (this.isCompleting || this.pendingCompletion) return;

            this.clearInterval();
            this.isRunning = false;
            this.timeLeft = this.totalTime;
            this.startedAt = null;
            this.endsAt = null;
            this.cycleId = null;
            this.errorMessage = '';
            this.persist();
        },

        syncTime() {
            if (!this.isRunning || !this.endsAt) return;

            this.timeLeft = Math.max(0, Math.ceil((this.endsAt - now()) / 1000));

            if (this.timeLeft === 0) void this.complete();
        },

        startInterval() {
            this.clearInterval();
            this.interval = setTimerInterval(() => this.syncTime(), 1000);
        },

        clearInterval() {
            if (this.interval !== null) {
                clearTimerInterval(this.interval);
                this.interval = null;
            }
        },

        async finishEarly() {
            if (this.isCompleting || this.pendingCompletion || this.mode !== 'work') return;
            if (this.isRunning) this.syncTime();
            if (this.pendingCompletion || this.isCompleting) return;
            const elapsed = this.totalTime - this.timeLeft;
            if (elapsed < 60) {
                this.errorMessage = 'Registra al menos un minuto de foco antes de finalizar.';
                return;
            }
            this.endsAt = now();
            this.startedAt = this.endsAt - elapsed * 1000;
            await this.complete();
        },

        async complete() {
            if (this.isCompleting) return;

            const completedMode = this.mode;
            const scheduledEnd = this.endsAt ?? (this.startedAt ? this.startedAt + (this.totalTime * 1000) : null);
            const cycleId = this.cycleId;

            this.clearInterval();
            this.isRunning = false;
            this.timeLeft = 0;

            if (completedMode === 'work' && this.startedAt && scheduledEnd && cycleId) {
                this.isCompleting = true;
                this.pendingCompletion = true;
                this.endsAt = scheduledEnd;
                this.persist();

                try {
                    await options.saveSession(
                        Math.floor(this.startedAt / 1000),
                        Math.floor(scheduledEnd / 1000),
                        this.description,
                        cycleId,
                    );
                } catch (error) {
                    this.errorMessage = 'No se pudo guardar la sesión. Pulsa de nuevo para reintentar.';
                    this.isCompleting = false;
                    this.persist();
                    return;
                }

                dependencies.notify?.('Pomodoro completado', 'Tiempo de descanso.');
                this.description = '';
            } else if (completedMode !== 'work') {
                dependencies.notify?.('Descanso terminado', 'De vuelta al trabajo.');
            }

            this.isCompleting = false;
            this.pendingCompletion = false;
            this.timeLeft = this.totalTime;
            this.startedAt = null;
            this.endsAt = null;
            this.cycleId = null;
            this.errorMessage = '';
            this.persist();
        },

        persist() {
            if (!storage || !options.storageKey) return;

            storage.setItem(options.storageKey, JSON.stringify({
                version: 1,
                mode: this.mode,
                isRunning: this.isRunning,
                pendingCompletion: this.pendingCompletion,
                timeLeft: this.timeLeft,
                totalTime: this.totalTime,
                startedAt: this.startedAt,
                endsAt: this.endsAt,
                cycleId: this.cycleId,
                description: this.description,
            }));
        },

        restore() {
            if (!storage || !options.storageKey) return;

            let saved;

            try {
                saved = JSON.parse(storage.getItem(options.storageKey));
            } catch (error) {
                storage.removeItem(options.storageKey);
                return;
            }

            if (!saved || saved.version !== 1 || !MODES.includes(saved.mode)) return;

            this.mode = saved.mode;
            this.totalTime = Number(saved.totalTime) > 0 ? Number(saved.totalTime) : durations[this.mode];
            this.timeLeft = Math.min(Math.max(Number(saved.timeLeft) || 0, 0), this.totalTime);
            this.startedAt = Number(saved.startedAt) || null;
            this.endsAt = Number(saved.endsAt) || null;
            this.cycleId = typeof saved.cycleId === 'string' ? saved.cycleId : null;
            this.description = typeof saved.description === 'string' ? saved.description : '';
            this.isRunning = Boolean(saved.isRunning && this.endsAt && this.startedAt && this.cycleId);
            this.pendingCompletion = Boolean(saved.pendingCompletion && this.startedAt && this.endsAt && this.cycleId);

            if (this.pendingCompletion) {
                void this.complete();
            } else if (this.isRunning) {
                this.syncTime();
                if (this.isRunning) this.startInterval();
            }
        },

        init() {
            this.restore();
            this.$watch?.('description', () => this.persist());
            this.visibilityHandler = () => this.syncTime();
            documentObject?.addEventListener('visibilitychange', this.visibilityHandler);
        },

        destroy() {
            this.clearInterval();
            if (this.visibilityHandler) {
                documentObject?.removeEventListener('visibilitychange', this.visibilityHandler);
            }
        },
    };
}

export function registerPomodoroTimer(Alpine) {
    Alpine.data('pomodoroTimer', (options) => createPomodoroTimer(options));
}
