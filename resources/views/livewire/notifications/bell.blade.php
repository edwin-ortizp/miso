<div class="notification-bell-shell" x-data="{ inboxOpen: $wire.entangle('open').live, filtersOpen: false }" x-on:keydown.escape.window="if (!inboxOpen) filtersOpen = false">
    <x-ui.icon-action icon="bi-bell" size="lg" :tone="$attentionTone" class="lt-topbar__bell" :label="'Notificaciones: '.$unread.' sin leer'" x-on:click="inboxOpen = true" />
    @if ($unread)<x-ui.badge tone="danger" placement="corner" class="notification-bell-badge" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</x-ui.badge>@endif
    @teleport('body')
        <div data-module="settings" class="notification-inbox" wire:key="notification-inbox" x-effect="if (!inboxOpen) filtersOpen = false" x-init="$watch('filtersOpen', value => { if (!value &amp;&amp; inboxOpen) $nextTick(() => $el.querySelector('[data-notification-filter-trigger]')?.focus()); })">
            <x-ui.sheet state="inboxOpen" title="Notificaciones" placement="side" persistent x-on:keydown.escape.capture="if (filtersOpen) { $event.stopPropagation(); filtersOpen = false; $el.querySelector('[data-notification-filter-trigger]')?.focus(); }">
                <div class="notification-toolbar">
                    <span class="notification-summary">{{ $unread }} sin leer<br><small>Más recientes primero</small></span>
                    <x-ui.action variant="outlined" size="lg" icon="bi-funnel" data-popover-trigger data-notification-filter-trigger x-on:click="if (!filtersOpen) { await $wire.prepareFilters(); } filtersOpen = !filtersOpen; if (filtersOpen) $nextTick(() => $el.closest('.md-sheet').querySelector('select[name=notification-reading]')?.focus())" x-bind:aria-expanded="filtersOpen" aria-controls="notification-filters">Filtros @if ($filterCount)<x-ui.badge placement="corner">{{ $filterCount }}</x-ui.badge>@endif</x-ui.action>
                    <x-ui.popover wire:key="notification-filters-surface" id="notification-filters" state="filtersOpen" title="Filtros de notificaciones">
                        <x-ui.select name="notification-reading" label="Lectura" wire:model="draftReading" :options="\App\Livewire\Notifications\NotificationBell::READING" />
                        <x-ui.select name="notification-type" label="Tipo" wire:model="draftType" :options="['' => 'Todos'] + \App\Livewire\Notifications\NotificationBell::TYPES" />
                        <x-slot:actions>
                            <x-ui.action variant="outlined" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                            <x-ui.action wire:click="applyFilters" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
                        </x-slot:actions>
                    </x-ui.popover>
                </div>
                @if ($filterCount)
                    <div class="notification-filters" aria-label="Filtros aplicados">
                        @if ($reading !== 'all')<x-ui.chip class="notification-filter-chip" variant="input" icon="bi-x" wire:click="removeFilter('reading')" :aria-label="'Quitar filtro: '.\App\Livewire\Notifications\NotificationBell::READING[$reading]">{{ \App\Livewire\Notifications\NotificationBell::READING[$reading] }}</x-ui.chip>@endif
                        @if ($type !== '' && isset(\App\Livewire\Notifications\NotificationBell::TYPES[$type]))<x-ui.chip class="notification-filter-chip" variant="input" icon="bi-x" wire:click="removeFilter('type')" :aria-label="'Quitar filtro: '.\App\Livewire\Notifications\NotificationBell::TYPES[$type]">{{ \App\Livewire\Notifications\NotificationBell::TYPES[$type] }}</x-ui.chip>@endif
                        <x-ui.action variant="text" size="lg" wire:click="clearFilters">Limpiar filtros</x-ui.action>
                    </div>
                @endif
                <div wire:loading.delay role="status" class="notification-loading">Actualizando notificaciones…</div>
                @if ($loadError)
                    <x-ui.state variant="error" title="No pudimos cargar las notificaciones">
                        <x-slot:actions><x-ui.action wire:click="$refresh">Reintentar</x-ui.action></x-slot:actions>
                    </x-ui.state>
                @elseif ($notifications)
                    <ul class="notification-list" aria-label="Avisos">
                        @forelse ($notifications as $notification)
                            @php($row = $presentation[$notification->id])
                            <li wire:key="notification-{{ $notification->id }}" class="notification-row notification-row--{{ $row['tone'] }} {{ ! $notification->read_at ? 'notification-row--unread' : '' }}">
                                <i class="bi {{ $row['icon'] }} notification-row__icon" aria-hidden="true"></i>
                                <a class="notification-row__link" href="{{ $row['destination'] }}" wire:navigate>
                                    <span class="notification-row__title">{{ $row['title'] }}</span>
                                    <span class="notification-row__context">{{ $row['context'] }}</span>
                                    @if ($row['body'])<span class="notification-row__body">{{ $row['body'] }}</span>@endif
                                    <span class="notification-row__date">Aviso: <time datetime="{{ $row['noticeIso'] }}">{{ $row['noticeDate'] }}</time> · {{ $notification->read_at ? 'Leída' : 'Sin leer' }}</span>
                                </a>
                                @unless ($notification->read_at)
                                    <x-ui.icon-action icon="bi-check2" size="lg" label="Marcar como leída" wire:click="markRead('{{ $notification->id }}')" wire:loading.attr="disabled" wire:target="markRead('{{ $notification->id }}')" />
                                @endunless
                            </li>
                        @empty
                            <li><x-ui.state :variant="$filterCount ? 'filtered-empty' : 'empty'" :title="$filterCount ? 'No hay notificaciones con estos filtros.' : 'No tienes notificaciones.'" /></li>
                        @endforelse
                    </ul>
                    @if ($notifications->hasPages())
                        <nav class="notification-pagination" aria-label="Páginas de notificaciones">
                            <x-ui.icon-action icon="bi-chevron-left" size="lg" label="Página anterior" wire:click="previousPage('notificationsPage')" :disabled="$notifications->onFirstPage()" />
                            <span aria-live="polite">Página {{ $notifications->currentPage() }} de {{ $notifications->lastPage() }}</span>
                            <x-ui.icon-action icon="bi-chevron-right" size="lg" label="Página siguiente" wire:click="nextPage('notificationsPage')" :disabled="! $notifications->hasMorePages()" />
                        </nav>
                    @endif
                @endif
                <x-slot:actions>
                    <div class="notification-footer"><x-ui.action variant="text" size="lg" wire:click="markAllRead" wire:loading.attr="disabled" :disabled="! $unread || $loadError">{{ $filterCount ? 'Marcar filtradas leídas' : 'Marcar todas leídas' }}</x-ui.action>
                    <x-ui.action variant="text" size="lg" :href="route('settings.section', 'notifications')" wire:navigate>Ajustes</x-ui.action></div>
                </x-slot:actions>
            </x-ui.sheet>
        </div>
    @endteleport
</div>
