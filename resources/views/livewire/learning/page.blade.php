@php
    use App\Models\LearningFormation;
    use App\Models\LearningUnit;
    $vocab = app(\App\Services\Learning\LearningVocabulary::class);
    $uid = (int) auth()->id();
    $ftype = $formation?->type;
    $semesterLabel = $formation?->kind === 'academic' ? $vocab->label($uid, $ftype, 'period') : '';
    $current = $unit ?? $formation;
    $currentType = $unit ? 'unit' : 'formation';
    $currentPinned = $current && in_array($currentType.':'.$current->id, $pinKeys, true);
    $createActions = [];
    if (!$formation) {
        foreach ($vocab->typeLabels() as $key => $label) $createActions[] = ['label' => $label, 'action' => "openFormation(null, '$key')", 'icon' => 'bi-mortarboard', 'create' => true];
        $primary = ['label' => 'Agregar formación', 'action' => 'openFormation', 'icon' => 'bi-plus-lg'];
    } else {
        foreach ($childKinds as $key) $createActions[] = ['label' => $vocab->addLabel($uid, $ftype, $key), 'action' => "openUnit('$key')", 'icon' => 'bi-plus-lg', 'create' => true];
        $primary = array_shift($createActions);
    }
    $reservationAction = ['label' => 'Reservar tiempo de estudio', 'icon' => 'bi-calendar-plus', 'event' => 'study-reservation-create', 'create' => true];
    $secondary = $primary ? [...$createActions, $reservationAction] : [];
    $primary ??= $reservationAction;
    $addActions = $formation ? array_map(fn ($key) => ['label' => $vocab->addLabel($uid, $ftype, $key), 'action' => "openUnit('$key')"], $childKinds) : [];
    $currentEditAction = $unit ? "openUnit('', '$unit->id')" : ($formation ? "openFormation('$formation->id')" : '');
    $currentDeleteAction = $formation ? "deleteRecord('$formation->id', ".($unit ? "'$unit->id'" : 'null').")" : '';
    $dialogLabel = $editor === 'unit' ? $vocab->singular($uid, $ftype, (string) $editorKind) : $vocab->typeLabel($draft['type'] ?? '');
