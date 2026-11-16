/**
 * Apariencia de la aplicación (Claro, Oscuro o «Sistema»).
 *
 * `partials/theme-init` resuelve el tema antes del primer pintado. Este módulo
 * lo mantiene al cambiar la preferencia, al cambiar el modo del dispositivo y
 * tras cada navegación, y guarda la elección en la cuenta.
 */
export function resolveTheme(preference) {
    if (preference === 'system') {
        return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    return preference === 'dark' ? 'dark' : 'light';
}

export function applyTheme(preference) {
    const root = document.documentElement;
    const theme = resolveTheme(preference);
    const changed = root.dataset.theme !== theme;

    root.dataset.themePreference = preference;
    root.dataset.theme = theme;
    syncThemeColor(theme);
    window.dispatchEvent(new CustomEvent('miso:theme', { detail: { preference, theme, changed } }));
}

// La barra del navegador conserva el color del manifest en claro y usa la superficie en oscuro.
function syncThemeColor(theme) {
    const meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) return;

    meta.dataset.light ||= meta.getAttribute('content');
    const surface = getComputedStyle(document.documentElement).getPropertyValue('--md-sys-color-surface').trim();
    meta.setAttribute('content', theme === 'dark' && surface ? surface : meta.dataset.light);
}

const currentPreference = () => document.documentElement.dataset.themePreference || 'system';

export function registerTheme(Alpine) {
    syncThemeColor(document.documentElement.dataset.theme);
    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (currentPreference() === 'system') applyTheme('system');
    });
    document.addEventListener('livewire:navigated', () => applyTheme(currentPreference()));

    Alpine.data('themePicker', () => ({
        preference: currentPreference(),
        announcement: '',
        failed: false,

        init() {
            window.addEventListener('miso:theme', (event) => { this.preference = event.detail.preference; });
        },

        async choose(preference) {
            const previous = this.preference;
            if (preference === previous) return;

            applyTheme(preference);
            this.failed = false;

            try {
                const response = await fetch(this.$root.dataset.url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ theme: preference }),
                });
                if (!response.ok) throw new Error(String(response.status));
                this.announcement = 'Apariencia actualizada.';
            } catch {
                this.failed = true;
                this.announcement = 'No pudimos guardar tu apariencia. Inténtalo de nuevo.';
            }
        },

        // Flechas entre opciones, como en cualquier grupo de radios.
        move(event, step) {
            const options = [...this.$root.querySelectorAll('[role="radio"]')];
            const next = options[(options.indexOf(event.target) + step + options.length) % options.length];
            next?.focus();
            next?.click();
        },
    }));
}
