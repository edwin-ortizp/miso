{{-- Override de markdown::editor sin sus <link>/<script> a /vendor/: el hosting bloquea esa ruta.
     editor.js entra por resources/js/app.js y editor.css por resources/css/app.css. --}}
@php
    $name = $name ?? null;
    $value = $value ?? '';
    $id = $id ?? ($name ? \Illuminate\Support\Str::slug($name, '_').'_markdown' : 'markdown_'.\Illuminate\Support\Str::random(8));
    $initialMode = ($initialMode ?? 'edit') === 'preview' ? 'preview' : 'edit';
    $textareaAttributes = (new \Illuminate\View\ComponentAttributeBag($attributes ?? []))
        ->merge(['id' => $id, 'rows' => $rows ?? 6, 'placeholder' => $placeholder ?? ''])
        ->class(['lm-editor__source']);
@endphp
<div data-markdown-editor data-editor-id="{{ $id }}" data-initial-mode="{{ $initialMode }}" class="lm-editor">
    <div class="lm-editor__toolbar" role="toolbar" aria-label="Formato Markdown">
        <div class="lm-editor__mode" role="group" aria-label="Modo del editor">
            <button type="button" class="lm-editor__button" data-markdown-mode="edit">Editar</button>
            <button type="button" class="lm-editor__button" data-markdown-mode="preview">Vista previa</button>
        </div>
        @foreach (['heading' => 'Título', 'bold' => 'Negrita', 'italic' => 'Cursiva', 'link' => 'Enlace', 'list' => 'Lista', 'checklist' => 'Checklist', 'quote' => 'Cita', 'code' => 'Código'] as $tool => $label)
            <button type="button" class="lm-editor__button" data-markdown-snippet="{{ $tool }}">{{ $label }}</button>
        @endforeach
        <button type="button" class="lm-editor__button lm-editor__copy" data-markdown-copy aria-label="Copiar contenido" title="Copiar contenido">
            <i class="bi bi-clipboard" aria-hidden="true"></i>
        </button>
        @if($expandable ?? true)
            <button type="button" class="lm-editor__button lm-editor__expand" data-markdown-expand hidden aria-label="Ampliar" title="Ampliar" aria-expanded="false">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path data-markdown-expand-icon d="M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5" /></svg>
            </button>
        @endif
    </div>
    <div class="lm-editor__body" data-markdown-body tabindex="0" aria-label="Contenido Markdown">
    <div data-markdown-edit @if($initialMode !== 'edit') hidden @endif>
        <textarea data-markdown-source @if($name) name="{{ $name }}" @endif {{ $textareaAttributes }}>{{ $value }}</textarea>
    </div>
    <div data-markdown-preview class="lm-editor__preview" data-empty-text="{{ $emptyText ?? 'Sin contenido' }}" @if($initialMode !== 'preview') hidden @endif></div>
    </div>
</div>
