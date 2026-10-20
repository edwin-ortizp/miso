@php
    use App\Support\Ui\DataState;

    $expenseActive = count(array_filter([$expensePeriod, $expenseCategory], static fn ($value) => $value !== ''));
    $expenseState = DataState::resolve(visible: $expenses->count(), total: $totalCount);
    $periodLabel = $periodOptions[$expensePeriod] ?? 'Todo el historial';
@endphp

<x-module-shell module="vehicles" :title="$vehicle->name" subtitle="Seguros, llantas, pintura y otros gastos" icon="bi-wallet2" :tabs="\App\Support\Ui\Tabs\VehicleTabs::for($vehicle, $energyUi)" :back="\App\Support\Ui\Tabs\VehicleTabs::back()">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar gasto', 'icon' => 'bi-plus-lg', 'action' => 'openExpenseForm']" />
    </x-slot:actions>

    @if ($expenseMessage)
        <x-ui.snackbar>{{ $expenseMessage }}</x-ui.snackbar>
    @endif

    <section class="vehicle-metrics-grid">
        <article class="md-card-outlined"><span>Total en gastos</span><strong>$ {{ number_format($filteredAmount, 0, ',', '.') }}</strong><small>{{ $periodLabel }}</small></article>
        <article class="md-card-outlined"><span>Mayor categoría</span><strong>{{ $topCategory['name'] ?? '—' }}</strong><small>{{ $topCategory ? '$ '.number_format($topCategory['amount'], 0, ',', '.') : 'Sin gastos en el periodo' }}</small></article>
        <article class="md-card-outlined"><span>Registros</span><strong>{{ number_format($expenses->total(), 0, ',', '.') }}</strong><small>El combustible y los servicios se registran en sus pestañas</small></article>
    </section>

    <x-ui.management-card clear="clearExpenseFilters" id="vehicle-expense-history" title="Historial de gastos" icon="bi-receipt"
                          :count="'('.$expenses->total().' / '.$totalCount.')'" search="expenseSearch" search-placeholder="Buscar descripción o proveedor"
                          :active-filters="$expenseActive" :paginator="$expenses" sort-model="expenseSort" :sort-options="$sorts" :sort-value="$expenseSort" noun="gastos" alpine="openMenu: null" class="vehicle-history-section" flush>
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Filtros de gastos" @click.outside="openMenu = null">
                <x-ui.filter-menu name="expensePeriod" label="Periodo" allLabel="Todo el historial" :options="$periodOptions" :selected="$expensePeriod" />
                <x-ui.filter-menu name="expenseCategory" label="Categoría" allLabel="Todas las categorías" :options="$categoryOptions" :selected="$expenseCategory" />
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $expensePeriod !== '' ? ['key' => 'expensePeriod', 'value' => null, 'label' => 'Periodo: '.($periodOptions[$expensePeriod] ?? $expensePeriod), 'icon' => 'bi-calendar-range'] : null,
                $expenseCategory !== '' ? ['key' => 'expenseCategory', 'value' => null, 'label' => 'Categoría: '.($categoryOptions[$expenseCategory] ?? $expenseCategory), 'icon' => 'bi-tag'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif
        @if ($expenseActive)
            <x-slot:filterActions>
                <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearExpenseFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
                <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="filtersOpen = false">Filtrar</x-ui.action>
            </x-slot:filterActions>
        @endif
        <x-slot:menu>
            <x-ui.menu-item icon="bi-tags" wire:click="openCategoryManager">Gestionar categorías</x-ui.menu-item>
        </x-slot:menu>

        @if ($expenseState === DataState::CONTENT)
            <div class="vehicle-history-table"><div class="table-responsive"><table class="table md-table mb-0 align-middle"><thead><tr><th>Fecha</th><th>Categoría</th><th>Detalle</th><th>Lectura</th><th>Valor</th><th><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>
                @foreach ($expenses as $expense)
                    <tr wire:key="expense-row-{{ $expense->id }}">
                        <td>{{ $expense->spent_on->format('d/m/Y') }}</td>
                        <td><span class="vehicle-expense-category"><i class="bi {{ $expense->category->icon ?: 'bi-tag' }}" aria-hidden="true"></i>{{ $expense->category->name }}</span></td>
                        <td>@if ($expense->description)<strong>{{ $expense->description }}</strong>@endif @if ($expense->provider)<br><small>{{ $expense->provider }}</small>@endif @if (! $expense->description && ! $expense->provider)—@endif</td>
                        <td>{{ $expense->usage_reading !== null ? number_format($expense->usage_reading, 0, ',', '.').' '.$vehicle->usage_unit : '—' }}</td>
                        <td><strong>$ {{ number_format($expense->amount, 0, ',', '.') }}</strong></td>
                        <td><div class="d-flex">
                            <x-ui.icon-action icon="bi-pencil" label="Editar gasto del {{ $expense->spent_on->format('d/m/Y') }}" size="sm" wire:click="openExpenseForm('{{ $expense->id }}')" />
                            <x-ui.destructive-action label="Eliminar gasto del {{ $expense->spent_on->format('d/m/Y') }}" :iconOnly="true" size="sm"
                                                     action="deleteExpense('{{ $expense->id }}')" title="Eliminar gasto" message="El gasto se elimina del historial de este vehículo." />
                        </div></td>
                    </tr>
                @endforeach
            </tbody></table></div></div>

            <div class="vehicle-history-cards">
                @foreach ($expenses as $expense)
                    <article class="md-card-outlined" wire:key="expense-card-{{ $expense->id }}">
                        <header><time>{{ $expense->spent_on->translatedFormat('d M Y') }}</time><div>
                            <x-ui.icon-action icon="bi-pencil" label="Editar gasto del {{ $expense->spent_on->format('d/m/Y') }}" size="sm" wire:click="openExpenseForm('{{ $expense->id }}')" />
                            <x-ui.destructive-action label="Eliminar gasto del {{ $expense->spent_on->format('d/m/Y') }}" :iconOnly="true" size="sm"
                                                     action="deleteExpense('{{ $expense->id }}')" title="Eliminar gasto" message="El gasto se elimina del historial de este vehículo." />
                        </div></header>
                        <strong>$ {{ number_format($expense->amount, 0, ',', '.') }}</strong>
                        <span class="vehicle-expense-category"><i class="bi {{ $expense->category->icon ?: 'bi-tag' }}" aria-hidden="true"></i>{{ $expense->category->name }}{{ $expense->description ? ' · '.$expense->description : '' }}</span>
                        <dl><div><dt>Proveedor</dt><dd>{{ $expense->provider ?: '—' }}</dd></div><div><dt>Lectura</dt><dd>{{ $expense->usage_reading !== null ? number_format($expense->usage_reading, 0, ',', '.').' '.$vehicle->usage_unit : '—' }}</dd></div></dl>
                    </article>
                @endforeach
            </div>
        @elseif ($expenseState === DataState::FILTERED_EMPTY)
            <x-ui.state variant="filtered-empty" icon="bi-receipt" message="Hay gastos registrados, pero ninguno coincide con la búsqueda o los filtros activos.">
                <x-slot:actions>
                    <x-ui.action variant="outlined" icon="bi-x-circle" wire:click="clearExpenseFilters">Limpiar filtros</x-ui.action>
                </x-slot:actions>
            </x-ui.state>
        @else
            <x-ui.state variant="empty" icon="bi-receipt" title="Sin gastos registrados todavía"
                        message="Registra seguros, llantas, pintura, trámites y demás gastos con el botón de acción." />
        @endif
    </x-ui.management-card>

    @include('livewire.vehicle.partials.expense-form')
    @include('livewire.vehicle.partials.expense-categories-dialog')
</x-module-shell>
