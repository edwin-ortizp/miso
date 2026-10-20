<x-module-shell module="relationships">
    <x-slot:actions>
        <x-module-actions
            :primary="['label' => 'Agregar persona', 'icon' => 'bi-person-plus', 'action' => 'openForm']"
            :secondary="[
                ['label' => 'Agregar círculo', 'icon' => 'bi-plus-circle', 'action' => 'openCircleForm', 'create' => true],
                ['label' => 'Agregar etiqueta', 'icon' => 'bi-tag', 'action' => 'openTagForm', 'create' => true],
            ]"
            :split="true" />
    </x-slot:actions>


    @php
        $activeFilterCount = collect([$showArchived, $circleFilter, $tagFilter])->filter()->count();
    @endphp
    <nav aria-label="Ajustes de categorías"><x-ui.action variant="text" icon="bi-diagram-3" :href="route('categories.settings', 'contacts')">Gestionar categorías</x-ui.action></nav>
    <x-ui.management-card id="relationship-people" title="Personas" icon="bi-people" :count="'('.$relationships->total().' / '.($activeCount + $archivedCount).')'"
                          search="search" search-placeholder="Buscar por nombre, apodo, teléfono o correo" :active-filters="$activeFilterCount"
                          :paginator="$relationships" noun="personas" alpine="openMenu: null"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros de relaciones" @click.outside="openMenu = null">
        
            <button wire:click="$toggle('showArchived')"
                    class="md-chip md-chip-filter {{ $showArchived ? 'selected' : '' }}">
                <i class="bi bi-archive"></i> Archivadas
            </button>

            <div class="md-chip-rail__divider"></div>

            <div class="md-chip-menu" :class="{ 'open': openMenu === 'circle' }">
                <button @click="openMenu = openMenu === 'circle' ? null : 'circle'"
                        class="md-chip md-chip-filter {{ $circleFilter ? 'selected' : '' }}">
                    {{ $circleFilter ? $circles->firstWhere('id', $circleFilter)?->name : 'Círculo' }}
                    <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                </button>
                <div x-show="openMenu === 'circle'" x-transition x-cloak class="md-chip-menu__dropdown">
                    <button wire:click="$set('circleFilter', '')" @click="openMenu = null"
                            class="md-chip-menu__item {{ $circleFilter === '' ? 'active' : '' }}">Todos</button>
                    @foreach ($circles as $circle)
                        <button wire:click="$set('circleFilter', '{{ $circle->id }}')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $circleFilter === $circle->id ? 'active' : '' }}">{{ $circle->name }}</button>
                    @endforeach
                </div>
            </div>

            <div class="md-chip-menu" :class="{ 'open': openMenu === 'tag' }">
                <button @click="openMenu = openMenu === 'tag' ? null : 'tag'"
                        class="md-chip md-chip-filter {{ $tagFilter ? 'selected' : '' }}">
                    {{ $tagFilter ? $tags->firstWhere('id', $tagFilter)?->name : 'Etiqueta' }}
                    <i class="bi bi-chevron-down md-chip-menu__arrow"></i>
                </button>
                <div x-show="openMenu === 'tag'" x-transition x-cloak class="md-chip-menu__dropdown">
                    <button wire:click="$set('tagFilter', '')" @click="openMenu = null"
                            class="md-chip-menu__item {{ $tagFilter === '' ? 'active' : '' }}">Todas</button>
                    @foreach ($tags as $tag)
                        <button wire:click="$set('tagFilter', '{{ $tag->id }}')" @click="openMenu = null"
                                class="md-chip-menu__item {{ $tagFilter === $tag->id ? 'active' : '' }}">{{ $tag->name }}</button>
                    @endforeach
                </div>
            </div>
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $showArchived ? ['key' => 'showArchived', 'value' => null, 'label' => 'Archivadas', 'icon' => 'bi-archive'] : null,
                $circleFilter !== '' ? ['key' => 'circleFilter', 'value' => null, 'label' => 'Círculo: '.($circles->firstWhere('id', $circleFilter)?->name ?? '—'), 'icon' => 'bi-bullseye'] : null,
                $tagFilter !== '' ? ['key' => 'tagFilter', 'value' => null, 'label' => 'Etiqueta: '.($tags->firstWhere('id', $tagFilter)?->name ?? '—'), 'icon' => 'bi-tag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
    @forelse ($relationships as $rel)
        @php
            $birthday = $rel->birthday();
        @endphp
        <div class="md-card-outlined md-relationship-card mb-2 {{ $rel->is_archived ? 'md-relationship-card--archived' : '' }}">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <a href="{{ route('relationships.show', $rel) }}" class="md-relationship-card__link d-flex align-items-center gap-3" wire:navigate>
                    <div class="md-list-icon-circle" style="background: var(--md-custom-color-info-container); color: var(--md-custom-color-on-info-container);">
                        <i class="bi bi-person-fill" style="font-size: 1rem;"></i>
                    </div>
                    <div>
                        <div class="md-title-small" style="color: var(--md-sys-color-on-surface);">
                            {{ $rel->full_name }}
                            @if ($rel->nickname)
                                <span class="md-label-small" style="color: var(--md-sys-color-on-surface-variant);">· {{ $rel->nickname }}</span>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @if ($rel->circle)
                                <span class="md-chip-tonal md-chip-tonal--info">{{ $rel->circle->name }}</span>
                            @endif
                            @foreach ($rel->tags as $tag)
                                <span class="md-chip-tonal">{{ $tag->name }}</span>
                            @endforeach
                            @if ($birthday)
                                <span class="md-chip-tonal md-chip-tonal--warning">
                                    <i class="bi bi-cake2" style="font-size: 0.5625rem;"></i> {{ $birthday->label() }}
                                </span>
                            @endif
                            @if ($rel->isFollowUpDue())
                                <span class="md-chip-tonal md-chip-tonal--warning">
                                    <i class="bi bi-exclamation-circle" style="font-size: 0.5625rem;"></i> Por contactar
                                </span>
                            @endif
                            @foreach ($rel->contactMethods->take(2) as $method)
                                <span class="md-label-small md-relationship-card__contact">
                                    <i class="bi {{ $method->icon() }}" aria-hidden="true"></i>
                                    {{ $method->value }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </a>
                <div class="d-flex gap-1">
                    <button wire:click="markContact('{{ $rel->id }}')" class="md-btn-icon" title="Marcar contacto" style="color: var(--md-custom-color-success);">
                        <i class="bi bi-chat-dots"></i>
                    </button>
                    <button wire:click="openForm('{{ $rel->id }}')" class="md-btn-icon" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button wire:click="toggleArchive('{{ $rel->id }}')" class="md-btn-icon" title="{{ $rel->is_archived ? 'Desarchivar' : 'Archivar' }}" style="color: var(--md-custom-color-warning);">
                        <i class="bi {{ $rel->is_archived ? 'bi-archive-fill' : 'bi-archive' }}"></i>
                    </button>
                    <button wire:click="delete('{{ $rel->id }}')"
                            wire:confirm="Se eliminará el perfil de {{ $rel->full_name }} y sus acontecimientos. Las tareas asociadas se conservarán. ¿Continuar?"
                            class="md-btn-icon" title="Eliminar" style="color: var(--md-sys-color-error);">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    @empty
        <x-empty-state icon="bi-people" title="{{ $showArchived ? 'Sin relaciones archivadas' : 'Sin personas registradas' }}"
                       message="Agrega a las personas que quieres recordar y cuidar." />
    @endforelse

    </x-ui.management-card>

    <x-slot:rail>
        <x-context-widget title="Resumen" icon="bi-people" tone="success">
            <dl class="md-context-list">
                <div><dt>Activas</dt><dd>{{ $activeCount }}</dd></div>
                <div><dt>Archivadas</dt><dd>{{ $archivedCount }}</dd></div>
                <div><dt>Círculos</dt><dd>{{ $circles->count() }}</dd></div>
                <div><dt>Etiquetas</dt><dd>{{ $tags->count() }}</dd></div>
            </dl>
        </x-context-widget>

        @if ($nextBirthdays->isNotEmpty())
            <x-context-widget title="Próximos cumpleaños" icon="bi-cake2" tone="warning">
                <dl class="md-context-list">
                    @foreach ($nextBirthdays as $row)
                        <div>
                            <dt>{{ $row['relationship']->displayName() }}</dt>
                            <dd>{{ $row['birthday']->isToday() ? 'Hoy' : 'en '.$row['birthday']->daysUntil().' días' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-context-widget>
        @endif

        @if ($dueFollowUps->isNotEmpty())
            <x-context-widget title="Seguimiento vencido" icon="bi-exclamation-circle" tone="warning">
                <dl class="md-context-list">
                    @foreach ($dueFollowUps as $due)
                        <div>
                            <dt>{{ $due->displayName() }}</dt>
                            <dd>{{ $due->daysSinceLastContact() }} d</dd>
                        </div>
                    @endforeach
                </dl>
            </x-context-widget>
        @endif

        <x-context-widget title="Organización" icon="bi-diagram-3">
            <div class="md-relationship-admin">
                @forelse ($circles as $circle)
                    <div class="md-relationship-admin__row">
                        <span>{{ $circle->name }}</span>
                        <span class="d-flex gap-1">
                            <button wire:click="openCircleForm('{{ $circle->id }}')" class="md-btn-icon" title="Editar círculo"><i class="bi bi-pencil"></i></button>
                            <button wire:click="deleteCircle('{{ $circle->id }}')" wire:confirm="Las relaciones de este círculo quedarán sin círculo. ¿Eliminar?"
                                    class="md-btn-icon" title="Eliminar círculo" style="color: var(--md-sys-color-error);"><i class="bi bi-trash"></i></button>
                        </span>
                    </div>
                @empty
                    <p class="md-body-small mb-2" style="color: var(--md-sys-color-on-surface-variant);">Sin círculos.</p>
                @endforelse

                <div class="md-relationship-admin__divider"></div>

                @forelse ($tags as $tag)
                    <div class="md-relationship-admin__row">
                        <span>{{ $tag->name }}</span>
                        <span class="d-flex gap-1">
                            <button wire:click="openTagForm('{{ $tag->id }}')" class="md-btn-icon" title="Editar etiqueta"><i class="bi bi-pencil"></i></button>
                            <button wire:click="deleteTag('{{ $tag->id }}')" wire:confirm="La etiqueta se quitará de todas las relaciones. ¿Eliminar?"
                                    class="md-btn-icon" title="Eliminar etiqueta" style="color: var(--md-sys-color-error);"><i class="bi bi-trash"></i></button>
                        </span>
                    </div>
                @empty
                    <p class="md-body-small mb-0" style="color: var(--md-sys-color-on-surface-variant);">Sin etiquetas.</p>
                @endforelse
            </div>
        </x-context-widget>
    </x-slot:rail>

    {{-- Circle dialog --}}
    <x-ui.form-dialog :open="$showCircleForm" close="$set('showCircleForm', false)" submit-action="saveCircle"
                      :title="($editingCircleId ? 'Editar' : 'Agregar').' círculo'" icon="bi-plus-circle" id="relationship-circle-dialog">
        <div class="d-flex flex-column gap-3">
            <x-ui.field name="circleName" label="Nombre del círculo" :required="true" wire:model="circleName" />
            <x-ui.field name="circleFrequencyDays" label="Frecuencia de contacto (días)" type="number" min="1" wire:model="circleFrequencyDays" />
        </div>
    </x-ui.form-dialog>

    {{-- Tag dialog --}}
    <x-ui.form-dialog :open="$showTagForm" close="$set('showTagForm', false)" submit-action="saveTag"
                      :title="($editingTagId ? 'Editar' : 'Agregar').' etiqueta'" icon="bi-tag" id="relationship-tag-dialog">
        <x-ui.field name="tagName" label="Nombre de la etiqueta" :required="true" wire:model="tagName" />
    </x-ui.form-dialog>

    {{-- Person dialog --}}
    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save"
                      :title="($editingId ? 'Editar' : 'Agregar').' persona'" icon="bi-person-plus" id="relationship-person-dialog"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-person', 'error' => $errors->hasAny(['fullName', 'birthdayDay', 'birthdayMonth', 'birthdayYear'])],
                          'contact' => ['label' => 'Contacto', 'icon' => 'bi-telephone', 'error' => $errors->has('documentNumber')],
                          'details' => ['label' => 'Detalles adicionales', 'icon' => 'bi-card-text'],
                      ]">
        <x-ui.form-dialog-section name="basic" title="Información básica">
            <div class="d-flex flex-column gap-3">
                <div class="md-field-pair">
                    <x-ui.field name="fullName" label="Nombre completo" :required="true" wire:model="fullName" />
                    <x-ui.field name="nickname" label="Apodo" wire:model="nickname" />
                </div>
                <div class="md-field-pair">
                    <x-ui.select name="circleId" label="Círculo" placeholder="Sin círculo" :options="$circles->pluck('name', 'id')->all()" :selected="$circleId" wire:model="circleId" />
                    <x-ui.select name="category" label="Categoría"
                                 :options="array_key_exists($category, $categories) ? $categories : $categories + [$category => ucfirst($category)]"
                                 :selected="$category" wire:model="category" />
                </div>
                <div class="md-field-trio">
                    <x-ui.select name="birthdayMonth" label="Mes de cumpleaños" placeholder="-" :selected="$birthdayMonth" wire:model="birthdayMonth"
                                 :options="collect(['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'])->mapWithKeys(fn ($month, $index) => [$index + 1 => ucfirst($month)])->all()" />
                    <x-ui.field name="birthdayDay" label="Día" type="number" min="1" max="31" wire:model="birthdayDay" />
                    <x-ui.field name="birthdayYear" label="Año (opcional)" type="number" min="1900" max="{{ now()->year }}" wire:model="birthdayYear" />
                </div>
                <div class="md-field-trio">
                    <x-ui.field name="pronouns" label="Pronombres" wire:model="pronouns" />
                    <x-ui.field name="occupation" label="Ocupación" wire:model="occupation" />
                    <x-ui.field name="organization" label="Organización" wire:model="organization" />
                </div>
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="contact" title="Contacto" description="Medios de contacto, ubicación y documento.">
            <div class="d-flex flex-column gap-3">
                <fieldset class="md-relationship-contacts">
                    <legend class="md-label-medium">Medios de contacto</legend>
                    @foreach ($contactMethods as $index => $method)
                        <div class="md-relationship-contacts__row" wire:key="contact-{{ $index }}">
                            <x-ui.select name="contactMethods.{{ $index }}.type" label="Tipo" id="contact-type-{{ $index }}"
                                         :options="\App\Models\RelationshipContactMethod::TYPES" :selected="$method['type'] ?? null"
                                         wire:model="contactMethods.{{ $index }}.type" />
                            <x-ui.field name="contactMethods.{{ $index }}.label" label="Etiqueta" id="contact-label-{{ $index }}" wire:model="contactMethods.{{ $index }}.label" />
                            <x-ui.field name="contactMethods.{{ $index }}.value" label="Valor" id="contact-value-{{ $index }}" wire:model="contactMethods.{{ $index }}.value" />
                            <label class="md-relationship-contacts__primary">
                                <input type="checkbox" wire:model="contactMethods.{{ $index }}.is_primary">
                                <span class="md-label-small">Principal</span>
                            </label>
                            <button type="button" wire:click="removeContactMethod({{ $index }})" class="md-btn-icon md-btn-danger"
                                    aria-label="Quitar medio" title="Quitar medio">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addContactMethod" class="md-btn-text mt-1">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar medio de contacto
                    </button>
                </fieldset>
                <div class="md-field-pair">
                    <x-ui.field name="address" label="Dirección" wire:model="address" />
                    <x-ui.field name="city" label="Ciudad" wire:model="city" />
                </div>
                <div class="md-field-pair">
                    <x-ui.select name="documentType" label="Tipo de documento" :options="\App\Models\Relationship::DOCUMENT_TYPES" :selected="$documentType" placeholder="Sin documento" icon="bi-person-vcard" wire:model="documentType" />
                    <x-ui.field name="documentNumber" label="Número de documento" autocomplete="off" wire:model="documentNumber" />
                </div>
                <x-ui.field name="contactFrequencyDays" label="Frecuencia de contacto propia (días)" type="number" min="1" wire:model="contactFrequencyDays" />
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="details" title="Detalles adicionales">
            <div class="d-flex flex-column gap-3">
                @if ($tags->isNotEmpty())
                    <fieldset class="md-relationship-tagset">
                        <legend class="md-label-medium">Etiquetas</legend>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($tags as $tag)
                                <label class="md-chip md-chip-filter {{ in_array($tag->id, $selectedTags, true) ? 'selected' : '' }}">
                                    <input type="checkbox" class="md-visually-hidden" value="{{ $tag->id }}" wire:model.live="selectedTags">
                                    {{ $tag->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <x-ui.textarea name="generalNotes" label="Notas generales" rows="4" wire:model="generalNotes" />
            </div>
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
</x-module-shell>
