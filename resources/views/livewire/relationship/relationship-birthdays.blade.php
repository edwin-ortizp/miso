<x-module-shell module="relationships" title="Cumpleaños" subtitle="Los próximos doce meses, sin duplicar acontecimientos cada año."
                x-data="{ showTaskDialog: $wire.entangle('showTaskForm') }">
    <div class="md-summary-strip mb-3" aria-label="Resumen de cumpleaños">
        <span class="md-count-badge--info">{{ $totalWithBirthday }} con cumpleaños</span>
        @if ($todayCount)
            <span class="md-count-badge--warning">{{ $todayCount }} hoy</span>
        @endif
    </div>


    <x-ui.management-card id="relationship-birthdays" title="Próximos cumpleaños" icon="bi-cake2" :count="'('.$rows->total().')'"
                          :active-filters="$monthFilter !== '' ? 1 : 0" :paginator="$rows" noun="cumpleaños" alpine="openMenu: null">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros de cumpleaños" @click.outside="openMenu = null">
            <button wire:click="$set('monthFilter', '')"
                    class="md-chip md-chip-filter {{ $monthFilter === '' ? 'selected' : '' }}">Próximos 12 meses</button>

            <div class="md-chip-rail__divider"></div>

            @foreach ($monthNames as $number => $name)
                <button wire:click="$set('monthFilter', '{{ $number }}')"
                        class="md-chip md-chip-filter {{ (int) $monthFilter === $number ? 'selected' : '' }}">{{ $name }}</button>
            @endforeach
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $monthFilter !== '' ? ['key' => 'monthFilter', 'value' => null, 'label' => 'Mes: '.($monthNames[(int) $monthFilter] ?? $monthFilter), 'icon' => 'bi-calendar3'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
    @forelse ($rows as $row)
        <div class="md-card-outlined md-relationship-birthday mb-2" wire:key="birthday-{{ $row['relationship']->id }}">
            <div class="d-flex align-items-center gap-3">
                <div class="md-list-icon-circle" style="background: var(--md-custom-color-warning-container); color: var(--md-custom-color-on-warning-container);">
                    <i class="bi bi-cake2" style="font-size: 1rem;"></i>
                </div>
                <div>
                    <div class="md-title-small">
                        <a href="{{ route('relationships.show', $row['relationship']->id) }}" wire:navigate>{{ $row['relationship']->full_name }}</a>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-1 align-items-center">
                        <span class="md-label-small" style="color: var(--md-sys-color-on-surface-variant);">{{ $row['birthday']->label() }}</span>
                        @if ($row['is_today'])
                            <span class="md-chip-tonal md-chip-tonal--warning">Hoy</span>
                        @else
                            <span class="md-chip-tonal">en {{ $row['days_until'] }} días</span>
                        @endif
                        @if ($row['age'] !== null)
                            <span class="md-chip-tonal md-chip-tonal--info">Cumple {{ $row['age'] }}</span>
                        @endif
                        @if ($row['relationship']->circle)
                            <span class="md-chip-tonal">{{ $row['relationship']->circle->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <x-ui.row-actions :label="'Más acciones de '.$row['relationship']->full_name">
                <x-slot:primary wire:click="openTaskForm('{{ $row['relationship']->id }}')">Crear tarea</x-slot:primary>
                <x-ui.menu-item icon="bi-person" :href="route('relationships.show', $row['relationship']->id)" wire:navigate>Ver relación</x-ui.menu-item>
            </x-ui.row-actions>
        </div>
    @empty
        <x-empty-state icon="bi-cake2" title="Sin cumpleaños registrados"
                       message="Agrega el día y el mes de nacimiento desde el formulario de cada relación." />
    @endforelse
    </x-ui.management-card>

    <x-slot:rail>
        <x-context-widget title="Resumen" icon="bi-cake2" tone="warning">
            <dl class="md-context-list">
                <div><dt>Con cumpleaños</dt><dd>{{ $totalWithBirthday }}</dd></div>
                <div><dt>Hoy</dt><dd>{{ $todayCount }}</dd></div>
                <div><dt>En la vista</dt><dd>{{ $rows->count() }}</dd></div>
            </dl>
        </x-context-widget>

        <x-context-widget title="Cómo funciona" icon="bi-info-circle">
            <p class="md-body-small mb-0">
                Los cumpleaños se calculan desde el perfil de cada persona: no se crean acontecimientos nuevos
                cada año. La edad solo aparece cuando conoces el año de nacimiento.
            </p>
        </x-context-widget>

        <x-context-widget title="Enlaces" icon="bi-signpost-split">
            <div class="md-context-links">
                <a href="{{ route('relationships') }}" wire:navigate><i class="bi bi-people"></i> Relaciones</a>
                <a href="{{ route('relationships.events') }}" wire:navigate><i class="bi bi-calendar-event"></i> Acontecimientos</a>
            </div>
        </x-context-widget>
    </x-slot:rail>

    <template x-if="showTaskDialog">
        <div>
            <div class="md-dialog-scrim" @click="showTaskDialog = false"></div>
            <div class="md-dialog" @click.stop>
                <h2 class="md-dialog-headline md-headline-small">Tarea desde un cumpleaños</h2>
                <div class="md-dialog-content">
                    <div class="d-flex flex-column gap-3">
                        <div class="md-text-field">
                            <input type="text" wire:model="taskTitle" placeholder=" " id="birthday-task-title">
                            <label for="birthday-task-title">Título</label>
                        </div>
                        @error('taskTitle') <p class="md-body-small" style="color: var(--md-sys-color-error);">{{ $message }}</p> @enderror

                        <div class="md-text-field">
                            <input type="date" wire:model="taskDueDate" placeholder=" " id="birthday-task-date">
                            <label for="birthday-task-date">Fecha</label>
                        </div>
                        @error('taskDueDate') <p class="md-body-small" style="color: var(--md-sys-color-error);">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="md-dialog-actions">
                    <button @click="showTaskDialog = false" class="md-btn-text">Cancelar</button>
                    <button wire:click="saveTask" class="md-btn-filled"><i class="bi bi-check-lg"></i> Crear tarea</button>
                </div>
            </div>
        </div>
    </template>
</x-module-shell>
