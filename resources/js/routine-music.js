/**
 * Música de fondo de la rutina de hábitos (CHG-060).
 * Store global: sobrevive al re-montaje de cada paso, así la música no se corta.
 * Pistas CC0 en public/audio/routine (ver LICENSES.md). La campanita se sintetiza
 * con Web Audio. Si el navegador bloquea el audio o falla un archivo, no pasa nada.
 */
const TRACK_NAMES = [
    'all-into-harmony',
    'another-august',
    'breathing',
    'budding-consciousness',
    'calm-piano',
    'calm-relax',
    'chill-lofi',
    'contemplation',
    'daydream',
    'deep-blue-piano',
    'dream-ambience',
    'dreamlike-state',
    'first-light',
    'happy-lofi',
    'lifewave',
    'lost-meadows',
    'meadow-thoughts',
    'morning-sky',
    'napping-cloud',
    'pan-flute-chords',
    'peaceful-theme',
    'piano-melody-soft',
    'piano-soft-20',
    'piano-soft-6',
    'soft-electric-piano',
    'starfield-romance',
    'sunset-plains',
    'synthwave-15k',
    'synthwave-4k',
    'waking-states',];
let TRACKS = TRACK_NAMES.map((n) => `/audio/routine/${n}.mp3`);
const VOLUME = 0.3;
const MUTE_KEY = 'miso.routine.music.muted';

function readMuted() {
    try { return localStorage.getItem(MUTE_KEY) === '1'; } catch { return false; }
}

export function registerRoutineMusic(Alpine) {
    let audio = null;
    let last = -1;
    let fadeTimer = null;
    let ctx = null;
    let retry = null;

    function fade(to, ms, done) {
        clearInterval(fadeTimer);
        if (!audio) return done?.();
        const from = audio.volume;
        const steps = Math.max(1, Math.round(ms / 50));
        let i = 0;
        fadeTimer = setInterval(() => {
            i++;
            if (audio) audio.volume = Math.min(1, Math.max(0, from + (to - from) * (i / steps)));
            if (i >= steps) { clearInterval(fadeTimer); done?.(); }
        }, 50);
    }

    // Bolsa barajada: suenan todas antes de repetir y nunca la misma dos veces seguidas.
    let bag = [];
    function pickTrack() {
        if (!bag.length) {
            bag = TRACKS.map((_, i) => i);
            for (let j = bag.length - 1; j > 0; j--) {
                const k = Math.floor(Math.random() * (j + 1));
                [bag[j], bag[k]] = [bag[k], bag[j]];
            }
            if (bag.length > 1 && bag[bag.length - 1] === last) [bag[0], bag[bag.length - 1]] = [bag[bag.length - 1], bag[0]];
        }
        last = bag.pop();
        return TRACKS[last];
    }

    function play(store) {
        if (!audio || store.muted || store.paused) return;
        audio.play().catch(() => {
            // Autoplay bloqueado: arrancar con la siguiente interacción.
            if (retry) return;
            retry = () => { retry = null; play(store); };
            window.addEventListener('pointerdown', retry, { once: true, capture: true });
            window.addEventListener('keydown', retry, { once: true, capture: true });
        });
    }

    function loadNext(store) {
        if (!audio) return;
        audio.src = pickTrack();
        play(store);
    }

    Alpine.store('routineMusic', {
        muted: readMuted(),
        paused: false,
        active: false,

        start(base) {
            if (base) TRACKS = TRACK_NAMES.map((n) => `${base.replace(/\/$/, '')}/${n}.mp3`);
            if (this.active) return;
            this.active = true;
            this.paused = false;
            audio = new Audio();
            audio.preload = 'auto';
            audio.volume = 0;
            audio.addEventListener('ended', () => loadNext(this));
            audio.addEventListener('error', () => {});
            loadNext(this);
            fade(VOLUME, 2000);
        },
        stop() {
            this.active = false;
            const a = audio;
            fade(0, 600, () => { a?.pause(); if (audio === a) audio = null; });
        },
        pause() { this.paused = true; audio?.pause(); },
        resume() { this.paused = false; play(this); },
        step() { this.paused = false; if (audio) { play(this); fade(VOLUME, 800); } },
        timeUp() {
            if (audio) fade(VOLUME / 2, 2000);
            this.chime();
        },
        toggle() {
            this.muted = !this.muted;
            try { localStorage.setItem(MUTE_KEY, this.muted ? '1' : '0'); } catch {}
            if (this.muted) audio?.pause(); else play(this);
        },
        chime() {
            try {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return;
                ctx ??= new AC();
                ctx.resume?.().catch(() => {});
                const t = ctx.currentTime;
                [[880, 0.12], [1760, 0.04]].forEach(([freq, peak]) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    gain.gain.setValueAtTime(0.0001, t);
                    gain.gain.exponentialRampToValueAtTime(peak, t + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t + 1.6);
                    osc.connect(gain).connect(ctx.destination);
                    osc.start(t);
                    osc.stop(t + 1.7);
                });
            } catch {}
        },
    });
}
