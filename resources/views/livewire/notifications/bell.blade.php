<div x-data="{ inboxOpen: $wire.entangle('open').live, filtersOpen: false }">
    <button type="button" class="md-btn-icon lt-topbar__bell" x-on:click="inboxOpen = true" aria-label="Notificaciones: {{ $unread }} sin leer">
        <i class="bi bi-bell" aria-hidden="true"></i>
        @if ($unread)<x-ui.badge placement="corner">{{ $unread > 99 ? '99+' : $unread }}</x-ui.badge>@endif
    </button>
    @teleport('body')
        <div data-module="settings" class="notification-inbox">
            <x-ui.sheet state="inboxOpen" title="Notificaciones" placement="side" persistent>
                <div class="md-filter-bar" x-data="{ filtersOpen: false }">
                    <x-ui.action variant="outlined" icon="bi-funnel" data-popover-trigger x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen">Filtros</x-ui.action>
                    <x-ui.popover state="filtersOpen" title="Filtros de notificaciones">
                        <x-ui.select name="reading" label="Lectura" wire:model="reading" :options="['all' => 'Todas', 'unread' => 'Sin leer', 'read' => 'Leídas']" />
                        <x-ui.select name="type" label="Tipo" wire:model="type" :options="['' => 'Todos', 'birthday' => 'Cumpleaños', 'task_due' => 'Tareas', 'plan_due' => 'Planes', 'reminder' => 'Recordatorios']" />
                        <x-slot:actions>
                            <x-ui.action variant="outlined" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                            <x-ui.action wire:click="applyFilters" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
                        </x-slot:actions>
                    </x-ui.popover>
                </div>
                <div wire:loading><x-ui.state variant="loading" title="Cargando notificaciones…" /></div>
                @if ($notifications)
                    @forelse ($notifications as $notification)
                        <x-ui.card wire:key="notification-{{ $notification->id }}" :title="$notification->title" icon="bi-bell">
                            <p>{{ $notification->body }}</p>
                            <p><time datetime="{{ $notification->scheduled_for->toIso8601String() }}">{{ $notification->scheduled_for->timezone('America/Bogota')->format('d/m/Y H:i') }}</time></p>
                            <x-ui.action variant="text" :href="$notification->destination()" wire:navigate>Ver elemento</x-ui.action>
                            @unless ($notification->read_at)
                                <x-ui.action variant="text" wire:click="markRead('{{ $notification->id }}')">Marcar leída</x-ui.action>
                            @else
                                <span>Leída</span>
                            @endunless
                        </x-ui.card>
                    @empty
                        <x-ui.state :variant="$reading !== 'all' || $type !== '' ? 'filtered-empty' : 'empty'" :title="$reading !== 'all' || $type !== '' ? 'No hay notificaciones con estos filtros.' : 'No tienes notificaciones.'" />
                    @endforelse
                    {{ $notifications->links() }}
                @endif
                <x-slot:actions>
                    <x-ui.action variant="text" wire:click="markAllRead">Marcar todas las filtradas como leídas</x-ui.action>
                    <x-ui.action variant="text" :href="route('settings.section', 'notifications')" wire:navigate>Ajustes</x-ui.action>
                </x-slot:actions>
            </x-ui.sheet>
        </div>
    @endteleport
</div>
