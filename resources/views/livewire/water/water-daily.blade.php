@php
    use App\Support\Ui\DataState;

    $logsState = $logs->count() > 0
        ? DataState::CONTENT
        : ($totalLogs > 0 && (count($activeFilters) > 0 || trim($search) !== '') ? DataState::FILTERED_EMPTY : DataState::EMPTY);
    $drinksState = DataState::resolve(visible: $drinkTypes->count(), total: $drinkTypes->count());
@endphp

<x-module-shell module="water">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar', 'icon' => 'bi-plus-lg', 'action' => 'openForm']"
            :greeting="config('companion_chat.water.greeting')"
            :quick="$drinkTypes->take(4)->map(fn ($type) => [
                'type' => 'tap', 'id' => 'drink-'.$type->id, 'emoji' => $type->icon ?? '💧', 'label' => $type->name.' · 250 ml',
                'aria' => 'Registrar 250 ml de '.$type->name, 'echo' => '250 ml de '.$type->name,
                'call' => 'quickAdd', 'args' => [(string) $type->id, 250],
                'reply' => str_replace('{name}', $type->name, config('companion_chat.water.reply')),
            ])->values()->all()" />
    </x-slot:actions>

    <x-slot:controls>
        <p class="md-body-medium mb-0">{{ ucfirst(\Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \d\e F')) }}</p>
    </x-slot:controls>

    <livewire:game.guardian-card module="water" variant="top" />

    <x-ui.management-card id="water-day-logs" title="Registro del día" icon="bi-clock-history"
                          :count="'('.$logs->total().' / '.$totalLogs.')'" search="search" search-placeholder="Buscar registros"
                          sort-model="sort" :sort-options="$sorts" :sort-value="$sort"
                          :active-filters="count($activeFilters)" :paginator="$logs" noun="registros"
                          alpine="draftScope: 'day', draftDrink: ''"
                          on-filters-open="draftScope = $wire.dateScope; draftDrink = $wire.drinkFilter">
        <x-slot:filters>
            <x-ui.select name="filterDateScope" label="Fecha" :options="$dateScopes" :selected="$dateScope" icon="bi-calendar-event" x-model="draftScope" />
            <x-ui.select name="filterDrink" label="Bebida" placeholder="Todas"
                         :options="$drinkTypes->mapWithKeys(fn ($type) => [$type->id => ($type->icon ?? '💧').' '.$type->name])->all()"
                         :selected="$drinkFilter" icon="bi-cup-straw" x-model="draftDrink" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftScope, draftDrink); filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>

        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif

        @if ($logsState === DataState::CONTENT)
            <table class="md-table md-table--stack">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Hora</th>
                        <th scope="col">Bebida</th>
                        <th scope="col">Cantidad</th>
                        <th scope="col">Hidratación efectiva</th>
                        <th scope="col" class="md-table__actions"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        @php $logDate = \Carbon\Carbon::parse($log->date); @endphp
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="md-table__date">{{ $logDate->isToday() ? 'Hoy' : $logDate->translatedFormat('j M Y') }}</td>
                            <td class="md-table__date">{{ $log->time }}</td>
                            <td class="md-table__title"><span aria-hidden="true">{{ $log->drinkType?->icon ?? '💧' }}</span> {{ $log->drink_type }}</td>
                            <td class="md-table__nowrap">{{ number_format($log->amount, 0, ',', '.') }} ml</td>
                            <td class="md-table__nowrap">{{ number_format($log->hydration_value, 0, ',', '.') }} ml</td>
                            <td class="md-table__actions">
                                <x-ui.row-actions :label="'Más acciones del registro de '.$log->drink_type">
                                    <x-slot:primary wire:click="openForm('{{ $log->id }}')">Editar</x-slot:primary>
                                    <x-ui.menu-divider />
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $log->id }}')"
                                                    wire:confirm="El registro de {{ $log->amount }} ml se elimina de forma permanente.">Eliminar</x-ui.menu-item>
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($logsState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="{{ DataState::FILTERED_EMPTY }}" message="Quita el filtro de fecha o cambia la búsqueda para ver otros registros." />
        @else
            <x-ui.state variant="{{ DataState::EMPTY }}" icon="bi-droplet" title="Sin registros de hidratación"
                        message="Registra tu primera bebida para ver aquí el detalle." />
        @endif
    </x-ui.management-card>

    <x-slot:rail>
        @php
            $selectedDay = \Carbon\Carbon::parse($selectedDate);
            $liters = fn (int $ml) => number_format($ml / 1000, 1, ',', '.').' L';
            $consumed = (int) $totalHydration;
        @endphp

        <x-context-widget :title="$selectedDay->isToday() ? 'Objetivo de hoy' : 'Objetivo del '.$selectedDay->translatedFormat('j \d\e F')" icon="bi-droplet">
            <livewire:game.guardian-card module="water" variant="rail" />
            <p class="goal-today"><strong>{{ $liters($consumed) }} <span>/ {{ $liters($dailyGoal) }}</span></strong><b>{{ $rawPercentage }} %</b></p>
            <x-ui.progress :value="min($rawPercentage, 100)" tone="primary" label="Avance de la meta diaria" :valueText="$rawPercentage.'% de la meta'" />
            <p class="md-body-small mb-0">
                @if ($consumed < $dailyGoal)
                    Faltan {{ number_format($dailyGoal - $consumed, 0, ',', '.') }} ml para alcanzar tu meta diaria.
                @elseif ($consumed === $dailyGoal)
                    Alcanzaste tu meta diaria.
                @else
                    Superaste tu meta por {{ number_format($consumed - $dailyGoal, 0, ',', '.') }} ml.
                @endif
            </p>
        </x-context-widget>

        <x-context-widget title="Calendario del mes" icon="bi-calendar3">
            <x-slot:actions>
                <div class="goal-month-nav">
                    <x-ui.icon-action icon="bi-chevron-left" label="Mes anterior" size="sm" wire:click="previousMonth" />
                    <span>{{ ucfirst($monthData['label']) }}</span>
                    <x-ui.icon-action icon="bi-chevron-right" label="Mes siguiente" size="sm" wire:click="nextMonth" />
                </div>
            </x-slot:actions>
            <x-goal-calendar :month-data="$monthData" :goal="$dailyGoal" :format="$liters" route="water.daily" label="Cumplimiento de la meta de hidratación" />
            <p class="goal-streak">
                <span aria-hidden="true">🔥</span>
                @if ($streak > 0)
                    Racha actual: {{ $streak }} {{ $streak === 1 ? 'día' : 'días' }} cumpliendo la meta al 100%.
                @else
                    Aún no tienes racha: cumple tu meta al 100% para empezarla.
                @endif
            </p>
        </x-context-widget>

        @if ($drinkTypes->isNotEmpty())
            <x-context-widget title="Agregar rápido" icon="bi-lightning-charge">
                <p class="md-body-small">Registra 250 ml de una bebida habitual.</p>
                <x-ui.filter-bar label="Bebidas habituales">
                    <x-slot:chips>
                        @foreach ($drinkTypes->take(6) as $type)
                            <x-ui.chip variant="suggestion" wire:click="quickAdd('{{ $type->id }}', 250)" wire:key="quick-{{ $type->id }}">
                                {{ $type->icon ?? '💧' }} {{ $type->name }} (250 ml)
                            </x-ui.chip>
                        @endforeach
                    </x-slot:chips>
                </x-ui.filter-bar>
            </x-context-widget>
        @endif
    </x-slot:rail>

    @if ($showForm)
        <div wire:key="water-form-{{ $editingId ?? 'new' }}" x-data="{
            initial: @js([(string) $drinkTypeId, (string) $amount, substr($time, 0, 5)]),
            confirming: false,
            returnFocus: null,
            requestClose() {
                if (this.confirming) { this.keepEditing(); return; }
                const current = [String($wire.drinkTypeId ?? ''), String($wire.amount ?? ''), String($wire.time ?? '').slice(0, 5)];
                if (JSON.stringify(current) === JSON.stringify(this.initial)) { $wire.closeForm(); return; }
                this.returnFocus = document.activeElement;
                this.confirming = true;
                this.$nextTick(() => this.$refs.keepEditing.focus());
            },
            keepEditing() {
                this.confirming = false;
                this.$nextTick(() => {
                    if (this.returnFocus?.isConnected && this.returnFocus.closest('[role=dialog]')) this.returnFocus.focus();
                    else document.getElementById('water-drink-type').focus();
                });
            }
        }">
            <x-ui.form-dialog :open="$showForm" close="closeForm" close-expression="requestClose()"
                              title="{{ $editingId ? 'Editar bebida' : 'Nueva bebida' }}"
                              submit-action="save" id="water-log-form"
                              x-on:submit.capture="if (confirming) { $event.preventDefault(); $event.stopImmediatePropagation(); }">
                <div x-show="!confirming">
                    <x-ui.combobox id="water-drink-type" name="drinkTypeId" label="Tipo de bebida" placeholder="Buscar bebida…"
                                 :options="$drinkTypes->mapWithKeys(fn ($type) => [$type->id => ($type->icon ?? '💧').' '.$type->name.' (x'.$type->hydration_factor.')'])->all()"
                                 wire:model="drinkTypeId" />
                    <x-ui.field name="amount" label="Cantidad (ml)" type="number" min="1" step="50" wire:model="amount" />
                    <x-ui.field name="time" label="Hora" type="time" wire:model="time" />
                    <x-ui.filter-bar label="Cantidades habituales">
                        <x-slot:chips>
                            @foreach ([100, 200, 250, 330, 500] as $preset)
                                <x-ui.chip variant="suggestion" wire:click="$set('amount', {{ $preset }})">{{ $preset }} ml</x-ui.chip>
                            @endforeach
                        </x-slot:chips>
                    </x-ui.filter-bar>
                </div>
                <div x-show="confirming" x-cloak role="alert" aria-labelledby="water-discard-title">
                    <h3 id="water-discard-title" class="md-title-medium">¿Descartar los cambios?</h3>
                    <p>Tienes cambios sin guardar. Si sales, se perderán.</p>
                </div>
                <x-slot:actions>
                    <x-ui.action variant="text" x-show="!confirming" x-on:click="requestClose()">Cancelar</x-ui.action>
                    <x-ui.action variant="filled" type="submit" x-show="!confirming" wire:loading.attr="disabled">{{ $editingId ? 'Actualizar' : 'Guardar' }}</x-ui.action>
                    <x-ui.action variant="text" x-show="confirming" x-cloak x-ref="keepEditing" x-on:click="keepEditing()">Seguir editando</x-ui.action>
                    <x-ui.action variant="filled" x-show="confirming" x-cloak wire:click="closeForm" wire:loading.attr="disabled">Descartar cambios</x-ui.action>
                </x-slot:actions>
            </x-ui.form-dialog>
        </div>
    @endif

    @if ($showCatalog)
        <div x-data="{ open: true }">
            <x-ui.dialog state="open" title="Configuración de bebidas" size="lg" x-on:md-surface-close="$wire.closeCatalog()">
                <p class="md-body-small">Administra las bebidas disponibles para registrar tu hidratación.</p>

                @if ($catalogMessage)
                    <x-ui.state variant="initial" icon="bi-info-circle" :title="$catalogMessage" />
                @endif

                <x-ui.section title="Tus bebidas" :level="3">
                    <x-slot:actions>
                        <x-ui.action variant="filled" icon="bi-plus-lg" wire:click="openDrinkTypeForm">Nueva bebida</x-ui.action>
                    </x-slot:actions>

                    @if ($drinksState === DataState::CONTENT)
                        <x-ui.list label="Bebidas configuradas">
                            @foreach ($drinkTypes as $type)
                                <x-ui.list-item :headline="$type->name"
                                                :supporting="'Factor de hidratación: '.$type->hydration_factor"
                                                wire:key="drink-{{ $type->id }}">
                                    <x-slot:leading><span aria-hidden="true">{{ $type->icon ?: '💧' }}</span></x-slot:leading>
                                    <x-slot:trailing>
                                        <x-ui.icon-action icon="bi-pencil" label="Editar la bebida {{ $type->name }}"
                                                          wire:click="openDrinkTypeForm('{{ $type->id }}')" />
                                        <x-ui.destructive-action label="Eliminar la bebida {{ $type->name }}" :iconOnly="true"
                                                                 action="deleteDrinkType('{{ $type->id }}')"
                                                                 title="Eliminar bebida"
                                                                 message="La bebida «{{ $type->name }}» se elimina de forma permanente." />
                                    </x-slot:trailing>
                                </x-ui.list-item>
                            @endforeach
                        </x-ui.list>
                    @else
                        <x-ui.state variant="empty" icon="bi-cup-straw" title="Aún no tienes bebidas configuradas"
                                    message="Crea una bebida para registrarla con un solo toque." />
                    @endif
                </x-ui.section>

                <x-slot:actions>
                    <x-ui.action variant="text" wire:click="closeCatalog">Cerrar</x-ui.action>
                </x-slot:actions>
            </x-ui.dialog>
        </div>
    @endif

    @if ($showDrinkTypeForm)
        <div x-data="{ openDrinkForm: true }">
            <x-ui.dialog state="openDrinkForm" title="{{ $editingDrinkTypeId ? 'Editar bebida' : 'Nueva bebida' }}"
                         x-on:md-surface-close="$wire.closeDrinkTypeForm()">
                <x-ui.field name="catalogDrinkName" label="Nombre" maxlength="255" wire:model="catalogDrinkName" />
                <x-ui.field name="catalogDrinkIcon" label="Icono" maxlength="40" help="Un emoji basta." wire:model="catalogDrinkIcon" />
                <x-ui.field name="catalogHydrationFactor" label="Factor de hidratación" type="number" min="0" max="9.99" step="0.01"
                            help="1,00 equivale a la misma cantidad de hidratación registrada." wire:model="catalogHydrationFactor" />

                <x-slot:actions>
                    <x-ui.action variant="text" wire:click="closeDrinkTypeForm">Cancelar</x-ui.action>
                    <x-ui.action variant="filled" wire:click="saveDrinkType">Guardar bebida</x-ui.action>
                </x-slot:actions>
            </x-ui.dialog>
        </div>
    @endif
</x-module-shell>
