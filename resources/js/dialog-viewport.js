// Scoped to modal surfaces: desktop and unrelated overlays retain their geometry.
export function registerDialogViewport(Alpine) {
    Alpine.directive('dialog-viewport', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const mobile = window.matchMedia('(max-width: 767.98px)');
        const viewport = window.visualViewport;
        let active = false;
        const update = () => {
            if (active && mobile.matches) {
                el.style.setProperty('--dialog-viewport-height', `${viewport?.height ?? window.innerHeight}px`);
                el.style.setProperty('--dialog-viewport-top', `${viewport?.offsetTop ?? 0}px`);
            } else {
                el.style.removeProperty('--dialog-viewport-height');
                el.style.removeProperty('--dialog-viewport-top');
            }
        };
        // Seguir el scroll del viewport visual solo hace falta en móvil (teclado en pantalla);
        // en escritorio ese listener solo dispara el aviso de efecto «scroll-linked» de Firefox.
        const events = [[viewport, 'resize'], [viewport, 'scroll'], [window, 'resize']];
        const listen = () => {
            events.forEach(([target, event]) => target?.removeEventListener(event, update));
            if (active && mobile.matches) events.forEach(([target, event]) => target?.addEventListener(event, update));
            update();
        };
        const toggle = open => {
            active = !!open;
            mobile[active ? 'addEventListener' : 'removeEventListener']('change', listen);
            listen();
        };
        if (expression) { const read = evaluateLater(expression); effect(() => read(toggle)); }
        else toggle(true);
        cleanup(() => toggle(false));
    });
}
