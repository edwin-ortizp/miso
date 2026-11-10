import { playGameSound } from './game-sounds.js';

export function createGameReveal({ reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches, schedule = setTimeout, cancel = clearTimeout } = {}) {
    let timers = [];
    return {
        phase: 'idle',
        revealOpen: false,
        ready: false,
        cleanup() { timers.forEach(cancel); timers = []; },
        begin() {
            this.cleanup();
            this.ready = false;
            // CHG-044: el sonido acompaña la apertura, también con movimiento reducido.
            playGameSound('open');
            if (reducedMotion()) { this.finish(); return; }
            this.phase = 'shake';
            timers.push(schedule(() => { this.phase = 'open'; }, 850));
            timers.push(schedule(() => { this.finish(); }, 1850));
        },
        finish() { this.cleanup(); this.phase = 'revealed'; this.ready = true; },
        destroy() { this.cleanup(); },
    };
}

export function registerGameReveal(Alpine) {
    Alpine.data('gameReveal', (wire) => ({
        ...createGameReveal(),
        revealOpen: wire.entangle('showReveal').live,
        revealKey: wire.entangle('revealKey').live,
        init() {
            if (this.revealOpen) this.begin();
            this.$watch('revealOpen', (open) => {
                if (open) this.begin();
                else { this.cleanup(); this.ready = false; this.phase = 'idle'; }
            });
            this.$watch('revealKey', () => { if (this.revealOpen) this.begin(); });
        },
    }));
}
