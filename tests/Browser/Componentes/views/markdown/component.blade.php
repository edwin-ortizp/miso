<div x-data="{loading: false, loadError: null, submitted: false, closeEditor() { this.$wire.$set('showEditor', false, false) }}">
    @if($modal)
        <x-create-modal state="$wire.showEditor" :client-close="true" title="Editor de prueba" icon="bi-list-task" :wide="true" class="task-properties-dialog"
            :steps="[['label' => 'Lo básico'], ['label' => 'Más opciones']]" save-action="save" close-action="cancel">
            <div class="task-properties-layout">
                <div class="task-properties-content lt-cm-form">
                    <div class="md-text-field"><input placeholder=" " id="fixture-title" value="Descripción extensa"><label for="fixture-title">Título</label></div>
                    @if($mounted)
                        @include('partials.markdown-editor', ['model' => 'text', 'content' => $text, 'mode' => 'viewMode',
                            'modeValue' => $viewMode, 'id' => 'miso-description', 'placeholder' => '', 'deferred' => $deferred])
                    @endif
                </div>
                <aside class="task-properties-panel">Propiedades sintéticas</aside>
            </div>
        </x-create-modal>
    @else
    @teleport('body')
        <div class="markdown-fixture-dialog" x-show="$wire.showEditor">
            @if($mounted)
                @include('partials.markdown-editor', ['model' => 'text', 'content' => $text, 'mode' => 'viewMode',
                    'modeValue' => $viewMode, 'id' => 'miso-description', 'placeholder' => '', 'deferred' => $deferred])
            @endif
            <button id="save" type="button" wire:click="save">Guardar</button>
            <button id="cancel" type="button" wire:click="cancel">Cancelar</button>
            <button id="close" type="button" x-on:click="$wire.$set('showEditor', false, false)">Cerrar</button>
        </div>
    @endteleport
    @endif
    @include('partials.markdown-editor', ['model' => 'secondary', 'content' => $secondary, 'mode' => 'secondaryMode',
        'modeValue' => $secondaryMode, 'id' => 'miso-secondary', 'placeholder' => ''])
    <button id="reopen" type="button" wire:click="reopen">Reabrir</button>
    <button id="new" type="button" wire:click="newDraft">Nuevo</button>
    <button id="unmount" type="button" wire:click="$set('mounted', false)">Retirar editor</button>
    <button id="refresh" type="button" wire:click="$set('refreshes', {{ $refreshes + 1 }})">Actualizar</button>
    <form id="portable-form">
        <x-markdown::editor id="portable-description" name="description" value="Campo Blade independiente" />
        <x-markdown::editor id="portable-no-expand" :expandable="false" value="Sin ampliación" />
    </form>
    <output id="saved">{{ $saved }}</output>
    <output id="refreshes">{{ $refreshes }}</output>
</div>
