@php
    use App\Models\TaskStatus;
    use App\Support\Ui\DataState;

    $state = DataState::resolve($paginator->total(), $catalogTotal);
    $tones = [
        TaskStatus::TODO => 'neutral',
        TaskStatus::READY => 'info',
        TaskStatus::IN_PROGRESS => 'warning',
        TaskStatus::DONE => 'success',
    ];
@endphp

<x-ui.management-card id="task-settings-statuses" title="Estados de tareas" icon="bi-kanban"
                      :count="$paginator->total()" search="q" search-placeholder="Buscar estado"
                      :paginator="$paginator" noun="estados">
    @if ($state === DataState::CONTENT)
        <table class="md-table md-table--stack" aria-label="Catálogo de estados de tareas">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Tareas</th>
                    <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paginator as $status)
                    @php
                        $total = (int) ($statusCounts[$status->id] ?? 0);
                        $locked = $status->type === TaskStatus::DONE
                            || ($status->type === TaskStatus::TODO && $todoStatusCount === 1);
                    @endphp
                    <tr wire:key="task-status-{{ $status->id }}">
                        <td data-label="Nombre" class="md-table__title"><x-ui.catalog-chip :color="$status->color" :icon="$status->icon">{{ $status->name }}</x-ui.catalog-chip></td>
                        <td data-label="Tipo" class="md-table__nowrap"><x-ui.chip variant="tonal" :tone="$tones[$status->type] ?? 'neutral'" :icon="$status->icon">{{ $status->type_label }}</x-ui.chip></td>
                        <td data-label="Tareas" class="md-table__nowrap">{{ $total ?: 'Sin tareas' }}</td>
                        <td data-label="Acciones" class="md-table__actions">
                            <x-ui.row-actions :label="'Acciones de '.$status->name">
                                <x-slot:primary wire:click="openStatusForm('{{ $status->id }}')">Editar</x-slot:primary>
                                @if ($canMoveUp[$status->id] ?? false)
                                    <x-ui.menu-item icon="bi-arrow-up" wire:click="moveStatus('{{ $status->id }}', -1)">Subir</x-ui.menu-item>
                                @endif
                                @if ($canMoveDown[$status->id] ?? false)
                                    <x-ui.menu-item icon="bi-arrow-down" wire:click="moveStatus('{{ $status->id }}', 1)">Bajar</x-ui.menu-item>
                                @endif
                                @unless ($locked)
                                    <x-ui.menu-divider />
                                    @if ($total)
                                        <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteStatus('{{ $status->id }}')">Eliminar</x-ui.menu-item>
                                    @else
                                        <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteStatus('{{ $status->id }}')"
                                                        wire:confirm="«{{ $status->name }}» no tiene tareas; se eliminará de tus estados.">Eliminar</x-ui.menu-item>
                                    @endif
                                @endunless
                            </x-ui.row-actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($state === DataState::FILTERED_EMPTY)
        <x-ui.state variant="filtered-empty" message="Prueba otra búsqueda para encontrar tu estado." />
    @else
        <x-ui.state variant="empty" icon="bi-kanban" title="Aún no tienes estados"
                    message="Crea el primero desde el botón flotante para organizar tu trabajo." />
    @endif
</x-ui.management-card>

<x-ui.form-dialog :open="$showStatusForm" close="closeStatusForm" submit-action="saveStatus" id="task-status-dialog"
                  :title="$editingStatusId ? 'Editar estado' : 'Nuevo estado'" icon="bi-kanban"
                  :submit="$editingStatusId ? 'Actualizar' : 'Crear estado'">
    <div class="d-flex flex-column gap-3">
        <x-ui.field name="statusName" label="Nombre" :required="true" maxlength="60" wire:model="statusName" />
        <x-ui.catalog-color-select name="statusColor" :selected="$statusColor" :preview="$statusName" :icon="\App\Models\TaskStatus::TYPES[$statusType]['icon'] ?? 'bi-circle'" />
        @if ($editingStatus && $editingStatus->type === TaskStatus::DONE)
            <p class="md-body-small mb-0">Tipo: {{ $statusTypes[TaskStatus::DONE] }}. Es el único estado terminado y no cambia de tipo.</p>
        @else
            <x-ui.select name="statusType" label="Tipo" :selected="$statusType" :options="$editableStatusTypes" wire:model="statusType" />
        @endif
    </div>
</x-ui.form-dialog>

<x-ui.form-dialog :open="(bool) $deletingStatus" close="cancelStatusDelete" submit-action="reassignAndDeleteStatus" id="task-status-delete-dialog"
                  :title="$deletingStatus ? 'Eliminar «'.$deletingStatus->name.'»' : 'Eliminar estado'" icon="bi-exclamation-triangle"
                  submit="Eliminar estado">
    @if ($deletingStatus)
        <div class="d-flex flex-column gap-3">
            <p class="md-body-medium mb-0">
                Hay {{ $deletingStatusCount }} {{ $deletingStatusCount === 1 ? 'tarea' : 'tareas' }} en este estado.
                Elige a cuál pasarlas.
            </p>
            <x-ui.select name="statusReassignTo" label="Pasar tareas a" placeholder="Elige un estado"
                         :selected="$statusReassignTo" :options="$statusReassignOptions" wire:model="statusReassignTo" />
        </div>
    @endif
</x-ui.form-dialog>
