/** Select-only combobox. Popover escapes dialog clipping while preserving DOM/focus ownership. */
export function registerCatalogColorSelect(Alpine) {
    Alpine.data('catalogColorSelect', (value, values) => ({
        value, values, open: false, active: 0, labels: {}, observer: null,
        init() {
            this.labels = JSON.parse(this.$refs.list.dataset.labels);
            this.observer = new ResizeObserver(() => { if (this.open) this.position(); });
            this.observer.observe(this.$refs.trigger);
        },
        destroy() { this.observer?.disconnect(); },
        show() {
            this.active = Math.max(0, this.values.indexOf(this.value || ''));
            this.open = true;
            this.$refs.list.showPopover();
            this.position();
            this.revealActive();
        },
        close() {
            this.open = false;
            this.$refs.list.hidePopover();
        },
        toggle() { if (this.open) this.close(); else this.show(); },
        choose(value) {
            this.value = value;
            this.close();
            this.$refs.trigger.focus({ preventScroll: true });
        },
        activate(index) {
            if (!this.open) this.show();
            this.active = index;
            this.revealActive();
        },
        move(step) {
            if (!this.open) { this.show(); return; }
            this.activate((this.active + step + this.values.length) % this.values.length);
        },
        revealActive() {
            this.$nextTick(() => {
                const list = this.$refs.list;
                const option = list.children[this.active];
                if (option.offsetTop < list.scrollTop) list.scrollTop = option.offsetTop;
                else if (option.offsetTop + option.offsetHeight > list.scrollTop + list.clientHeight) {
                    list.scrollTop = option.offsetTop + option.offsetHeight - list.clientHeight;
                }
            });
        },
        position() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const viewport = window.visualViewport;
            const leftEdge = viewport?.offsetLeft || 0;
            const topEdge = viewport?.offsetTop || 0;
            const width = viewport?.width || window.innerWidth;
            const height = viewport?.height || window.innerHeight;
            const below = topEdge + height - rect.bottom - 12;
            const above = rect.top - topEdge - 12;
            const down = below >= Math.min(300, above);
            const maxHeight = Math.max(44, Math.min(300, down ? below : above));
            const list = this.$refs.list;
            Object.assign(list.style, {
                width: `${Math.min(rect.width, width - 24)}px`,
                maxHeight: `${maxHeight}px`,
                left: `${Math.max(leftEdge + 12, Math.min(rect.left, leftEdge + width - rect.width - 12))}px`,
                top: `${down ? rect.bottom + 4 : Math.max(topEdge + 12, rect.top - Math.min(list.scrollHeight, maxHeight) - 4)}px`,
            });
        },
    }));
}
