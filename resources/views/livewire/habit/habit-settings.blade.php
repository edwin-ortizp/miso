<x-module-shell module="habits" archetype="settings">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Nuevo hábito', 'icon' => 'bi-plus-lg', 'action' => 'openForm']" />
    </x-slot:actions>

    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    <x-ui.management-card id="habit-definitions" title="Tus hábitos" icon="bi-check2-square"
                          :count="'('.$habits->total().' / '.$totalCount.')'"
                          search="search" search-placeholder="Buscar hábito"
                          :active-filters="$timeFilter !== '' ? 1 : 0" :paginator="$habits" noun="hábitos">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtrar por momento del día">
                <x-ui.chip variant="filter" :selected="$timeFilter === ''" wire:click="$set('timeFilter', '')">Todos</x-ui.chip>
                @foreach ($timesOfDay as $key => $label)
                    <x-ui.chip variant="filter" :selected="$timeFilter === $key" wire:click="$set('timeFilter', '{{ $key }}')">{{ $label }}</x-ui.chip>
                @endforeach
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $timeFilter !== '' ? ['key' => 'timeFilter', 'value' => null, 'label' => 'Momento: '.($timesOfDay[$timeFilter] ?? $timeFilter), 'icon' => 'bi-clock'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="restoreDefaults">Restaurar hábitos predeterminados</x-ui.menu-item>
        </x-slot:menu>
        <p class="md-mcard__section-label">Un hábito puede además registrar algo en otro módulo al completarlo.</p>

        <x-ui.list label="Hábitos">
            @forelse ($habits as $habit)
                @php
                    $action = $habit->action && $registry->has($habit->action->action_key)
                        ? $registry->find($habit->action->action_key)
                        : null;
                    $supporting = collect([
                        $timesOfDay[$habit->time_of_day] ?? 'En cualquier momento',
                        $habit->base_time,
                        $habit->goal_duration,
                        $habit->completions_count ? $habit->completions_count.' '.($habit->completions_count === 1 ? 'día' : 'días') : 'Sin registros',
                    ])->filter()->implode(' · ');
                @endphp
                <x-ui.list-item :headline="$habit->name" :supporting="$supporting" wire:key="habit-{{ $habit->id }}">
                    <x-slot:leading>
                        <span class="md-list-icon-circle" aria-hidden="true">{{ $habit->icon ?: '✅' }}</span>
                    </x-slot:leading>
                    <x-slot:trailing>
                        @if ($action)
                            <x-ui.badge>
                                <i class="bi {{ $action::icon() }}" aria-hidden="true"></i>
                                {{ $action::moduleLabel() }}@if ($habit->action->mode === \App\Models\HabitAction::MODE_PROMPT) · pregunta @endif
                            </x-ui.badge>
                        @endif
                        <x-ui.row-actions :label="'Más acciones de '.$habit->name">
                            <x-slot:primary wire:click="openForm({{ $habit->id }})">Editar</x-slot:primary>
                            <x-ui.menu-divider />
                            <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete({{ $habit->id }})"
                                            wire:confirm="Se eliminará “{{ $habit->name }}” y su historial de completados.">Eliminar</x-ui.menu-item>
                        </x-ui.row-actions>
                    </x-slot:trailing>
                </x-ui.list-item>
            @empty
                @if ($totalCount === 0)
                    <x-ui.state variant="empty" icon="bi-check2-square" title="Aún no tienes hábitos"
                                message="Crea el tuyo o parte de los predeterminados para empezar a registrar tu día.">
                        <x-slot:actions>
                            <x-ui.action variant="filled" icon="bi-arrow-counterclockwise" wire:click="restoreDefaults">Agregar predeterminados</x-ui.action>
                        </x-slot:actions>
                    </x-ui.state>
                @else
                    <x-ui.state variant="filtered-empty" title="Ningún hábito coincide"
                                message="Ajusta la búsqueda o el momento del día para ver el resto." />
                @endif
            @endforelse
        </x-ui.list>
    </x-ui.management-card>

    @include('livewire.habit.habit-dialog')

</x-module-shell>
