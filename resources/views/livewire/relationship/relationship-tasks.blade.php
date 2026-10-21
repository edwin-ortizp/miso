<x-module-shell module="relationships" :title="$relationship->full_name"
                :tabs="\App\Support\Ui\Tabs\PersonTabs::for($relationship)" :back="\App\Support\Ui\Tabs\PersonTabs::back()">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Agregar tarea', 'icon' => 'bi-check2-square', 'action' => 'openTaskForm']" />
    </x-slot:actions>

    <x-ui.management-card id="relationship-tasks" title="Tareas relacionadas" icon="bi-list-check"
                          :count="'('.$tasks->total().' / '.$counts['all'].')'" search="search" search-placeholder="Buscar tareas"
                          :active-filters="$status !== 'pending' ? 1 : 0" :paginator="$tasks" noun="tareas">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Estado de las tareas">
                @foreach ($statusFilters as $key => $filter)
                    <x-ui.chip variant="filter" :icon="$filter['icon']" :selected="$status === $key" wire:click="setStatus('{{ $key }}')" wire:key="relationship-tasks-status-{{ $key }}">
                        {{ $filter['label'] }} ({{ $counts[$key] }})
                    </x-ui.chip>
                @endforeach
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $status !== 'all' ? ['key' => 'status', 'value' => null, 'label' => 'Estado: '.(\App\Livewire\Relationship\RelationshipTasks::STATUS_FILTERS[$status]['label'] ?? $status), 'icon' => 'bi-circle'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif

        @if ($tasks->isNotEmpty())
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col" class="md-table__check"><span class="visually-hidden">Completar</span></th>
                        <th scope="col">Tarea</th>
                        <th scope="col">Vence</th>
                        <th scope="col">Prioridad</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        @php
                            $overdue = ! $task->completed && $task->end_date && $task->end_date->copy()->startOfDay()->lt(today());
                            $editUrl = route('tasks.list', ['edit' => $task->id]);
                        @endphp
                        <tr wire:key="relationship-task-{{ $task->id }}" @class(['is-done' => $task->completed])>
                            <td class="md-table__check">@unless ($task->is_recurrent && ! $task->completed)<button type="button" class="md-table__check-button" wire:click="toggleTask('{{ $task->id }}')" aria-label="{{ $task->completed ? 'Reabrir' : 'Completar' }} {{ $task->title }}" title="{{ $task->completed ? 'Reabrir' : 'Completar' }}"><i class="bi {{ $task->completed ? 'bi-check-circle-fill' : 'bi-circle' }}" aria-hidden="true"></i></button>@endunless</td>
                            <td class="md-table__title">
                                <a href="{{ $editUrl }}" wire:navigate><x-ui.task-code :code="$task->code" /> {{ $task->title }}</a>
                                @if ($task->is_recurrent)
                                    <span class="md-table__meta">Recurrente</span>
                                @endif
                            </td>
                            <td class="md-table__date">{{ $task->end_date ? \App\Support\EventDate::day($task->end_date)->label() : 'Sin fecha' }}</td>
                            <td class="md-table__nowrap">{{ $priorities[$task->priority] ?? '—' }}</td>
                            <td class="md-table__nowrap">
                                @if ($task->completed)
                                    <span class="md-chip-tonal md-chip-tonal--success">Completada</span>
                                @elseif ($overdue)
                                    <span class="md-chip-tonal md-chip-tonal--error">Vencida</span>
                                @else
                                    <span class="md-chip-tonal">Por hacer</span>
                                @endif
                            </td>
                            <td class="md-table__actions">
                                <x-ui.menu size="sm" :label="'Acciones de '.$task->title">
                                    @unless ($task->is_recurrent && ! $task->completed)
                                        <x-ui.menu-item :icon="$task->completed ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'" wire:click="toggleTask('{{ $task->id }}')">{{ $task->completed ? 'Reabrir' : 'Completar' }}</x-ui.menu-item>
                                    @endunless
                                    <x-ui.menu-item icon="bi-pencil" :href="$editUrl" wire:navigate>Editar en Tareas</x-ui.menu-item>
                                    <x-ui.menu-divider />
                                    <x-ui.menu-item icon="bi-link-45deg" tone="danger" wire:click="unlinkTask('{{ $task->id }}')"
                                                    wire:confirm="Se quitará el vínculo con esta persona. La tarea se conserva en Tareas. ¿Continuar?">Quitar vínculo</x-ui.menu-item>
                                </x-ui.menu>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($counts['all'] > 0)
            <x-ui.state variant="filtered-empty" message="No hay tareas con estos filtros." />
        @else
            <x-ui.state variant="empty" icon="bi-list-check" :title="'Aún no hay tareas con '.$relationship->displayName()"
                        message="Agrega una tarea para recordar lo que quieres hacer por esta persona." />
        @endif
    </x-ui.management-card>

    @include('livewire.relationship.partials.task-dialog')
</x-module-shell>
