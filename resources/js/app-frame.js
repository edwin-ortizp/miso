/**
 * Marco de aplicacion: plataforma, transiciones de pantalla y estado de red.
 *
 * No hay una variante por dispositivo: el marco es el mismo en cualquier ancho
 * y el CSS decide que se ve. Aqui solo vive lo que el CSS no puede saber.
 */

/** Marca la plataforma en <html> para que el CSS pueda afinar por sistema. */
export function markPlatform() {
    const root = document.documentElement;
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    root.classList.toggle('is-ios', isIos);
    root.classList.toggle('is-standalone', isStandalone);
}

// Animate only the new content after navigation. Never hold a browser snapshot
// while the network request is pending (or when navigation is cancelled).
export function registerPageTransitions() {
    document.addEventListener('livewire:navigated', () => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        document.querySelector('.lt-main')?.animate(
            [{ opacity: 0.85, transform: 'translateX(6px)' }, { opacity: 1, transform: 'none' }],
            { duration: 120, easing: 'ease-out' },
        );
    });
}

export function registerAppFrame(Alpine) {
    Alpine.data('ltNavigation', (defaultRail = false) => ({
        sidebarOpen: false,
        sidebarRail: defaultRail,
        compact: window.matchMedia('(max-width: 767.98px)').matches,
        search: '',
        searchOpen: false,
        removeMediaListener: null,
        init() {
            try {
                const saved = localStorage.getItem('lt-sidebar-rail');
                this.sidebarRail = saved === null ? defaultRail : saved === '1';
            } catch {}
            const mq = window.matchMedia('(max-width: 767.98px)');
            const handler = event => {
                this.compact = event.matches;
                if (!this.compact) { this.sidebarOpen = false; this.searchOpen = false; }
            };
            mq.addEventListener('change', handler);
            this.removeMediaListener = () => mq.removeEventListener('change', handler);
            this.$watch('sidebarRail', value => {
                try { localStorage.setItem('lt-sidebar-rail', value ? '1' : '0'); } catch {}
            });
        },
        toggleNav() {
            if (this.compact) { this.sidebarOpen = !this.sidebarOpen; return; }
            this.sidebarRail = !this.sidebarRail;
        },
        destroy() { this.removeMediaListener?.(); },
    }));
    Alpine.data('ltConnection', () => ({
        offline: !navigator.onLine,

        onlineHandler: null,
        offlineHandler: null,
        destroy() {
            window.removeEventListener('online', this.onlineHandler);
            window.removeEventListener('offline', this.offlineHandler);
        },
        init() {
            this.onlineHandler = () => { this.offline = false; };
            this.offlineHandler = () => { this.offline = true; };
            window.addEventListener('online', this.onlineHandler);
            window.addEventListener('offline', this.offlineHandler);
        },
    }));
}
