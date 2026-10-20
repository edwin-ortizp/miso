@php
    $qty = fn ($value) => $value === null ? '?' : rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
    $availabilityTone = fn ($recipe) => $recipe['incomplete'] ? 'neutral' : ($recipe['can_cook'] ? 'success' : 'warning');
    $availabilityLabel = fn ($recipe) => $recipe['incomplete'] ? 'Datos incompletos' : ($recipe['can_cook'] ? 'Stock suficiente' : 'Faltan ingredientes');
    $expiryGroups = ['expired_products' => 'Productos vencidos', 'expiring_products' => 'Productos próximos a vencer', 'expired_preparations' => 'Preparaciones vencidas', 'expiring_preparations' => 'Preparaciones próximas a vencer'];
    $activeFilters = ($availability !== 'ready' ? 1 : 0) + ((float) $portions !== 1.0 ? 1 : 0) + ((int) $days !== 7 ? 1 : 0);
@endphp

<x-module-shell module="meals">
    <x-slot:actions><x-module-actions :primary="['label' => 'Generar compras del plan', 'icon' => 'bi-cart-plus', 'event' => 'meal-shopping-preview']" /></x-slot:actions>

    <x-ui.management-card id="meal-cook-list" title="Qué puedo cocinar" icon="bi-egg-fried" :count="'('.$recipes->total().')'"
                          search="search" search-placeholder="Buscar qué cocinar" :active-filters="$activeFilters"
                          :paginator="$recipes" noun="recetas">
        <x-slot:filters>
            <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Disponibilidad de ingredientes">
                @foreach (\App\Livewire\Meal\MealSuggestions::AVAILABILITY as $value => $label)
                    <x-ui.chip variant="filter" :selected="$availability === $value" wire:click="$set('availability', '{{ $value }}')">{{ $label }}</x-ui.chip>
                @endforeach
            </div>
            <div class="md-field-pair">
                <x-ui.field label="Porciones a cocinar" name="portions" type="number" min="0.01" step="0.01" wire:model.live.debounce.300ms="portions" />
                <x-ui.field label="Días de vencimiento" name="days" type="number" min="0" max="365" wire:model.live.debounce.300ms="days" />
            </div>
        </x-slot:filters>
        @php
            $appliedChips = array_values(array_filter([
                $availability !== 'all' ? ['key' => 'availability', 'value' => null, 'label' => 'Disponibilidad: '.(\App\Livewire\Meal\MealSuggestions::AVAILABILITY[$availability] ?? $availability), 'icon' => 'bi-basket'] : null,
            ]));
        @endphp
        @if ($appliedChips !== [])
            <x-slot:strip>
                <x-ui.applied-filters :filters="$appliedChips" />
            </x-slot:strip>
        @endif

        @if ($recipes->isEmpty())
            <x-ui.state variant="empty" icon="bi-egg-fried" title="No hay recetas que coincidan"
                        message="Prueba con otra búsqueda o cambia la disponibilidad en Filtros." />
        @else
            <table class="md-table md-table--stack" aria-label="Recetas según inventario">
                <thead><tr><th scope="col">Receta</th><th scope="col">Tiempo</th><th scope="col">Porciones</th><th scope="col">Disponibilidad</th><th scope="col">Ingredientes faltantes</th><th scope="col" class="md-table__actions">Acciones</th></tr></thead>
                <tbody>
                    @foreach ($recipes as $recipe)
                        @php $missing = collect($recipe['ingredients'])->filter(fn ($row) => $row['missing'] === null || $row['missing'] > 0); @endphp
                        <tr wire:key="suggested-recipe-{{ $recipe['id'] }}">
                            <td data-label="Receta" class="md-table__title">{{ $recipe['name'] }}</td>
                            <td data-label="Tiempo" class="md-table__nowrap">{{ $recipe['prep_time'] ? $recipe['prep_time'].' min' : '—' }}</td>
                            <td data-label="Porciones">{{ $qty($recipe['portions']) }}</td>
                            <td data-label="Disponibilidad" class="md-table__nowrap"><x-ui.chip variant="tonal" :tone="$availabilityTone($recipe)">{{ $availabilityLabel($recipe) }}</x-ui.chip></td>
                            @php $missingText = $missing->map(fn ($row) => $row['name'].': '.($row['missing'] === null ? 'sin datos' : $qty($row['missing']).' '.$row['unit']))->implode(' · '); @endphp
                            <td data-label="Ingredientes faltantes" class="md-table__notes" title="{{ $missingText }}">{{ $missingText ?: '—' }}</td>
                            <td data-label="Acciones" class="md-table__actions">
                                <x-ui.row-actions :label="'Acciones de '.$recipe['name']">
                                    <x-slot:primary wire:click="showDetail('{{ $recipe['id'] }}')">Ver ingredientes</x-slot:primary>
                                    <x-ui.menu-item icon="bi-journal-text" :href="route('meals.recipes')" wire:navigate>Ir a recetas</x-ui.menu-item>
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.management-card>

    <x-ui.section title="Vencimientos del inventario" :level="2"
                  description="Fechas generales de productos y preparaciones en los próximos {{ (int) $days }} días. Son avisos y no bloquean el consumo.">
        <x-ui.card variant="outlined" icon="bi-calendar-x" iconTone="secondary">
            <div class="meal-expiry">
                @foreach ($expiryGroups as $key => $label)
                    <div class="meal-expiry__group">
                        <h3 class="md-title-small">{{ $label }} <span class="md-label-small">({{ count($suggestions[$key]) }})</span></h3>
                        @forelse ($suggestions[$key] as $row)
                            <p class="md-body-small mb-0">{{ $row['name'] }} · {{ \Carbon\Carbon::parse($row['consume_by'])->translatedFormat('j M') }} · {{ $qty($row['stock'] ?? $row['remaining']) }} {{ $row['unit'] ?? 'porciones' }}</p>
                        @empty
                            <p class="md-body-small mb-0 meal-expiry__empty">Nada en este intervalo.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </x-ui.section>

    @if ($detail)
        {{-- Se teletransporta al body: el view-transition-name de <main> deja los modales bajo el menú lateral. --}}
        <template x-teleport="body">
            <div x-data="{ open: true }" x-init="$watch('open', value => value || $wire.closeDetail())" wire:key="cook-detail-{{ $detail['id'] }}">
                <x-ui.dialog state="open" :title="$detail['name']" icon="bi-egg-fried" x-on:md-surface-close="$wire.closeDetail()">
                    <p class="md-body-medium">{{ $qty($detail['portions']) }} porciones{{ $detail['prep_time'] ? ' · '.$detail['prep_time'].' min' : '' }}</p>
                    <x-ui.list label="Ingredientes de {{ $detail['name'] }}">
                        @forelse ($detail['ingredients'] as $row)
                            <x-ui.list-item :headline="$row['name']" wire:key="cook-ingredient-{{ $loop->index }}">
                                <p class="md-body-small mb-0">Necesitas {{ $qty($row['quantity']) }} {{ $row['unit'] }} · Tienes {{ $qty($row['stock']) }} {{ $row['unit'] }}</p>
                                <x-slot:trailing>
                                    @if ($row['missing'] === null)
                                        <x-ui.chip variant="tonal">Sin datos</x-ui.chip>
                                    @elseif ($row['missing'] > 0)
                                        <x-ui.chip variant="tonal" tone="warning">Faltan {{ $qty($row['missing']) }} {{ $row['unit'] }}</x-ui.chip>
                                    @else
                                        <x-ui.chip variant="tonal" tone="success">Disponible</x-ui.chip>
                                    @endif
                                </x-slot:trailing>
                            </x-ui.list-item>
                        @empty
                            <p class="md-body-small">La receta no tiene ingredientes enlazados al inventario.</p>
                        @endforelse
                    </x-ui.list>
                    <x-slot:actions><x-ui.action wire:click="closeDetail">Cerrar</x-ui.action></x-slot:actions>
                </x-ui.dialog>
            </div>
        </template>
    @endif

    <livewire:meal.meal-plan-shopping-editor :key="'suggestions-shopping-editor'" />
</x-module-shell>
