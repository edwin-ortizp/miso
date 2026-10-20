@php
    use App\Support\DefaultExerciseTypes;
@endphp

<x-module-shell module="exercise" archetype="settings">
    <x-slot:actions>
        <x-module-actions
            :primary="['label' => 'Nuevo tipo', 'icon' => 'bi-plus-lg', 'action' => 'openForm']"
 />
    </x-slot:actions>

    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    <nav aria-label="Ajustes de categorías"><x-ui.action variant="text" icon="bi-diagram-3" :href="route('categories.settings', 'exercise')">Gestionar categorías</x-ui.action></nav>
    <x-ui.management-card id="exercise-types" title="Tipos de ejercicio" icon="bi-tags" :count="'('.$types->total().' / '.$totalCount.')'"
                          search="search" search-placeholder="Buscar tipo de ejercicio" :active-filters="$category !== '' ? 1 : 0"
                          :paginator="$types" noun="tipos">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtrar por categoría">
                <x-ui.chip variant="filter" :selected="$category === ''" wire:click="$set('category', '')">Todas</x-ui.chip>
                @foreach ($categories as $key => $label)
                    <x-ui.chip variant="filter" :selected="$category === $key" wire:click="$set('category', '{{ $key }}')">{{ $label }}</x-ui.chip>
                @endforeach
                <x-ui.chip variant="filter" :selected="$category === 'none'" wire:click="$set('category', 'none')">Sin categoría</x-ui.chip>
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $category !== '' ? ['key' => 'category', 'value' => null, 'label' => 'Categoría: '.($category === 'none' ? 'Sin categoría' : ($categories[$category] ?? $category)), 'icon' => 'bi-tag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="restoreDefaults">Restaurar tipos predeterminados</x-ui.menu-item>
        </x-slot:menu>
        <p class="md-mcard__section-label">{{ $usedCount }} con registros · las calorías y pasos por hora se usan para estimar cada registro</p>

    @forelse ($groups as $key => $types)
        <x-ui.section :title="DefaultExerciseTypes::categoryLabel($key ?: null)" :level="3"
                      description="{{ $types->count() }} {{ $types->count() === 1 ? 'tipo' : 'tipos' }}"
                      wire:key="exercise-category-{{ $key ?: 'none' }}">
            <x-ui.list label="Tipos de {{ DefaultExerciseTypes::categoryLabel($key ?: null) }}">
                @foreach ($types as $type)
                    @php
                        $usage = $type->logs_count
                            ? $type->logs_count.' '.($type->logs_count === 1 ? 'registro' : 'registros').' · último '.\Carbon\Carbon::parse($type->logs_max_date)->translatedFormat('d M')
                            : 'Sin registros';
                        $rates = collect([
                            number_format($type->calories_per_hour, 0, ',', '.').' kcal/h',
                            $type->steps_equivalent ? number_format($type->steps_equivalent, 0, ',', '.').' pasos/h' : null,
                        ])->filter()->implode(' · ');
                    @endphp
                    <x-ui.list-item :headline="$type->name" :supporting="$rates.' · '.$usage" wire:key="exercise-type-{{ $type->id }}">
                        <x-slot:leading>
                            <span class="md-list-icon-circle" aria-hidden="true">{{ $type->icon ?: '🏃' }}</span>
                        </x-slot:leading>
                        <x-slot:trailing>
                            <x-ui.row-actions :label="'Más acciones de '.$type->name">
                                <x-slot:primary wire:click="openForm('{{ $type->id }}')">Editar</x-slot:primary>
                                <x-ui.menu-divider />
                                @if ($type->logs_count)
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="confirmDelete('{{ $type->id }}')">Eliminar</x-ui.menu-item>
                                @else
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $type->id }}')"
                                                    wire:confirm="“{{ $type->name }}” no tiene registros; se eliminará de tu catálogo.">Eliminar</x-ui.menu-item>
                                @endif
                            </x-ui.row-actions>
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        </x-ui.section>
    @empty
        @if ($totalCount === 0)
            <x-ui.state variant="empty" icon="bi-tags" title="Aún no tienes tipos de ejercicio"
                        message="Agrega los tipos predeterminados o crea el tuyo para empezar a registrar.">
                <x-slot:actions>
                    <x-ui.action variant="filled" icon="bi-arrow-counterclockwise" wire:click="restoreDefaults">Agregar predeterminados</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @else
            <x-ui.state variant="filtered-empty" title="Ningún tipo coincide"
                        message="Ajusta la búsqueda o la categoría para ver el resto de tu catálogo." />
        @endif
    @endforelse
    </x-ui.management-card>

    <x-slot:rail>
        <x-context-widget title="Meta diaria" icon="bi-bullseye">
            <p class="md-headline-small mb-0"><strong>{{ number_format((int) auth()->user()->daily_exercise_minutes ?: \App\Support\ExerciseProgress::DEFAULT_DAILY_MINUTES, 0, ',', '.') }}</strong> <span class="md-body-medium">min activos al día</span></p>
            <p class="md-body-small mb-3">La OMS recomienda al menos 30 min diarios.</p>
            <form wire:submit="saveGoal" class="d-grid gap-2" novalidate>
                <x-ui.field name="dailyExerciseMinutes" label="Minutos activos (5–600)" type="number" min="5" max="600" step="5" wire:model="dailyExerciseMinutes" />
                <div class="d-flex justify-content-end">
                    <button type="submit" class="md-btn-filled"><i class="bi bi-floppy" aria-hidden="true"></i><span>Guardar</span></button>
                </div>
            </form>
        </x-context-widget>
    </x-slot:rail>

    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="exercise-type-dialog"
                      :title="$editingId ? 'Editar tipo de ejercicio' : 'Nuevo tipo de ejercicio'" icon="bi-tags"
                      :submit="$editingId ? 'Actualizar' : 'Crear tipo'">
        <div class="d-flex flex-column gap-3">
            <div class="md-field-pair">
                <x-ui.field name="icon" label="Emoji" maxlength="16" wire:model="icon" />
                <x-ui.field name="name" label="Nombre" :required="true" maxlength="60" wire:model="name" />
            </div>
            <x-ui.category-select name="formCategory" label="Categoría" placeholder="Sin categoría" :selected="$formCategory"
                         :options="$categories" wire:model="formCategory" />
            <div class="md-field-pair">
                <x-ui.field name="caloriesPerHour" label="Calorías por hora" type="number" min="0" :required="true" wire:model="caloriesPerHour" />
                <x-ui.field name="stepsEquivalent" label="Pasos por hora" type="number" min="0" wire:model="stepsEquivalent" />
            </div>
            <p class="md-body-small mb-0">
                @if ($editingId)
                    Cambiar estas tasas solo afecta a los registros nuevos.
                @else
                    Se usan para estimar calorías y pasos al registrar solo la duración.
                @endif
            </p>
        </div>
    </x-ui.form-dialog>

    <x-ui.form-dialog :open="(bool) $deleting" close="cancelDelete" submit-action="reassignAndDelete" id="exercise-type-delete-dialog"
                      :title="$deleting ? 'Eliminar «'.$deleting->name.'»' : 'Eliminar tipo'" icon="bi-exclamation-triangle"
                      submit="Reasignar y eliminar">
        @if ($deleting)
            <div class="d-flex flex-column gap-3">
                <p class="md-body-medium mb-0">
                    Hay {{ $deleting->logs_count }} {{ $deleting->logs_count === 1 ? 'registro' : 'registros' }} con este tipo.
                    Elige a qué tipo pasarlos: tu historial y tus estadísticas se conservan.
                </p>
                <x-ui.select name="reassignTo" label="Reasignar registros a" placeholder="Seleccionar..." :required="true"
                             :selected="$reassignTo" :options="$reassignOptions" wire:model="reassignTo" />
            </div>
        @endif
    </x-ui.form-dialog>
</x-module-shell>
