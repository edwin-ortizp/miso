// Compañero de foco (CHG-059): el guardián de Pomodoro cambia de frase solo al
// cambiar de etapa del ciclo. Lee el estado del temporizador padre vía sync().

const FINAL_SECONDS = 300;

/** Etapa del ciclo a partir del estado del temporizador (sin "done", que es un evento). */
export function focusStage(timer) {
    if (timer.isCompleting || timer.pendingCompletion) return timer.mode === 'work' ? 'done' : 'break';
    if (timer.mode !== 'work') return 'break';
    if (!timer.startedAt) return 'ready';
    if (!timer.isRunning) return 'paused';
    const total = Number(timer.totalTime) || 0;
    const left = Number(timer.timeLeft) || 0;
    const progress = total > 0 ? (total - left) / total : 0;
    if (progress >= 0.8 || (total > FINAL_SECONDS && left <= FINAL_SECONDS)) return 'final';
    if (progress >= 0.5) return 'half';
    return 'start';
}

export function createFocusCompanion({ lines = {}, cry = '', storageKey = 'life-tracker:focus-companion-hidden', storage = null, random = Math.random } = {}) {
    const store = storage ?? (typeof window !== 'undefined' ? window.localStorage : null);
    const pick = (stage, except) => {
        const options = lines[stage] ?? [];
        if (!options.length) return '';
        const pool = options.length > 1 ? options.filter((line) => line !== except) : options;
        return pool[Math.floor(random() * pool.length)] ?? options[0];
    };

    return {
        stage: '',
        line: '',
        cry: '',
        celebrating: false,
        cheer: false,
        hidden: store?.getItem(storageKey) === '1',

        /** Llamado por x-effect con el estado del temporizador; solo cambia la frase si cambia la etapa. */
        sync(timer) {
            let stage = focusStage(timer);
            if (stage === 'done') this.celebrating = true;
            else if (timer.startedAt || timer.mode !== 'work') this.celebrating = false;
            if (this.celebrating && stage === 'ready') stage = 'done';
            if (stage === this.stage) return;
            const first = this.stage === '';
            this.stage = stage;
            this.line = pick(stage, this.line);
            // Grito del Pokémon antes de la frase; al terminar, más emocionado.
            this.cry = cry && stage === 'done' ? `¡${cry}!` : cry;
            this.cheer = stage === 'done' && !first;
            if (this.cheer) setTimeout(() => { this.cheer = false; }, 1000);
        },

        toggleHidden() {
            this.hidden = !this.hidden;
            this.hidden ? store?.setItem(storageKey, '1') : store?.removeItem(storageKey);
        },
    };
}

export function registerFocusCompanion(Alpine) {
    Alpine.data('focusCompanion', (options) => createFocusCompanion(options));
}
