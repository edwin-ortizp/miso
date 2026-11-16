<x-module-shell :module="$module" title="Amigos" archetype="list">
    <x-ui.card title="Esta semana" icon="bi-calendar-week">
        <x-ui.switch name="social_visible" label="Visible para mis amigos" help="Tus amigos solo verán tu nombre y avatar, nada más." :checked="$visible" wire:click="toggleVisible" />
        <x-ui.switch name="social_leaderboard" label="Mostrar mi semana" help="Tus amigos verán solo cuántas acciones hiciste, nada más." :checked="$enabled" :disabled="! $visible" wire:click="toggleLeaderboard" />

        @if (! $visible || ! $enabled)
            <x-ui.state variant="empty" icon="bi-calendar-week" title="Activa tu semana para verla" message="Enciende «Visible para mis amigos» y «Mostrar mi semana». Solo verás a quienes también la comparten." />
        @elseif (count($rows) <= 1)
            <x-ui.state variant="empty" icon="bi-people" title="Invita a un amigo" message="Cuando un amigo también muestre su semana, aparecerá aquí." />
        @else
            <ol aria-label="Acciones de la semana por persona">
                @foreach ($rows as $row)
                    <li wire:key="week-{{ $row['id'] }}" @class(['md-title-small' => $row['me']])>
                        <x-social.avatar :name="$row['name']" :url="$row['avatar_url']" />
                        {{ $row['me'] ? 'Tú' : $row['name'] }}
                        <span class="md-body-medium">{{ $row['actions'] === 1 ? '1 acción' : $row['actions'].' acciones' }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="md-body-small">Semana nueva cada lunes, sin arrastre. Cada acción suma.</p>
        @endif
    </x-ui.card>
</x-module-shell>
