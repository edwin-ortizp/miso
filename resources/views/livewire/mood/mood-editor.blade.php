<div x-data @mood-editor.window="$wire.open($event.detail.mode, $event.detail.id ?? null)"
     @mood-editor-delete.window="if (confirm('¿Eliminar este registro de Ánimo y de todas las fichas vinculadas?')) $wire.delete($event.detail.id)">
    <x-ui.form-dialog :open="$mode !== ''" close="close" :submit-action="in_array($mode, ['create', 'edit'], true) ? 'save' : null"
                      :title="match ($mode) {'create' => 'Registrar mood', 'edit' => 'Editar mood', default => 'Detalle del registro'}"
                      id="mood-entry-editor" icon="bi-emoji-smile">
        @if ($mode === 'view' && $entry)
            <dl class="md-context-list">
                <div><dt>Emoción</dt><dd>{{ $entry->emoji }} {{ $entry->text }}</dd></div>
                <div><dt>Fecha</dt><dd>{{ $entry->date->format('d/m/Y') }}</dd></div>
                <div><dt>Detalle</dt><dd>{{ $entry->situation ?: '—' }}</dd></div>
                <div><dt>Contactos</dt><dd>{{ $entry->relationships->map->displayName()->join(', ') ?: '—' }}</dd></div>
            </dl>
        @elseif ($mode !== '')
            <div class="d-flex flex-column gap-3">
                <div role="group" aria-label="Emoción">
                    @if ($entry && ($emotion === ($entry->mood_state_id ?? '')) && ! $states->contains('id', $emotion))
                        <p>{{ $entry->emoji }} {{ $entry->text }}</p>
                    @endif
                    <div class="md-mood-catalog">
                        @foreach ($states as $state)
                            <x-ui.chip variant="filter" :selected="$emotion === $state->id"
                                       wire:click="$set('emotion', '{{ $state->id }}')">
                                {{ $state->emoji }} {{ $state->text }}
                            </x-ui.chip>
                        @endforeach
                    </div>
                    @error('emotion') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <x-ui.field name="date" label="Fecha" type="date" wire:model="date" />
                <x-ui.textarea name="situation" label="Detalle" maxlength="500" wire:model="situation" />
                @if ($origin)
                    <p class="md-body-small">{{ $origin->displayName() }} · Contacto de origen</p>
                @endif
                <x-ui.multi-select name="contacts" label="Contactos relacionados" all-label="Seleccionar contactos" :options="$options" :searchable="true" :live="false" />
                @error('contacts.*') <p role="alert">{{ $message }}</p> @enderror
            </div>
        @endif
    </x-ui.form-dialog>
</div>
