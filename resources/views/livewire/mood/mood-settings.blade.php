<x-module-shell module="mood">
    <x-slot:actions>
        <x-module-actions
            :primary="['label' => 'Nueva emoción', 'icon' => 'bi-plus-lg', 'action' => 'openForm']"
 />
    </x-slot:actions>

    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    @php
        $moodFilterCount = collect([$status, $category])->filter()->count();
    @endphp
    <nav aria-label="Ajustes de categorías"><x-ui.action variant="text" icon="bi-diagram-3" :href="route('categories.settings', 'mood')">Gestionar categorías</x-ui.action></nav>
    <x-ui.management-card id="mood-catalog" title="Catálogo de emociones" icon="bi-emoji-smile" :count="'('.$states->total().' / '.$totalCount.')'"
                          search="search" search-placeholder="Buscar una emoción" :active-filters="$moodFilterCount"
                          :paginator="$states" noun="emociones" alpine="openMenu: null" flush>
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros del catálogo" @click.outside="openMenu = null">
            <button wire:click="$set('status', '{{ $status === 'active' ? '' : 'active' }}')"
                    class="md-chip md-chip-filter {{ $status === 'active' ? 'selected' : '' }}"
                    aria-pressed="{{ $status === 'active' ? 'true' : 'false' }}">Activas</button>
            <button wire:click="$set('status', '{{ $status === 'inactive' ? '' : 'inactive' }}')"
                    class="md-chip md-chip-filter {{ $status === 'inactive' ? 'selected' : '' }}"
                    aria-pressed="{{ $status === 'inactive' ? 'true' : 'false' }}">Inactivas</button>

            <div class="md-chip-rail__divider"></div>

            <div class="md-chip-menu" :class="{ 'open': openMenu === 'category' }">
                <button @click="openMenu = openMenu === 'category' ? null : 'category'"
                        class="md-chip md-chip-filter {{ $category ? 'selected' : '' }}">
                    {{ $category ?: 'Categoría' }}
                    <i class="bi bi-chevron-down md-chip-menu__arrow" aria-hidden="true"></i>
                </button>
                <div x-show="openMenu === 'category'" x-transition x-cloak class="md-chip-menu__dropdown">
                    <button wire:click="$set('category', '')" @click="openMenu = null"
                            class="md-chip-menu__item {{ ! $category ? 'active' : '' }}">Todas</button>
                    @foreach ($categories as $option => $categoryLabel)
                        <button wire:click="$set('category', '{{ $option }}')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $category === $option ? 'active' : '' }}">{{ $categoryLabel }}</button>
                    @endforeach
                </div>
            </div>
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $status !== '' ? ['key' => 'status', 'value' => null, 'label' => 'Estado: '.($status === 'active' ? 'Activas' : 'Inactivas'), 'icon' => 'bi-toggle-on'] : null,
                $category !== '' ? ['key' => 'category', 'value' => null, 'label' => 'Categoría: '.($categories[$category] ?? $category), 'icon' => 'bi-tag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="confirmRestore">Restaurar catálogo predeterminado</x-ui.menu-item>
        </x-slot:menu>
        @forelse ($states as $state)
            <div class="md-list-item" wire:key="mood-state-{{ $state->id }}">
                {{-- Only the decorative emoji fades: the row's text keeps full contrast
                     and the "Inactiva" chip is what actually carries the state. --}}
                <div class="md-list-item-leading">
                    <span style="font-size: 1.5rem; {{ $state->is_active ? '' : 'opacity: .5;' }}"
                          aria-hidden="true">{{ $state->emoji }}</span>
                </div>
                <div class="md-list-item-content">
                    <div class="md-list-item-headline">
                        {{ $state->text }}
                        @if ($state->is_pinned)
                            <span class="md-chip-tonal"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i> Fijada</span>
                        @endif
                        @unless ($state->is_active)
                            <span class="md-chip-tonal">Inactiva</span>
                        @endunless
                    </div>
                    <div class="md-list-item-supporting">
                        {{ $state->category_label ?: 'Sin categoría' }} · Valencia {{ $state->value }}/10
                        · {{ $state->isDefault() ? 'Predeterminada' : 'Personalizada' }}
                        @if ($state->entries_count)
                            · {{ $state->entries_count }} {{ $state->entries_count === 1 ? 'registro' : 'registros' }}
                        @endif
                    </div>
                </div>
                <div class="md-list-item-trailing d-flex gap-1">
                    @if ($state->is_pinned)
                        <button wire:click="move('{{ $state->id }}', -1)" class="md-btn-icon"
                                aria-label="Subir {{ $state->text }} en el selector rápido" title="Subir">
                            <i class="bi bi-arrow-up"></i>
                        </button>
                        <button wire:click="move('{{ $state->id }}', 1)" class="md-btn-icon"
                                aria-label="Bajar {{ $state->text }} en el selector rápido" title="Bajar">
                            <i class="bi bi-arrow-down"></i>
                        </button>
                    @endif
                    <button wire:click="togglePin('{{ $state->id }}')" class="md-btn-icon"
                            aria-pressed="{{ $state->is_pinned ? 'true' : 'false' }}"
                            aria-label="{{ $state->is_pinned ? 'Dejar de fijar' : 'Fijar' }} {{ $state->text }}"
                            title="{{ $state->is_pinned ? 'Dejar de fijar' : 'Fijar en el selector rápido' }}">
                        <i class="bi {{ $state->is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle' }}"></i>
                    </button>
                    <button wire:click="toggleActive('{{ $state->id }}')" class="md-btn-icon"
                            aria-pressed="{{ $state->is_active ? 'true' : 'false' }}"
                            aria-label="{{ $state->is_active ? 'Desactivar' : 'Activar' }} {{ $state->text }}"
                            title="{{ $state->is_active ? 'Desactivar' : 'Activar' }}">
                        <i class="bi {{ $state->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                    </button>
                    <button wire:click="openForm('{{ $state->id }}')" class="md-btn-icon"
                            aria-label="Editar {{ $state->text }}" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    @if ($state->isDeletable())
                        <button wire:click="delete('{{ $state->id }}')"
                                wire:confirm="¿Eliminar “{{ $state->text }}” de tu catálogo? Nunca la has usado, así que no perderás registros."
                                class="md-btn-icon" style="color: var(--md-sys-color-error);"
                                aria-label="Eliminar {{ $state->text }}" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-3">
                @if ($totalCount === 0)
                    <x-mood-empty-catalog action="confirmRestore" />
                @else
                    <x-empty-state title="Ninguna emoción coincide" icon="bi-search"
                                   message="Ajusta la búsqueda o los filtros para ver el resto de tu catálogo." />
                @endif
            </div>
        @endforelse
    </x-ui.management-card>

    <x-slot:rail>
        <x-context-widget title="Tu catálogo" icon="bi-emoji-smile" tone="success">
            <dl class="md-context-list">
                <div><dt>Activas</dt><dd>{{ $activeCount }}</dd></div>
                <div><dt>Totales</dt><dd>{{ $totalCount }}</dd></div>
                <div><dt>Fijadas</dt><dd>{{ $pinnedCount }}</dd></div>
                <div><dt>Predeterminadas faltantes</dt><dd>{{ max($missingDefaults, 0) }}</dd></div>
            </dl>
        </x-context-widget>

        <x-context-widget title="Valencia e intensidad" icon="bi-question-circle">
            <p class="md-body-small mb-0">
                La <strong>valencia</strong> describe qué tan agradable o desagradable resulta
                normalmente una emoción y vive en el catálogo. La <strong>intensidad</strong> es
                cuánto la sentiste en un momento concreto y se registra en cada entrada.
            </p>
        </x-context-widget>

        <x-context-widget title="Recuperar" icon="bi-arrow-counterclockwise">
            <p class="md-body-small">
                Restaurar añade las emociones predeterminadas que falten y reactiva las que
                desactivaste. No borra nada de lo que creaste.
            </p>
            <button wire:click="confirmRestore" class="md-btn-tonal">
                <i class="bi bi-arrow-counterclockwise"></i> Restaurar catálogo
            </button>
        </x-context-widget>
    </x-slot:rail>

    {{-- Create / edit --}}
    @if ($showForm)
        <div>
            <div class="md-dialog-scrim" wire:click="closeForm"></div>
            <div class="md-dialog" role="dialog" aria-modal="true" aria-labelledby="mood-state-dialog-title" @click.stop>
                <h2 class="md-dialog-headline md-headline-small" id="mood-state-dialog-title">
                    {{ $editingId ? 'Editar emoción' : 'Nueva emoción' }}
                </h2>
                <div class="md-dialog-content">
                    @if ($editingId)
                        <p class="md-body-small" style="color: var(--md-sys-color-on-surface-variant);">
                            Los registros que ya hiciste conservan el emoji, el nombre y la valencia que tenían.
                        </p>
                    @endif

                    <div class="row g-3">
                        <div class="col-4">
                            <div class="md-text-field">
                                <input type="text" wire:model="emoji" id="mood-state-emoji" placeholder=" " maxlength="8">
                                <label for="mood-state-emoji">Emoji</label>
                            </div>
                            @error('emoji') <small style="color: var(--md-sys-color-error);">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-8">
                            <div class="md-text-field">
                                <input type="text" wire:model="text" id="mood-state-text" placeholder=" " maxlength="60">
                                <label for="mood-state-text">Nombre</label>
                            </div>
                            @error('text') <small style="color: var(--md-sys-color-error);">{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <fieldset class="mt-3">
                        <legend class="md-label-medium">Categoría</legend>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($categories as $option => $categoryLabel)
                                <button type="button" wire:click="$set('formCategory', '{{ $option }}')"
                                        class="md-chip md-chip-filter {{ $formCategory === $option ? 'selected' : '' }}"
                                        aria-pressed="{{ $formCategory === $option ? 'true' : 'false' }}">{{ $categoryLabel }}</button>
                            @endforeach
                        </div>
                        @error('formCategory') <small style="color: var(--md-sys-color-error);">{{ $message }}</small> @enderror
                    </fieldset>

                    <div class="mt-3">
                        <label class="md-label-medium" for="mood-state-value">Valencia: {{ $value }}/10</label>
                        <input type="range" wire:model.live="value" id="mood-state-value" class="form-range"
                               min="1" max="10" step="1"
                               aria-describedby="mood-state-value-help">
                        <div class="d-flex justify-content-between md-body-small" style="color: var(--md-sys-color-on-surface-variant);">
                            <span>1 · Desagradable</span><span>10 · Agradable</span>
                        </div>
                        <p class="md-body-small mt-2 mb-0" id="mood-state-value-help"
                           style="color: var(--md-sys-color-on-surface-variant);">
                            La valencia dice qué tan agradable suele resultar esta emoción. No es su
                            intensidad: eso lo registras en cada entrada, del 1 al 5.
                        </p>
                        @error('value') <small style="color: var(--md-sys-color-error);">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="md-dialog-actions">
                    <button type="button" wire:click="closeForm" class="md-btn-text">Cancelar</button>
                    <button type="button" wire:click="save" class="md-btn-filled">
                        <i class="bi bi-check-lg"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Restore confirmation --}}
    @if ($showRestoreConfirm)
        <div>
            <div class="md-dialog-scrim" wire:click="cancelRestore"></div>
            <div class="md-dialog" role="dialog" aria-modal="true" aria-labelledby="mood-restore-title" @click.stop>
                <h2 class="md-dialog-headline md-headline-small" id="mood-restore-title">
                    Restaurar catálogo predeterminado
                </h2>
                <div class="md-dialog-content">
                    <p class="md-body-medium mb-2">
                        Se añadirán las emociones predeterminadas que falten y se reactivarán las
                        predeterminadas que hayas desactivado.
                    </p>
                    <p class="md-body-small mb-0" style="color: var(--md-sys-color-on-surface-variant);">
                        Tus emociones personalizadas, los nombres y emojis que editaste y todo tu
                        historial se conservan.
                    </p>
                </div>
                <div class="md-dialog-actions">
                    <button type="button" wire:click="cancelRestore" class="md-btn-text">Cancelar</button>
                    <button type="button" wire:click="restoreDefaults" class="md-btn-filled">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-module-shell>
