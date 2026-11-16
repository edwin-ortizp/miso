<x-module-shell :module="$module" title="Amigos" archetype="list">
    <x-slot:actions><x-ui.fab label="Agregar amigo" icon="bi-person-plus" action="openAdd" :module="$module" /></x-slot:actions>
    @if ($message)<p role="status">{{ $message }}</p>@endif

    <x-ui.card title="Mi código" icon="bi-qr-code">
        <p class="md-title-large" aria-label="Tu código de invitación">MISO-{{ $code }}</p>
        <p class="md-body-small">Comparte este código o enlace: {{ $inviteUrl }}</p>
        <p class="md-body-medium">
            Usuario: {{ $this->username !== '' ? '@'.$this->username : 'sin elegir' }}
            <x-ui.action variant="text" size="sm" wire:click="openUsername">{{ auth()->user()->username ? 'Cambiar' : 'Elegir' }}</x-ui.action>
            <x-ui.action variant="text" size="sm" wire:click="regenerateCode" wire:confirm="Tu código anterior dejará de funcionar. ¿Generar uno nuevo?">Generar código nuevo</x-ui.action>
        </p>
        <x-ui.switch name="social_visible" label="Visible para mis amigos" help="Tus amigos solo verán tu nombre y avatar, nada más." :checked="$visible" wire:click="toggleVisible" />
    </x-ui.card>

    @if ($incoming->isNotEmpty())
        <x-ui.card title="Solicitudes" icon="bi-envelope-heart">
            <ul>@foreach ($incoming as $row)
                <li wire:key="in-{{ $row['friendship_id'] }}">
                    <x-social.avatar :name="$row['name']" :url="$row['avatar_url']" />
                    {{ $row['name'] }}@if($row['username']) <span class="md-body-small">&#64;{{ $row['username'] }}</span>@endif
                    <x-ui.action variant="tonal" size="sm" wire:click="accept({{ $row['friendship_id'] }})">Aceptar</x-ui.action>
                    <x-ui.action variant="text" size="sm" wire:click="decline({{ $row['friendship_id'] }})">Rechazar</x-ui.action>
                </li>
            @endforeach</ul>
        </x-ui.card>
    @endif

    <x-ui.card title="Mis amigos" icon="bi-people">
        @forelse ($friendList as $row)
            <div wire:key="friend-{{ $row['friendship_id'] }}" class="md-body-large">
                <x-social.avatar :name="$row['name']" :url="$row['avatar_url']" />
                {{ $row['name'] }}@if($row['username']) <span class="md-body-small">&#64;{{ $row['username'] }}</span>@endif
                <x-ui.row-actions :label="'Acciones de '.$row['name']">
                    <x-slot:primary wire:click="remove({{ $row['friendship_id'] }})" wire:confirm="¿Eliminar esta amistad?">Eliminar</x-slot:primary>
                    <x-ui.menu-item icon="bi-slash-circle" tone="danger" wire:click="block({{ $row['friendship_id'] }})" wire:confirm="¿Bloquear a esta persona?">Bloquear</x-ui.menu-item>
                </x-ui.row-actions>
                @if ($row['streak'])
                    <x-social.shared-streak :streak="$row['streak']" :name="$row['name']" />
                @endif
            </div>
        @empty
            <x-ui.state variant="empty" icon="bi-people" title="Aún no tienes amigos" message="Comparte tu código para empezar." />
        @endforelse
        @if ($friendList->isNotEmpty() && ! $visible)
            <p class="md-body-small">La racha compartida aparece cuando tú y tu amigo activan «Visible para mis amigos».</p>
        @endif
    </x-ui.card>

    @if ($outgoing->isNotEmpty())
        <x-ui.card title="Enviadas" icon="bi-send">
            <ul>@foreach ($outgoing as $row)
                <li wire:key="out-{{ $row['friendship_id'] }}">{{ $row['name'] }}
                    <x-ui.action variant="text" size="sm" wire:click="decline({{ $row['friendship_id'] }})">Cancelar</x-ui.action></li>
            @endforeach</ul>
        </x-ui.card>
    @endif

    @if ($blockedList->isNotEmpty())
        <x-ui.card title="Bloqueados" icon="bi-slash-circle">
            <ul>@foreach ($blockedList as $row)
                <li wire:key="blk-{{ $row['friendship_id'] }}">{{ $row['name'] }}
                    <x-ui.action variant="text" size="sm" wire:click="unblock({{ $row['friendship_id'] }})">Desbloquear</x-ui.action></li>
            @endforeach</ul>
        </x-ui.card>
    @endif

    <x-ui.form-dialog :module="$module" :open="$showAdd" close="closeAdd" submit-action="sendRequest" submit="Enviar solicitud" id="friend-add" title="Agregar amigo" icon="bi-person-plus">
        <x-ui.field name="lookup" label="Código o @usuario" wire:model="lookup" :required="true" maxlength="40" />
    </x-ui.form-dialog>
    <x-ui.form-dialog :module="$module" :open="$showUsername" close="closeUsername" submit-action="saveUsername" id="friend-username" title="Nombre de usuario" icon="bi-at">
        <x-ui.field name="username" label="Nombre de usuario" wire:model="username" :required="true" maxlength="20" />
        <p class="md-body-small">De 3 a 20 letras minúsculas, números o guion bajo.</p>
    </x-ui.form-dialog>
</x-module-shell>