@endphp
<x-module-shell module="learning" class="learning-page" :title="$current?->title" :back="$back" :archetype="$formation ? 'detail' : 'list'">
    @if ($primary)
        <x-slot:actions><x-module-actions :primary="$primary" :secondary="$secondary" :split="true" /></x-slot:actions>
    @endif
    <x-slot:contextBefore>
        <div class="learning-shortcuts">
            <x-context-widget title="Accesos directos" icon="bi-pin-angle">
                @if ($shortcuts)
                    <ul class="learning-shortcuts__list">
                        @foreach ($shortcuts as $shortcut)
                            <li wire:key="pin-{{ $shortcut['type'] }}-{{ $shortcut['id'] }}">
                                <a href="{{ $shortcut['href'] }}" wire:navigate><strong>{{ $shortcut['title'] }}</strong><small>{{ $shortcut['label'] }} · {{ $shortcut['origin'] }}</small></a>
                                <button type="button" class="md-btn-icon" wire:click="setPin('{{ $shortcut['type'] }}', '{{ $shortcut['id'] }}', false)" aria-label="Quitar de accesos directos: {{ $shortcut['title'] }}" title="Quitar de accesos directos"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i></button>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="md-body-small">Fija una formación o cualquier contenido desde su menú para abrirlo directamente.</p>
                @endif
            </x-context-widget>
        </div>
    </x-slot:contextBefore>
    <x-slot:rail>
        <x-context-widget title="Guardián de Formación" icon="bi-stars">
            @if (\App\Livewire\Game\GuardianCard::visibleFor((int) auth()->id(), 'learning'))
                <livewire:game.guardian-card module="learning" variant="rail" />
            @else
                <p class="md-body-small">Organiza lo que quieres estudiar en pasos pequeños. Puedes comenzar con una formación y agregar su contenido cuando lo necesites.</p>
            @endif
        </x-context-widget>
    </x-slot:rail>

    @if ($message)<p role="status" class="learning-feedback">{{ $message }}</p>@endif
    @error('deletion')<p role="alert" class="learning-error">{{ $message }}</p>@enderror

    @if ($formation)
        <div class="learning-summary">
            <div>
                <x-ui.chip variant="tonal">{{ $unit ? $vocab->label($uid, $ftype, $unit->kind) : $vocab->typeLabel($formation->type) }}</x-ui.chip>
                <x-ui.chip variant="tonal">{{ LearningFormation::STATES[$current->status] }}</x-ui.chip>
                @if (!$unit && $formation->institution)<x-ui.chip variant="tonal" icon="bi-bank">{{ $formation->institution }}</x-ui.chip>@endif
                @if ($unit?->credits !== null && $unit)<x-ui.chip variant="tonal" icon="bi-award">{{ $unit->credits }} {{ $unit->credits == 1 ? 'crédito' : 'créditos' }}</x-ui.chip>@endif
                @if ($current->start_date || $current->end_date)<x-ui.chip variant="tonal" icon="bi-calendar3">{{ $current->start_date?->translatedFormat('d M Y') ?? 'Sin fecha inicial' }} – {{ $current->end_date?->translatedFormat('d M Y') ?? 'Sin fecha final' }}</x-ui.chip>@endif
                @if ($current->description)<p class="learning-description">{{ $current->description }}</p>@endif
            </div>
            <div class="learning-summary__actions">
                <button type="button" class="md-btn-icon" aria-label="{{ $currentPinned ? 'Quitar de accesos directos' : 'Fijar en accesos directos' }}" title="{{ $currentPinned ? 'Quitar de accesos directos' : 'Fijar en accesos directos' }}" aria-pressed="{{ $currentPinned ? 'true' : 'false' }}" wire:click="setPin('{{ $currentType }}', '{{ $current->id }}', {{ $currentPinned ? 'false' : 'true' }})"><i class="bi {{ $currentPinned ? 'bi-pin-angle-fill' : 'bi-pin-angle' }}" aria-hidden="true"></i></button>
                <x-ui.menu label="Acciones del elemento">
                    @foreach ($addActions as $add)
                        <x-ui.menu-item icon="bi-plus-lg" :wire:click="$add['action']">{{ $add['label'] }}</x-ui.menu-item>
                    @endforeach
                    @if ($addActions)<x-ui.menu-divider />@endif
                    <x-ui.menu-item icon="bi-pencil" :wire:click="$currentEditAction" x-on:click="$el.closest('.md-menu').querySelector('button').focus()">Editar</x-ui.menu-item>
                    @if ($semesterLabel && $semesters && $unit?->kind === 'subject')
                        <x-ui.menu-item icon="bi-arrow-left-right" wire:click="openSemester('{{ $unit->id }}')" x-on:click="$el.closest('.md-menu').querySelector('button').focus()">Cambiar {{ mb_strtolower($semesterLabel) }}</x-ui.menu-item>
                    @endif
                    <x-ui.menu-item icon="bi-trash" tone="danger" :wire:click="$currentDeleteAction" :wire:confirm="'¿Eliminar «'.$current->title.'»? Se quitará también su acceso directo.'">Eliminar</x-ui.menu-item>
                </x-ui.menu>
            </div>
        </div>
    @endif

    @if (!$formation || $childKinds || $records->count())
        <x-ui.management-card id="learning-records" :create-actions="[$primary, ...$secondary]" :title="$formation ? 'Contenido' : 'Formaciones'" icon="bi-mortarboard" :count="(string)($paginator?->total() ?? $records->count())" :search="$formation ? null : 'search'" search-placeholder="Buscar formaciones" :paginator="$paginator" noun="formaciones" :portal-filters="true" :active-filters="(int)($typeFilter !== '') + (int)($statusFilter !== '') + (int)($categoryFilter !== '')">
            @unless ($formation)
                <x-slot:filters>
                    <x-ui.select label="Tipo de formación" name="typeFilter" wire:model.live="typeFilter" :options="$vocab->typeLabels()" placeholder="Todos los tipos" />
                    <x-ui.select label="Estado" name="statusFilter" wire:model.live="statusFilter" :options="LearningFormation::STATES" placeholder="Todos los estados" />
                    <x-ui.select label="Categoría" name="categoryFilter" wire:model.live="categoryFilter" :options="$categories" placeholder="Todas las categorías" />
                </x-slot:filters>
                <x-slot:filterActions><x-ui.action variant="text" wire:click="clearFilters">Limpiar filtros</x-ui.action><x-ui.action variant="filled" x-on:click="filtersOpen = false">Aplicar</x-ui.action></x-slot:filterActions>
            @endunless
            <x-slot:menu>
                @if ($formation)
                    @foreach ($addActions as $add)
                        <x-ui.menu-item icon="bi-plus-lg" :wire:click="$add['action']">{{ $add['label'] }}</x-ui.menu-item>
                    @endforeach
                @else
                    <x-ui.menu-item icon="bi-tags" :href="route('learning.settings.categories')">Categorías</x-ui.menu-item>
                @endif
            </x-slot:menu>
            @if ($records->count())
                <div class="learning-table-wrap">
                    <table class="md-table" aria-label="{{ $formation ? 'Contenido de '.$current->title : 'Formaciones' }}">
                        <thead><tr><th scope="col">Nombre</th><th scope="col">Tipo</th><th scope="col">Estado</th>@unless($formation)<th scope="col">Categoría</th>@endunless<th scope="col" class="md-table__actions">Acciones</th></tr></thead>
                        <tbody>
                            @foreach ($records as $record)
                                @php
                                    $isUnit = $record instanceof LearningUnit;
                                    $recordType = $isUnit ? 'unit' : 'formation';
                                    $pinned = in_array($recordType.':'.$record->id, $pinKeys, true);
                                    $editAction = $isUnit ? "openUnit('', '$record->id')" : "openFormation('$record->id')";
                                    $deleteAction = "deleteRecord('".($isUnit ? $formation->id : $record->id)."', ".($isUnit ? "'$record->id'" : 'null').")";
                                    $href = $isUnit ? route('learning.units.show', [$formation->id, $record->id]) : route('learning.show', $record->id);
                                    $consultAction = 'Livewire.navigate('.json_encode($href).')';
                                @endphp
                                <tr wire:key="learning-{{ $recordType }}-{{ $record->id }}">
                                    <td>
                                        <a class="learning-record-link" href="{{ $href }}" wire:navigate>
                                            <span>{{ $record->title }}</span>
                                            @if (!$isUnit && filled($record->institution))
                                                <span class="md-table__meta">{{ $record->institution }}</span>
                                            @endif
                                        </a>
                                    </td>
                                    <td>{{ $isUnit ? $vocab->label($uid, $ftype, $record->kind) : $vocab->typeLabel($record->type) }}</td>
                                    <td>{{ LearningFormation::STATES[$record->status] }}</td>
                                    @unless($formation)<td>{{ $categories[$record->category] ?? 'Sin categoría' }}</td>@endunless
                                    <td class="md-table__actions">
                                        <x-ui.row-actions :floating="true" :label="'Acciones de '.$record->title">
                                            <x-slot:primary :x-on:click="$consultAction">Consultar</x-slot:primary>
                                            <x-ui.menu-item icon="bi-pencil" :wire:click="$editAction">Editar</x-ui.menu-item>
                                            <x-ui.menu-item icon="bi-pin-angle" wire:click="setPin('{{ $recordType }}', '{{ $record->id }}', {{ $pinned ? 'false' : 'true' }})">{{ $pinned ? 'Quitar de accesos directos' : 'Fijar en accesos directos' }}</x-ui.menu-item>
                                            @if ($semesterLabel && $semesters && $isUnit && $record->kind === 'subject')
                                                <x-ui.menu-item icon="bi-arrow-left-right" wire:click="openSemester('{{ $record->id }}')">Cambiar {{ mb_strtolower($semesterLabel) }}</x-ui.menu-item>
                                            @endif
                                            @if ($isUnit)
                                                @unless ($loop->first)<x-ui.menu-item icon="bi-arrow-up" wire:click="move('{{ $record->id }}', -1)">Subir</x-ui.menu-item>@endunless
                                                @unless ($loop->last)<x-ui.menu-item icon="bi-arrow-down" wire:click="move('{{ $record->id }}', 1)">Bajar</x-ui.menu-item>@endunless
                                            @endif
                                            <x-ui.menu-divider />
                                            <x-ui.menu-item icon="bi-trash" tone="danger" :wire:click="$deleteAction" :wire:confirm="'¿Eliminar «'.$record->title.'»? Se quitará también su acceso directo.'">Eliminar</x-ui.menu-item>
                                        </x-ui.row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif (!$formation && ($search !== '' || $typeFilter !== '' || $categoryFilter !== '' || $statusFilter !== ''))
                <x-ui.state variant="filtered-empty" icon="bi-search" message="No hay formaciones con estos filtros."><x-slot:actions><x-ui.action variant="text" wire:click="clearFilters">Limpiar filtros</x-ui.action></x-slot:actions></x-ui.state>
            @else
                <x-ui.state variant="empty" icon="bi-mortarboard" :title="$formation ? 'Sin contenido todavía' : 'Aún no tienes formaciones'" :message="$formation ? ($childKinds ? 'Agrega '.$vocab->plurals($uid, $ftype, $childKinds).' con el botón Agregar o desde el menú ⋯ de esta tarjeta.' : 'Esta formación todavía no admite contenido adicional.') : 'Organiza lo que quieres estudiar desde Agregar formación.'" />
            @endif
        </x-ui.management-card>
    @else
        <p>{{ $unit ? $vocab->demonstrative($uid, $ftype, $unit->kind) : 'Esta formación' }} todavía no tiene registros adicionales.</p>
    @endif
    @include('livewire.learning.editor')
    <livewire:learning.study-reservation-editor :formation="$formation?->id" :unit="$unit?->id"
        :key="'study-reservation-editor-'.($current?->id ?? 'index')" />
</x-module-shell>
