/**
 * Celebraciones del juego (CHG-034). Catálogo de animaciones ligeras y originales:
 * crean una capa fija decorativa (`aria-hidden`, sin clics), animan solo
 * transform/opacity y se eliminan solas. Respetan "reducir movimiento".
 *
 * Para agregar una: añade una función al catálogo y su clave en config/game.php
 * (`celebration_catalog`).
 */

const DURATION = 2600;
const MAX_PARTICLES = 40;

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const rand = (min, max) => min + Math.random() * (max - min);

function layer(name) {
    const el = document.createElement('div');
    el.className = `game-celebration game-celebration--${name}`;
    el.setAttribute('aria-hidden', 'true');
    document.body.appendChild(el);
    window.setTimeout(() => el.remove(), DURATION + 900);

    return el;
}

/** Crea `count` partículas con variables CSS por partícula (posición, retraso, deriva…). */
function particles(el, count, build) {
    for (let index = 0; index < Math.min(count, MAX_PARTICLES); index++) {
        const particle = document.createElement('span');
        particle.className = 'game-celebration__particle';
        const { text = '', vars = {}, className = '' } = build(index);
        if (className) particle.classList.add(...className.split(' '));
        particle.textContent = text;
        Object.entries(vars).forEach(([key, value]) => particle.style.setProperty(key, value));
        el.appendChild(particle);
    }
}

const TYPE_COLORS = ['fuego', 'agua', 'planta', 'electrico', 'psiquico', 'hada', 'dragon', 'hielo'];

