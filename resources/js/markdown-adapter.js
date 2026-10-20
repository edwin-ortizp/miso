const toolIcons = {
    heading: 'bi-type-h2',
    bold: 'bi-type-bold',
    italic: 'bi-type-italic',
    link: 'bi-link-45deg',
    list: 'bi-list-ul',
    checklist: 'bi-list-check',
    quote: 'bi-blockquote-left',
    code: 'bi-code-slash',
};

// Only bridges MiSo's state contract. Formatting and toolbar stay in the package.
export function registerMarkdownAdapter(Alpine) {
    Alpine.data('misoMarkdown', (editorId, modeProperty, modelProperty) => ({
        toolObserver: null,
        resizeObserver: null,
        layoutListener: null,
        layoutFrame: null,
        refreshLayout() {
            if (this.layoutFrame) return;
            this.layoutFrame = requestAnimationFrame(() => {
                this.layoutFrame = null;
                const editor = this.$el.querySelector('.lm-editor');
                if (!editor || editor.classList.contains('lm-editor--expanded')) return;
                const modal = this.$el.closest('.md-dialog, .md-form-dialog');
                if (!modal || !modal.getClientRects().length) return;
                const content = modal.querySelector('.lt-cm-body > .md-dialog-content, .md-form-dialog__body, .md-dialog-content');
                if (!content) return;
                const viewport = window.visualViewport;
                const viewportBottom = (viewport?.offsetTop ?? 0) + (viewport?.height ?? innerHeight);
                const bottom = Math.min(content.getBoundingClientRect().bottom, viewportBottom);
                // Scroll position must not change the height budget and cause a feedback loop.
                const top = editor.getBoundingClientRect().top + content.scrollTop;
                const toolbar = editor.querySelector('.lm-editor__toolbar').getBoundingClientRect().height;
                const available = Math.max(132, Math.floor(bottom - top - toolbar - 20));
                const value = `${available}px`;
                if (this.$el.style.getPropertyValue('--lm-content-max-height') !== value) {
                    this.$el.style.setProperty('--lm-content-max-height', value);
                }
            });
        },
        init() {
            this.decorateTools();
            this.layoutListener = () => this.refreshLayout();
            this.resizeObserver = new ResizeObserver(this.layoutListener);
            this.resizeObserver.observe(this.$el);
            const modal = this.$el.closest('.md-dialog, .md-form-dialog');
            if (modal) this.resizeObserver.observe(modal);
            window.addEventListener('resize', this.layoutListener);
            window.visualViewport?.addEventListener('resize', this.layoutListener);
            // El scroll del viewport visual solo cambia la altura útil en móvil (teclado en pantalla).
            if (matchMedia('(pointer: coarse)').matches) window.visualViewport?.addEventListener('scroll', this.layoutListener);
            this.toolObserver = new MutationObserver(() => this.decorateTools());
            this.toolObserver.observe(this.$el, { childList: true, subtree: true, characterData: true });
            this.$watch(() => this.$wire[modeProperty], () => this.syncMode());
            this.$watch(() => this.$wire[modelProperty], () => this.syncContent());
            this.$nextTick(() => {
                window.LaravelMarkdownEditor.initializePortable(this.$el);
                this.syncContent();
                this.syncMode();
            });
        },
        destroy() {
            this.toolObserver?.disconnect();
            this.resizeObserver?.disconnect();
            cancelAnimationFrame(this.layoutFrame);
            window.removeEventListener('resize', this.layoutListener);
            window.visualViewport?.removeEventListener('resize', this.layoutListener);
            window.visualViewport?.removeEventListener('scroll', this.layoutListener);
        },
        decorateTools() {
            this.$el.querySelectorAll('[data-markdown-snippet]').forEach(button => {
                const iconClass = toolIcons[button.dataset.markdownSnippet];
                if (!iconClass) return;
                const label = button.textContent.trim() || button.getAttribute('aria-label');
                if (!label) return;
                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
                if (button.querySelector('[data-miso-markdown-icon]')) return;
                const icon = document.createElement('i');
                icon.className = `bi ${iconClass}`;
                icon.setAttribute('aria-hidden', 'true');
                icon.setAttribute('data-miso-markdown-icon', '');
                button.replaceChildren(icon);
            });
        },
        // La barra sigue activa en vista previa; sin esto escribiría en el textarea oculto.
        editBeforeFormatting(event) {
            if (!event.target.closest('[data-markdown-snippet]')) return;
            if (!this.$el.querySelector('[data-markdown-edit]')?.hidden) return;
            window.dispatchEvent(new CustomEvent('markdown-editor-set-mode', { detail: { editorId, mode: 'edit' } }));
            if (this.$wire[modeProperty] !== 'write') this.$wire.$set(modeProperty, 'write', false);
        },
        modeChanged(event) {
            if (event.detail.editorId !== editorId) return;
            const mode = event.detail.mode === 'preview' ? 'preview' : 'write';
            if (this.$wire[modeProperty] !== mode) this.$wire.$set(modeProperty, mode, false);
        },
        syncMode() {
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('markdown-editor-set-mode', {
                detail: { editorId, mode: this.$wire[modeProperty] === 'preview' ? 'preview' : 'edit' },
            })));
        },
        syncContent() {
            this.$nextTick(() => {
                const source = this.$el.querySelector('[data-markdown-source]');
                if (!source) return;
                const value = this.$wire[modelProperty] ?? '';
                if (source.value !== value) source.value = value;
                window.LaravelMarkdownEditor.updatePreview(source);
            });
        },
    }));
}
