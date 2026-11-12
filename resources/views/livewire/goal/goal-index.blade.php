@php
    use App\Support\Ui\DataState;

    $goalsState = DataState::resolve(
        visible: $goals->count(),
        total: $statusFilter === 'all' ? $goals->count() : 1,
    );

    $statusLabels = ['active' => 'Activos', 'completed' => 'Completados', 'abandoned' => 'Abandonados', 'all' => 'Todos'];
@endphp

<x-module-shell module="goals">

    <livewire:game.guardian-card module="goals" variant="top" />
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Agregar objetivo', 'icon' => 'bi-flag', 'action' => 'openForm']" />
    </x-slot:actions>

    <x-ui.metric-grid label="Resumen de objetivos">
        <x-ui.metric label="Activos" icon="bi-flag-fill" tone="primary" :value="$activeCount" />
        <x-ui.metric label="Completados" icon="bi-check-circle-fill" tone="success" :value="$completedCount" />
    </x-ui.metric-grid>

    <x-ui.management-card id="goals" title="Objetivos" icon="bi-flag" :count="'('.$goals->total().')'"
                          :active-filters="$statusFilter !== 'active' ? 1 : 0" :paginator="$goals" noun="objetivos"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Estado de los objetivos">
                @foreach ($statusLabels as $value => $label)
                    <x-ui.chip variant="filter" :selected="$statusFilter === $value" wire:click="$set('statusFilter', '{{ $value }}')">{{ $label }}</x-ui.chip>
                @endforeach
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $statusFilter !== 'all' ? ['key' => 'statusFilter', 'value' => null, 'label' => 'Estado: '.($statusLabels[$statusFilter] ?? $statusFilter), 'icon' => 'bi-flag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @if ($goalsState === DataState::CONTENT)
            <x-ui.list label="Objetivos">
                @foreach ($goals as $goal)
                    <x-ui.list-item :headline="$goal->title" :supporting="$goal->description" wire:key="goal-{{ $goal->id }}">
                        <x-slot:leading>
                            @if ($goal->status === 'active')
                                <x-ui.icon name="bi-flag-fill" tone="primary" label="Objetivo activo" />
                            @elseif ($goal->status === 'completed')
                                <x-ui.icon name="bi-check-circle-fill" tone="success" label="Objetivo completado" />
                            @else
                                <x-ui.icon name="bi-x-circle-fill" tone="muted" label="Objetivo abandonado" />
                            @endif
                        </x-slot:leading>

                        <div class="goal-card__meta">
                            @if ($goal->start_date)
                                <x-ui.chip variant="tonal" icon="bi-calendar">{{ $goal->start_date->format('d M Y') }}</x-ui.chip>
                            @endif
                            @if ($goal->due_date)
                                <x-ui.chip variant="tonal" icon="bi-calendar-event"
                                           :tone="$goal->due_date->isPast() && $goal->status === 'active' ? 'danger' : 'neutral'">
                                    {{ $goal->due_date->format('d M Y') }}
                                </x-ui.chip>
                            @endif
                        </div>

                        <x-slot:trailing>
                            <x-ui.row-actions :label="'Más acciones de '.$goal->title">
                                <x-slot:primary wire:click="openForm('{{ $goal->id }}')">Editar</x-slot:primary>
                                @if ($goal->status === 'active')
                                    <x-ui.menu-item icon="bi-check-circle" wire:click="updateStatus('{{ $goal->id }}', 'completed')">Completar</x-ui.menu-item>
                                    <x-ui.menu-item icon="bi-x-circle" wire:click="updateStatus('{{ $goal->id }}', 'abandoned')">Abandonar</x-ui.menu-item>
                                @else
                                    <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="updateStatus('{{ $goal->id }}', 'active')">Reactivar</x-ui.menu-item>
                                @endif
                                <x-ui.menu-divider />
                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $goal->id }}')"
                                                wire:confirm="¿Eliminar este objetivo?">Eliminar</x-ui.menu-item>
                            </x-ui.row-actions>
                        </x-slot:trailing>
                    </x-ui.list-item>
                @endforeach
            </x-ui.list>
        @elseif ($goalsState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" icon="bi-flag"
                        message="No hay objetivos con el estado «{{ $statusLabels[$statusFilter] ?? $statusFilter }}».">
                <x-slot:actions>
                    <x-ui.action variant="outlined" icon="bi-x-circle" wire:click="$set('statusFilter', 'all')">Ver todos</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @else
            <x-ui.state variant="empty" icon="bi-flag" title="Sin objetivos todavía"
                        message="¿Qué quieres lograr? Crea tu primer objetivo. *ya se emocionó*" />
        @endif
    </x-ui.management-card>

    @include('livewire.goal.goal-dialog')
    {{-- CHG-063: panel lateral con el guardián (solo si hay guardián; si no, el módulo se ve como antes). --}}
    @if (\App\Livewire\Game\GuardianCard::visibleFor((int) auth()->id(), 'goals'))
        <x-slot:rail>
            <x-context-widget title="Tu guardián" icon="bi-stars">
                <livewire:game.guardian-card module="goals" variant="rail" />
            </x-context-widget>
        </x-slot:rail>
    @endif
</x-module-shell>
