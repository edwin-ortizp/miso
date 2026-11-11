/**
 * Instalacion y funcionamiento sin conexion.
 *
 * El service worker existia en el repositorio desde hace tiempo pero no lo
 * registraba nadie, asi que la aplicacion nunca llego a comportarse como una
 * PWA. Aqui se registra y se resuelve el flujo de instalacion, que es distinto
 * en Android (evento `beforeinstallprompt`) y en iOS (no existe: hay que
 * explicar los pasos de Safari).
 */

const DISMISSED_KEY = 'lt-install-dismissed-at';
const DISMISS_DAYS = 30;

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    // En local el worker cachearia el bundle entre recargas de Vite.
    if (import.meta.env.DEV) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Un fallo al registrar no puede tumbar la aplicacion: sin worker
            // simplemente no hay modo sin conexion.
        });
    });
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function wasRecentlyDismissed() {
    try {
        const at = Number(window.localStorage.getItem(DISMISSED_KEY));

        return Number.isFinite(at) && at > 0
            && (Date.now() - at) < DISMISS_DAYS * 24 * 60 * 60 * 1000;
    } catch {
        return false;
    }
}

function remember() {
    try {
        window.localStorage.setItem(DISMISSED_KEY, String(Date.now()));
    } catch {
        // Modo privado: se volvera a ofrecer, que es preferible a fallar.
    }
}

export function registerInstallPrompt(Alpine) {
    let deferred = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event;
        window.dispatchEvent(new CustomEvent('lt-install-available'));
    });

    Alpine.data('ltInstallPrompt', () => ({
        open: false,
        platform: 'android',
        installHandler: null,
        promptTimer: null,
        destroy() {
            clearTimeout(this.promptTimer);
            window.removeEventListener('lt-install-available', this.installHandler);
        },

        init() {
            if (isStandalone() || wasRecentlyDismissed()) {
                return;
            }

            // La hoja se puede cerrar desde el scrim, el aspa o el gesto, no
            // solo desde "Ahora no": cualquier cierre cuenta como descarte.
            this.$watch('open', (open) => {
                if (!open) {
                    remember();
                }
            });

            const isIos = /iphone|ipod|ipad/i.test(navigator.userAgent);

            if (isIos) {
                // Safari no expone `beforeinstallprompt`: solo se pueden dar
                // las instrucciones, y solo tienen sentido en el propio Safari.
                if (/safari/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent)) {
                    this.platform = 'ios';
                    // Se espera un poco para no interrumpir el primer vistazo.
                    this.promptTimer = setTimeout(() => { this.open = true; }, 4000);
                }

                return;
            }

            this.installHandler = () => {
                this.platform = 'android';
                this.open = true;
            };
            window.addEventListener('lt-install-available', this.installHandler);
        },

        async install() {
            if (!deferred) {
                this.dismiss();

                return;
            }

            this.open = false;
            deferred.prompt();
            await deferred.userChoice;
            deferred = null;
        },

        dismiss() {
            this.open = false;
        },
    }));
}

/** Clave VAPID pública (base64url) al formato que pide `pushManager.subscribe`. */
function vapidKeyToBytes(key) {
    const padded = (key + '='.repeat((4 - (key.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(padded);

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

/**
 * CHG-067: activar o desactivar los avisos push del guardián en este dispositivo.
 * Estados: unsupported | unconfigured | denied | off | on. El permiso del
 * navegador se pide solo al pulsar "Activar", nunca al cargar la página.
 */
export function registerPushToggle(Alpine) {
    Alpine.data('ltPushToggle', (vapidKey) => ({
        state: 'off',
        busy: false,

        async init() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                this.state = 'unsupported';

                return;
            }
            if (!vapidKey) {
                this.state = 'unconfigured';

                return;
            }
            if (Notification.permission === 'denied') {
                this.state = 'denied';

                return;
            }
            const registration = await navigator.serviceWorker.getRegistration();
            const subscription = await registration?.pushManager.getSubscription();
            this.state = subscription ? 'on' : 'off';
        },

        async enable() {
            this.busy = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.state = permission === 'denied' ? 'denied' : 'off';

                    return;
                }
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription()
                    ?? await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: vapidKeyToBytes(vapidKey),
                    });
                await this.$wire.saveSubscription(subscription.toJSON());
                this.state = 'on';
            } catch {
                this.state = 'off';
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    await this.$wire.removeSubscription(subscription.endpoint);
                    await subscription.unsubscribe();
                }
                this.state = 'off';
            } finally {
                this.busy = false;
            }
        },
    }));
}
