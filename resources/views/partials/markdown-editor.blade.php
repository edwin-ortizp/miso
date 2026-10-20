{{-- Keep MiSo's field/mode contract; editing and tools belong to the package. --}}
<div class="md-markdown-editor"
     x-data="misoMarkdown(@js($id), @js($mode), @js($model))"
     x-on:markdown-editor-mode-changed="modeChanged($event)"
     x-on:click.capture="editBeforeFormatting($event)">
    <x-markdown::editor
        :id="$id"
        :value="$content"
        :rows="$rows ?? 5"
        :placeholder="$placeholder ?? ''"
        :initial-mode="$modeValue === 'preview' ? 'preview' : 'edit'"
        class="md-markdown-editor-input"
        aria-label="Contenido Markdown"
        :attributes="new \Illuminate\View\ComponentAttributeBag([
            ($deferred ?? false) ? 'wire:model' : 'wire:model.live.debounce.250ms' => $model,
        ])"
    />
</div>
