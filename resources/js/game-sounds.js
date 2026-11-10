/**
 * Sonidos sutiles del juego (CHG-044), sintetizados con Web Audio: sin archivos
 * ni derechos de terceros. Volumen bajo fijo. Solo suenan después de una
 * interacción del usuario en la página (política de autoplay); si el navegador
 * lo bloquea, la página está oculta o el ajuste está apagado, no pasa nada.
 */
const MAX_GAIN = 0.08;

// Notas (Hz), duración de cada una (s) y separación entre ellas (s).
const catalog = {
    ball: { notes: [784, 1046.5], length: 0.16, gap: 0.09, type: 'sine' },
    open: { notes: [523.25, 1568], length: 0.12, gap: 0.07, type: 'triangle' },
    level: { notes: [659.25, 783.99, 1046.5], length: 0.14, gap: 0.08, type: 'sine' },
    mission: { notes: [523.25, 659.25, 783.99], length: 0.35, gap: 0, type: 'sine' },
};

let context = null;
let unlocked = false;

function unlock() {
    if (unlocked) return;
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;
    try {
        context ??= new AudioContextClass();
        context.resume?.().catch(() => {});
        unlocked = true;
    } catch {
        context = null;
    }
}

export function soundsEnabled() {
    return document.querySelector('.game-notifier')?.dataset.gameSound === '1';
}

/** Reproduce un sonido del catálogo. `force` ignora el ajuste (botón "Probar sonido"). */
export function playGameSound(name, { force = false } = {}) {
    const sound = catalog[name];
    if (!sound || !unlocked || !context || document.hidden) return false;
    if (!force && !soundsEnabled()) return false;

    const start = context.currentTime + 0.01;
    sound.notes.forEach((frequency, index) => {
        const at = start + index * sound.gap;
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = sound.type;
        oscillator.frequency.setValueAtTime(frequency, at);
        gain.gain.setValueAtTime(0.0001, at);
        gain.gain.exponentialRampToValueAtTime(MAX_GAIN, at + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, at + sound.length);
        oscillator.connect(gain).connect(context.destination);
        oscillator.start(at);
        oscillator.stop(at + sound.length + 0.02);
    });

    return true;
}

export function registerGameSounds() {
    ['pointerdown', 'keydown'].forEach((type) => window.addEventListener(type, unlock, { passive: true }));
    window.addEventListener('game-sound', (event) => playGameSound(event.detail?.name));
    window.playGameSound = playGameSound;
}