export const catalog = {
    confetti(el) {
        particles(el, 36, () => ({
            className: `game-type--${TYPE_COLORS[Math.floor(Math.random() * TYPE_COLORS.length)]}`,
            vars: { '--x': `${rand(0, 100)}vw`, '--drift': `${rand(-18, 18)}vw`, '--spin': `${rand(-540, 540)}deg`, '--delay': `${rand(0, 500)}ms`, '--dur': `${rand(1600, 2400)}ms` },
        }));
    },
    cats(el) {
        const cats = ['🐈', '🐈‍⬛', '🐱', '😺', '🐈'];
        particles(el, 6, (index) => ({
            text: cats[index % cats.length],
            vars: { '--y': `${rand(55, 85)}vh`, '--delay': `${index * 260}ms`, '--dur': `${rand(1900, 2500)}ms`, '--size': `${rand(1.8, 2.8)}rem` },
        }));
    },
    'shooting-stars'(el) {
        particles(el, 10, (index) => ({
            text: index % 2 ? '✦' : '★',
            vars: { '--x': `${rand(0, 70)}vw`, '--y': `${rand(0, 40)}vh`, '--delay': `${rand(0, 900)}ms`, '--dur': `${rand(900, 1500)}ms`, '--size': `${rand(1, 2)}rem` },
        }));
    },
    balloons(el) {
        const balloons = ['🎈', '🎈', '🎈', '🪅'];
        particles(el, 12, (index) => ({
            text: balloons[index % balloons.length],
            vars: { '--x': `${rand(2, 95)}vw`, '--drift': `${rand(-6, 6)}vw`, '--delay': `${rand(0, 700)}ms`, '--dur': `${rand(2000, 2600)}ms`, '--size': `${rand(1.8, 2.8)}rem` },
        }));
    },
    fireworks(el) {
        for (let burst = 0; burst < 3; burst++) {
            const cx = rand(20, 80);
            const cy = rand(20, 50);
            particles(el, 12, (index) => {
                const angle = (index / 12) * Math.PI * 2;
                return {
                    text: '✶',
                    className: `game-type--${TYPE_COLORS[(burst * 3 + index) % TYPE_COLORS.length]}`,
                    vars: { '--x': `${cx}vw`, '--y': `${cy}vh`, '--dx': `${Math.cos(angle) * 14}vmin`, '--dy': `${Math.sin(angle) * 14}vmin`, '--delay': `${burst * 450}ms`, '--dur': '1100ms' },
                };
            });
        }
    },
    bubbles(el) {
        particles(el, 22, () => ({
            vars: { '--x': `${rand(0, 98)}vw`, '--drift': `${rand(-4, 4)}vw`, '--delay': `${rand(0, 800)}ms`, '--dur': `${rand(1800, 2500)}ms`, '--size': `${rand(0.8, 2.4)}rem` },
        }));
    },
    balls(el) {
        particles(el, 16, () => ({
            className: 'game-ball',
            vars: { '--x': `${rand(0, 96)}vw`, '--delay': `${rand(0, 700)}ms`, '--dur': `${rand(1300, 2000)}ms`, '--spin': `${rand(-360, 360)}deg` },
        }));
    },
    // Temáticas (CHG-045): solo salen durante una temporada con ese tema.
    snow(el) {
        particles(el, 30, () => ({
            text: Math.random() > 0.5 ? '❄' : '•',
            vars: { '--x': `${rand(0, 98)}vw`, '--drift': `${rand(-6, 6)}vw`, '--spin': `${rand(-120, 120)}deg`, '--delay': `${rand(0, 900)}ms`, '--dur': `${rand(2000, 2600)}ms`, '--size': `${rand(0.8, 1.6)}rem` },
        }));
    },
    sparkles(el) {
        particles(el, 18, (index) => ({
            text: index % 3 ? '✦' : '✧',
            vars: { '--x': `${rand(0, 70)}vw`, '--y': `${rand(0, 40)}vh`, '--delay': `${rand(0, 1000)}ms`, '--dur': `${rand(900, 1500)}ms`, '--size': `${rand(1, 2)}rem` },
        }));
    },
    pumpkins(el) {
        const items = ['🎃', '🦇', '🍬', '🎃'];
        particles(el, 12, (index) => ({
            text: items[index % items.length],
            vars: { '--x': `${rand(2, 95)}vw`, '--drift': `${rand(-6, 6)}vw`, '--delay': `${rand(0, 700)}ms`, '--dur': `${rand(2000, 2600)}ms`, '--size': `${rand(1.6, 2.6)}rem` },
        }));
    },
    hearts(el) {
        const items = ['💛', '💖', '💜', '🤝'];
        particles(el, 14, (index) => ({
            text: items[index % items.length],
            vars: { '--x': `${rand(2, 95)}vw`, '--drift': `${rand(-6, 6)}vw`, '--delay': `${rand(0, 700)}ms`, '--dur': `${rand(2000, 2600)}ms`, '--size': `${rand(1.4, 2.4)}rem` },
        }));
    },
    candles(el) {
        const items = ['🕯️', '🎂', '🎉', '🕯️'];
        particles(el, 12, (index) => ({
            text: items[index % items.length],
            vars: { '--x': `${rand(2, 95)}vw`, '--drift': `${rand(-6, 6)}vw`, '--delay': `${rand(0, 700)}ms`, '--dur': `${rand(2000, 2600)}ms`, '--size': `${rand(1.6, 2.6)}rem` },
        }));
    },
    petals(el) {
        const petals = ['🌸', '🌼', '🍃', '🌺'];
        particles(el, 22, (index) => ({
            text: petals[index % petals.length],
            vars: { '--x': `${rand(0, 98)}vw`, '--drift': `${rand(-10, 10)}vw`, '--spin': `${rand(-200, 200)}deg`, '--delay': `${rand(0, 900)}ms`, '--dur': `${rand(2000, 2600)}ms`, '--size': `${rand(1, 1.8)}rem` },
        }));
    },
};

let active = false;

export function celebrate(name) {
    if (reducedMotion() || active || !catalog[name]) {
        return false;
    }
    active = true;
    window.setTimeout(() => { active = false; }, DURATION);
    catalog[name](layer(name));

    return true;
}

/**
 * Escucha `game-celebrate` (lo despacha el notificador) y pide al notificador
 * revisar novedades (`game-check`) solo cuando la respuesta del servidor trae
 * `X-Game-Event: 1`, es decir, cuando la acción generó un evento del juego (CHG-041).
 */
export function registerGameCelebrations(Livewire) {
    window.addEventListener('game-celebrate', (event) => celebrate(event.detail?.name));

    let timer = null;
    Livewire.hook('request', ({ respond }) => {
        respond(({ response }) => {
            if (response?.headers?.get('X-Game-Event') !== '1') return;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => Livewire.dispatch('game-check'), 400);
        });
    });
}
