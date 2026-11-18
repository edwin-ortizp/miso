@php
    $initials = \Illuminate\Support\Str::of($relationship->full_name)->explode(' ')->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('');
    $alias = $relationship->nickname ?: $relationship->aliases->first()?->alias;
    $identity = collect([$alias ? '“'.$alias.'”' : null, $relationship->circle?->name])->filter()->implode(' · ');
    $toneClass = fn (string $tone) => 'md-chip-tonal'.($tone === 'neutral' ? '' : ' md-chip-tonal--'.($tone === 'danger' ? 'error' : $tone));
    $createAction = ['label' => 'Registrar acontecimiento', 'icon' => 'bi-calendar-plus', 'action' => 'openEventForm'];
    $createTaskAction = ['label' => 'Agregar tarea', 'icon' => 'bi-check2-square', 'action' => 'openTaskForm', 'create' => true];
@endphp

<x-module-shell module="relationships" :title="$relationship->full_name"
                :tabs="\App\Support\Ui\Tabs\PersonTabs::for($relationship)" :back="\App\Support\Ui\Tabs\PersonTabs::back()">
    <x-slot:actions>
        <x-module-actions
            :primary="$createAction"
            :secondary="[$createTaskAction]" />
    </x-slot:actions>

    <div class="md-relationship-home">
        <x-ui.management-card id="relationship-agenda" :create-actions="[$createTaskAction, $createAction]" title="Pendientes y próximos" icon="bi-list-check">
            <x-slot:headerAction>
                <a href="{{ route('relationships.tasks', $relationship) }}" class="md-btn-text" wire:navigate>Ver todas</a>
            </x-slot:headerAction>

            @if ($agenda->isEmpty())
                <x-ui.state variant="empty" icon="bi-calendar-check" :title="'Nada pendiente con '.$relationship->displayName()"
                            message="Las tareas, acontecimientos, planes y cumpleaños próximos aparecerán aquí." />
            @else
                <table class="md-table md-table--stack">
                    <thead>
                        <tr>
                            <th scope="col" class="md-table__check"><span class="visually-hidden">Completar</span></th>
                            <th scope="col">Tarea / Acontecimiento</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agenda as $item)
                            <tr wire:key="agenda-{{ $item['kind'] }}-{{ $item['id'] }}">
                                <td class="md-table__check">@if ($item['kind'] === 'task' && ! $item['recurrent'])<button type="button" class="md-table__check-button" wire:click="toggleTask('{{ $item['id'] }}')" aria-label="Completar {{ $item['title'] }}" title="Completar"><i class="bi bi-circle" aria-hidden="true"></i></button>@endif</td>
                                <td class="md-table__title">
                                    @if ($item['url'])
                                        <a href="{{ $item['url'] }}" wire:navigate>{{ $item['title'] }}</a>
                                    @else
                                        {{ $item['title'] }}
                                    @endif
                                    @if ($item['category'])
                                        <span class="md-table__meta">{{ $item['category'] }}</span>
                                    @endif
                                </td>
                                <td class="md-table__date">{{ $item['dateLabel'] }}</td>
                                <td class="md-table__nowrap"><span class="{{ $toneClass($item['typeTone']) }}"><i class="bi {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['type'] }}</span></td>
                                <td class="md-table__nowrap"><span class="{{ $toneClass($item['stateTone']) }}">{{ $item['state'] }}</span></td>
                                <td class="md-table__actions">
                                    @switch ($item['kind'])
                                        @case ('task')
                                            <x-ui.row-actions :label="'Más acciones de '.$item['title']">
                                                <x-slot:primary x-on:click="Livewire.navigate('{{ $item['url'] }}')">Editar</x-slot:primary>
                                                @unless ($item['recurrent'])
                                                    <x-ui.menu-item icon="bi-check2-circle" wire:click="toggleTask('{{ $item['id'] }}')">Completar</x-ui.menu-item>
                                                @endunless
                                                <x-ui.menu-divider />
                                                <x-ui.menu-item icon="bi-link-45deg" tone="danger" wire:click="unlinkTask('{{ $item['id'] }}')"
                                                                wire:confirm="Se quitará el vínculo con esta persona. La tarea se conserva en Tareas. ¿Continuar?">Quitar vínculo</x-ui.menu-item>
                                            </x-ui.row-actions>
                                            @break
                                        @case ('event')
                                            <x-ui.row-actions :label="'Más acciones de '.$item['title']">
                                                <x-slot:primary wire:click="openEventForm('{{ $item['id'] }}')">Editar</x-slot:primary>
                                                <x-ui.menu-item icon="bi-archive" wire:click="toggleEventArchive('{{ $item['id'] }}')">Archivar</x-ui.menu-item>
                                                <x-ui.menu-divider />
                                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteEvent('{{ $item['id'] }}')"
                                                                wire:confirm="¿Eliminar este acontecimiento?">Eliminar</x-ui.menu-item>
                                            </x-ui.row-actions>
                                            @break
                                        @case ('plan')
                                            <x-ui.action variant="outlined" size="sm" :href="$item['url']" wire:navigate>Ver plan</x-ui.action>
                                            @break
                                        @default
                                            <x-ui.action variant="outlined" size="sm" :href="$item['url']" wire:navigate>Ver cumpleaños</x-ui.action>
                                    @endswitch
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.management-card>

        <x-ui.management-card id="relationship-recent-history" :create-actions="[$createAction]" title="Historial reciente" icon="bi-clock-history">
            <x-slot:headerAction>
                <a href="{{ route('relationships.history', $relationship) }}" class="md-btn-text" wire:navigate>Ver historial completo</a>
            </x-slot:headerAction>

            @if ($recentHistory->isEmpty())
                <x-ui.state variant="empty" icon="bi-clock-history" title="Todavía no hay historial"
                            message="Los acontecimientos pasados y los planes que hagan juntos aparecerán aquí." />
            @else
                @include('livewire.relationship.partials.history-table', ['rows' => $recentHistory])
            @endif
        </x-ui.management-card>
    </div>

    <x-slot:rail>
        <x-context-widget title="Información de contacto" icon="bi-person" menu-label="Acciones del contacto">
            <x-slot:menu>
                <x-ui.menu-item icon="bi-pencil" :href="route('relationships', ['edit' => $relationship->id])" wire:navigate>Editar contacto</x-ui.menu-item>
                <x-ui.menu-item icon="bi-chat-dots" wire:click="markContact">Marcar contacto</x-ui.menu-item>
                <x-ui.menu-item icon="bi-person-hearts" wire:click="openFriendLink">{{ $relationship->friend_user_id ? 'Cambiar amigo vinculado' : 'Vincular con un amigo' }}</x-ui.menu-item>
                @if ($relationship->friend_user_id)<x-ui.menu-item icon="bi-link-45deg" wire:click="unlinkFriend">Quitar vínculo con amigo</x-ui.menu-item>@endif
                <x-ui.menu-divider />
                <x-ui.menu-item icon="bi-archive" wire:click="toggleArchive">{{ $relationship->is_archived ? 'Desarchivar' : 'Archivar' }}</x-ui.menu-item>
            </x-slot:menu>

            <div class="md-relationship-profile">
                <span class="md-relationship-profile__avatar" aria-hidden="true">{{ $initials }}</span>
                <div class="md-relationship-profile__body">
                    <strong>{{ $relationship->full_name }}</strong>
                    @if ($identity !== '')
                        <span>{{ $identity }}</span>
                    @endif
                </div>
            </div>

            <dl class="md-context-list">
                @if ($linkedFriend)
                    <div><dt>Amigo en MiSo</dt><dd>{{ $linkedFriend->full_name ?: $linkedFriend->name }}@if ($linkedFriend->username) &#64;{{ $linkedFriend->username }}@endif</dd></div>
                @endif
                @if ($birthday)
                    <div>
                        <dt>Cumpleaños</dt>
                        <dd>{{ $birthday->label() }}{{ $birthday->ageOnNextOccurrence() !== null ? ' · cumple '.$birthday->ageOnNextOccurrence() : '' }}</dd>
                    </div>
                @endif
                @if ($relationship->city)
                    <div><dt>Ciudad</dt><dd>{{ $relationship->city }}</dd></div>
                @endif
                @if ($relationship->occupation || $relationship->organization)
                    <div><dt>Ocupación</dt><dd>{{ collect([$relationship->occupation, $relationship->organization])->filter()->implode(' · ') }}</dd></div>
                @endif
                @if ($relationship->pronouns)
                    <div><dt>Pronombres</dt><dd>{{ $relationship->pronouns }}</dd></div>
                @endif
                @if ($relationship->address)
                    <div><dt>Dirección</dt><dd>{{ $relationship->address }}</dd></div>
                @endif
                @if ($relationship->document_number)
                    <div><dt>{{ $relationship->documentTypeLabel() ?? 'Documento' }}</dt><dd>{{ $relationship->document_number }}</dd></div>
                @endif
                @if ($relationship->tags->isNotEmpty())
                    <div>
                        <dt>Etiquetas</dt>
                        <dd class="md-relationship-tags">
                            @foreach ($relationship->tags as $tag)
                                <span class="md-chip-tonal">{{ $tag->name }}</span>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </dl>
        </x-context-widget>

        <x-context-widget title="Estadísticas rápidas" icon="bi-bar-chart">
            <dl class="md-relationship-quick-stats">
                @foreach ($quickStats as $stat)
                    <div class="md-relationship-quick-stat md-relationship-quick-stat--{{ $stat['tone'] }}">
                        <dt>{{ $stat['label'] }}</dt>
                        <dd>{{ $stat['value'] }}</dd>
                        @if ($stat['support'])
                            <dd class="md-relationship-quick-stat__support">{{ $stat['support'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </x-context-widget>

        <x-context-widget title="Medios de contacto" icon="bi-telephone">
            @forelse ($relationship->contactMethods as $method)
                <div class="md-relationship-contact">
                    <span class="md-relationship-contact__icon" aria-hidden="true"><i class="bi {{ $method->icon() }}"></i></span>
                    <div class="md-relationship-contact__body">
                        <strong>{{ $method->value }}</strong>
                        <span>{{ collect([$method->typeLabel(), $method->label])->filter()->implode(' · ') }}</span>
                    </div>
                    @if ($method->is_primary)
                        <span class="md-chip-tonal md-chip-tonal--success">Principal</span>
                    @endif
                </div>
            @empty
                <p class="md-body-small mb-0">Sin medios de contacto registrados.</p>
            @endforelse
        </x-context-widget>

        @if ($relationship->general_notes)
            <x-context-widget title="Notas" icon="bi-journal-text">
                <p class="md-body-small mb-0">{{ $relationship->general_notes }}</p>
            </x-context-widget>
        @endif
    </x-slot:rail>

    @include('livewire.relationship.partials.event-dialog')
    @include('livewire.relationship.partials.task-dialog')
    <x-ui.form-dialog module="relationships" :open="$showFriendLink" close="closeFriendLink" submit-action="saveFriendLink" id="relationship-friend-link" title="Vincular con un amigo" icon="bi-person-hearts">
        <x-ui.select name="friendChoice" label="Amigo" placeholder="Sin vínculo" :selected="$friendChoice" :options="$friendOptions" wire:model="friendChoice" />
        <p class="md-body-small">Solo se guarda el vínculo. Tus notas y datos de esta ficha nunca se comparten.</p>
    </x-ui.form-dialog>
</x-module-shell>
