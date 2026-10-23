{{--
    Shared post-save experience for Ánimo, Inicio and Diario.
    Nothing here is required: the confirmation can be ignored and the sheet only opens on demand.
--}}

@if ($progressiveEntry)
    <div class="md-mood-snackbar" role="status" aria-live="polite" wire:key="mood-snackbar-{{ $progressiveEntry->id }}">
        <span class="md-mood-snackbar__text">
            {{ $progressiveEntry->emoji }} Registraste “{{ $progressiveEntry->text }}”
        </span>
        <div class="md-mood-snackbar__actions">
            <button type="button" wire:click="undoLastMood" class="md-btn-text">Deshacer</button>
            <button type="button" wire:click="openMoodContext" class="md-btn-text">Añadir contexto</button>
            <button type="button" wire:click="dismissMoodConfirmation" class="md-btn-icon" aria-label="Cerrar confirmación">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
@endif

@if ($showMoodCatalog)
    <x-ui.dialog state="$wire.showMoodCatalog" title="Más emociones" :standard="$panelDialogs ?? false" close-expression="$wire.closeMoodCatalog()" :size="($panelDialogs ?? false) ? 'lg' : 'md'">

                <div class="md-search-bar mb-3">
                    <i class="bi bi-search md-search-bar__icon"></i>
                    <input type="text" wire:model.live.debounce.200ms="moodCatalogSearch"
                           class="md-search-bar__input" placeholder="Buscar una emoción..."
                           aria-label="Buscar una emoción">
                </div>
                <div class="md-mood-catalog">
                    @forelse ($catalogStates as $state)
                        <button type="button" wire:click="logMood('{{ $state->id }}')"
                                class="md-mood-catalog__item" title="{{ $state->text }}">
                            <span class="md-mood-catalog__emoji">{{ $state->emoji }}</span>
                            <span class="md-label-small">{{ $state->text }}</span>
                        </button>
                    @empty
                        <p class="md-body-small mb-0" style="color: var(--md-sys-color-on-surface-variant);">
                            Ninguna emoción coincide con la búsqueda.
                        </p>
                    @endforelse
                </div>

        <x-slot:actions>
                <button type="button" wire:click="closeMoodCatalog" class="md-btn-text">Cerrar</button>
            </x-slot:actions>
    </x-ui.dialog>
@endif

@if ($showMoodContext && $contextEntry)
    <x-ui.dialog state="$wire.showMoodContext" title="Añadir contexto" :standard="$panelDialogs ?? false" close-expression="$wire.closeMoodContext()" :size="($panelDialogs ?? false) ? 'lg' : 'md'">

                <p class="md-body-small" style="color: var(--md-sys-color-on-surface-variant);">
                    Todo esto es opcional. Puedes guardar solo lo que quieras.
                </p>

                <div class="md-text-field mb-3">
                    <input type="text" wire:model="contextSituation" placeholder=" " id="mood-context-situation" maxlength="500">
                    <label for="mood-context-situation">¿Qué pasó?</label>
                </div>
                @error('contextSituation') <p class="md-body-small" style="color: var(--md-sys-color-error);">{{ $message }}</p> @enderror

                <fieldset class="md-mood-intensity mb-3">
                    <legend class="md-label-medium">Intensidad (opcional)</legend>
                    <div class="md-mood-intensity__scale">
                        @foreach (range(1, 5) as $level)
                            <button type="button" wire:click="setContextIntensity({{ $level }})"
                                    class="md-mood-intensity__step {{ $contextIntensity === $level ? 'is-selected' : '' }}"
                                    aria-pressed="{{ $contextIntensity === $level ? 'true' : 'false' }}"
                                    aria-label="Intensidad {{ $level }} de 5">{{ $level }}</button>
                        @endforeach
                    </div>
                </fieldset>
                @error('contextIntensity') <p class="md-body-small" style="color: var(--md-sys-color-error);">{{ $message }}</p> @enderror

                <fieldset class="md-mood-people">
                    <legend class="md-label-medium">¿Con quién? (opcional)</legend>
                    <div class="md-search-bar mb-2">
                        <i class="bi bi-search md-search-bar__icon"></i>
                        <input type="text" wire:model.live.debounce.300ms="contextRelationshipSearch"
                               class="md-search-bar__input" placeholder="Buscar una persona..."
                               aria-label="Buscar una relación">
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse ($suggestedRelationships as $relationship)
                            <button type="button" wire:click="toggleContextRelationship('{{ $relationship->id }}')"
                                    class="md-chip md-chip-filter {{ in_array($relationship->id, $contextRelationshipIds, true) ? 'selected' : '' }}"
                                    aria-pressed="{{ in_array($relationship->id, $contextRelationshipIds, true) ? 'true' : 'false' }}">
                                {{ $relationship->displayName() }}
                            </button>
                        @empty
                            <p class="md-body-small mb-0" style="color: var(--md-sys-color-on-surface-variant);">
                                Sin relaciones para sugerir.
                            </p>
                        @endforelse
                    </div>
                    <p class="md-body-small mt-2 mb-0" style="color: var(--md-sys-color-on-surface-variant);">
                        Se vincula solo lo que selecciones.
                    </p>
                </fieldset>

        <x-slot:actions>
                <button type="button" wire:click="closeMoodContext" class="md-btn-text">Cancelar</button>
                <button type="button" wire:click="saveMoodContext" class="md-btn-filled">
                    <i class="bi bi-check-lg"></i> Guardar contexto
                </button>
            </x-slot:actions>
    </x-ui.dialog>
@endif
