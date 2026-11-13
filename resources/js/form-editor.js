import { allowLeave } from './navigation-guard';

// Draft visibility is client state. Only loading an existing record and saving
// use Livewire; their requests belong to the editor, never the surrounding list.
export function registerFormEditor(Alpine) {
    Alpine.data('ltFormEditor', (wire, defaults, actions) => ({
        loading: false,
        loadError: '',
        submitted: false,
        editingContent: true,
        generation: 0,
        get showDialog() { return wire.showForm; },
        set showDialog(value) { wire.$set('showForm', value, false); },
        focusDuration() {
            if (wire.startDate && wire.endDate && wire.startTime && wire.endTime) {
                const start = new Date(`${wire.startDate}T${wire.startTime}`);
                const end = new Date(`${wire.endDate}T${wire.endTime}`);
                const minutes = (end - start) / 60000;
                if (Number.isFinite(minutes) && minutes > 0) return Math.round(minutes);
            }
            return Number(wire.estimatedTime) > 0 ? Number(wire.estimatedTime) : null;
        },
        async openEditor(detail) {
            if (this.loading) return;
            const state = actions[detail.action];
            if (!state) return;
            if (!await allowLeave()) return;
            this.closeEditor();
            const generation = ++this.generation;
            Object.entries(defaults).forEach(([key, value]) => wire.$set(key, structuredClone(value), false));
            Object.entries(detail.prefill ?? {}).forEach(([key, value]) => wire.$set(key, value, false));
            this.submitted = false;
            this.editingContent = !detail.id || detail.mode === 'edit' || detail.intent === 'schedule';
            this.loadError = '';
            wire.$set(state, true, false);
            if (detail.id) {
                this.loading = true;
                try {
                    await wire.$call(detail.action, detail.id);
                    if (generation === this.generation && detail.section) {
                        window.dispatchEvent(new CustomEvent('task-editor-section', { detail: { section: detail.section } }));
                    }
                } catch {
                    this.loadError = 'No se pudo cargar el registro. Cierra el formulario e inténtalo de nuevo.';
                } finally {
                    this.loading = false;
                    if (generation !== this.generation) this.closeEditor();
                }
            }
        },
        closeEditor() {
            this.generation++;
            Object.values(actions).forEach(state => wire.$set(state, false, false));
        },
    }));
}
