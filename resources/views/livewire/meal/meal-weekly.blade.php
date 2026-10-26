@php
    $stateOf = fn ($entry) => ! $entry ? 'empty' : ($entry->status === 'consumed' ? ($entry->consumption_mode === 'outside' ? 'outside' : 'home') : 'planned');
    $stateLabels = ['planned' => 'Planeada', 'home' => 'Consumida', 'outside' => 'Por fuera', 'empty' => 'Sin planificar'];
    $itemNames = fn ($entry) => $entry->items->map(fn ($item) => $item->recipe?->name ?? $item->name)->filter()->implode(' + ');
    $slotCalories = fn ($entry) => $entry->status === 'consumed' ? ($entry->consumption['calories'] ?? null) : $entry->effective_calories;
    $formState = $formStatus === 'consumed' ? ($consumptionMode === 'outside' ? 'outside' : 'home') : 'planned';
    $actionLabels = ['move' => 'Mover', 'copy' => 'Copiar', 'swap' => 'Intercambiar'];
@endphp

<x-module-shell module="meals">

    <nav class="meal-week-toolbar" aria-label="Semana">
        <x-ui.icon-action icon="bi-chevron-left" label="Semana anterior" wire:click="previousWeek" />
        <p class="meal-week-toolbar__label">
            <span>{{ $weekLabel }}</span>
            @unless ($showToday)
                <x-ui.action variant="text" size="sm" wire:click="thisWeek">Ir a esta semana</x-ui.action>
            @endunless
        </p>
        <x-ui.icon-action icon="bi-chevron-right" label="Semana siguiente" wire:click="nextWeek" />
    </nav>

    <section class="meal-week" aria-label="Plan semanal">
        <div class="meal-week__labels" aria-hidden="true">
            <span></span>
            @foreach ($mealTypes as $typeLabel)<span class="meal-week__label">{{ $typeLabel }}</span>@endforeach
            <span class="meal-week__label">Planeado / consumido</span>
        </div>

        @foreach ($weekDates as $date)
            @php $dayKey = $date->format('Y-m-d'); @endphp
            <div @class(['meal-day', 'is-today' => $date->isToday()]) wire:key="meal-day-{{ $dayKey }}">
                <h3 class="meal-day__head">{{ $date->translatedFormat('D') }} <small>{{ $date->format('j') }}{{ $date->isToday() ? ' · hoy' : '' }}</small></h3>

                @foreach ($mealTypes as $typeKey => $typeLabel)
                    @php
                        $entry = $entries->get($dayKey.'|'.$typeKey)?->first();
                        $state = $stateOf($entry);
                        $names = $entry ? $itemNames($entry) : '';
                        $kcal = $entry ? $slotCalories($entry) : null;
                    @endphp
                    <div class="meal-card" wire:key="meal-card-{{ $dayKey }}-{{ $typeKey }}">
                        <button type="button" @class(['meal-slot', 'meal-slot--empty' => ! $entry])
                                wire:click="openForm('{{ $dayKey }}', '{{ $typeKey }}')"
                                aria-label="{{ $typeLabel }} · {{ $date->translatedFormat('l j \d\e F') }}{{ $names ? ': '.$names : '' }}"
                                @if ($names) title="{{ $names }}" @endif>
                            @if ($entry)
                                <span class="meal-slot__caption">{{ $names ?: 'Comida sin elementos' }}</span>
                                @if ($kcal > 0)<span class="meal-slot__calories">{{ number_format($kcal, 0, ',', '.') }} kcal</span>@endif
                                <span class="meal-status-chip" data-state="{{ $state }}">{{ $stateLabels[$state] }}</span>
                            @else
                                <span class="meal-slot__add"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar</span>
                            @endif
                        </button>
                        @if ($entry)
                            <x-ui.menu size="sm" class="meal-card__menu" :label="'Acciones de '.mb_strtolower($typeLabel).' del '.$date->format('j')">
                                @if ($entry->status === 'planned')
                                    <x-ui.menu-item icon="bi-arrow-right" wire:click="openAction({{ $entry->id }}, 'move')">Mover</x-ui.menu-item>
                                    <x-ui.menu-item icon="bi-copy" wire:click="openAction({{ $entry->id }}, 'copy')">Copiar</x-ui.menu-item>
                                    <x-ui.menu-item icon="bi-arrow-left-right" wire:click="openAction({{ $entry->id }}, 'swap')">Intercambiar</x-ui.menu-item>
                                    <x-ui.menu-divider />
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteEntry({{ $entry->id }})" wire:confirm="¿Eliminar esta comida planificada?">Eliminar</x-ui.menu-item>
                                @else
                                    <x-ui.menu-item icon="bi-pencil" wire:click="openForm('{{ $dayKey }}', '{{ $typeKey }}')">Ver comida</x-ui.menu-item>
                                @endif
                            </x-ui.menu>
                        @endif
                    </div>
                @endforeach

                @php $dayCalories = $dailyCalories->get($dayKey, 0); @endphp
                <div class="meal-day__totals">
                    <strong>{{ $dayCalories > 0 ? number_format($dayCalories, 0, ',', '.').' kcal' : '—' }}</strong>
                    <small>{{ number_format($consumedCalories->get($dayKey, 0), 0, ',', '.') }} kcal consumidas</small>
                </div>
            </div>
        @endforeach

        @if ($plannedDays > 0)
            <p class="meal-week__summary">
                Pendiente de la semana: <strong>{{ number_format($weekCalories, 0, ',', '.') }} kcal</strong>
                · promedio <strong>{{ number_format($weekCalories / $plannedDays, 0, ',', '.') }} kcal/día</strong>
                en {{ $plannedDays }} {{ $plannedDays === 1 ? 'día planificado' : 'días planificados' }}
            </p>
        @endif
    </section>

    @if ($showToday)
        <x-ui.management-card id="meal-today" :title="'Hoy · '.now()->translatedFormat('l j')" icon="bi-sun" :refreshable="false">
            @if ($todayEntries->isEmpty())
                <x-ui.state variant="empty" icon="bi-egg-fried" title="Nada planeado para hoy" message="Toca una comida del calendario para planearla." />
            @else
                <x-ui.list label="Comidas de hoy">
                    @foreach ($todayEntries as $entry)
                        @php $state = $stateOf($entry); $kcal = $slotCalories($entry); $firstRecipe = $entry->items->first(fn ($item) => $item->recipe_id); @endphp
                        <x-ui.list-item :headline="$itemNames($entry) ?: 'Comida sin elementos'" wire:key="meal-today-{{ $entry->id }}">
                            <x-slot:leading><x-ui.icon name="bi-egg-fried" tone="primary" /></x-slot:leading>
                            <p class="md-list-item-supporting">{{ $mealTypes[$entry->meal_type] ?? $entry->meal_type }}{{ $kcal > 0 ? ' · '.number_format($kcal, 0, ',', '.').' kcal' : '' }} · <span class="meal-status-chip" data-state="{{ $state }}">{{ $stateLabels[$state] }}</span></p>
                            <x-slot:trailing>
                                <x-ui.row-actions :label="'Acciones de '.($mealTypes[$entry->meal_type] ?? $entry->meal_type)">
                                    <x-slot:primary wire:click="openForm('{{ $entry->date->format('Y-m-d') }}', '{{ $entry->meal_type }}')">Ver comida</x-slot:primary>
                                    @if ($firstRecipe)
                                        <x-ui.menu-item icon="bi-journal-text" :href="route('meals.recipes', ['q' => $firstRecipe->recipe?->name])" wire:navigate>Ver receta</x-ui.menu-item>
                                    @endif
                                </x-ui.row-actions>
                            </x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.list>
            @endif
        </x-ui.management-card>
    @endif

    <x-ui.form-dialog :open="$actionOpen" close="closeAction" submit-action="runAction" id="meal-action-dialog" module="meals"
                      :title="($actionLabels[$actionType] ?? 'Mover').' comida'" icon="bi-arrow-left-right" submit="Confirmar">
        <p class="md-body-medium">
            @if ($actionType === 'swap')
                Intercambia esta comida con la que esté en el destino.
            @elseif ($actionType === 'copy')
                Se crea una copia en el destino y la original se conserva.
            @else
                La comida pasa al destino elegido.
            @endif
        </p>
        <div class="md-field-pair">
            <x-ui.field name="targetDate" id="meal-action-date" label="Fecha destino" type="date" wire:model="targetDate" />
            <x-ui.select name="targetMealType" id="meal-action-type" label="Comida destino" wire:model="targetMealType" :options="$mealTypes" />
        </div>
    </x-ui.form-dialog>

    <x-ui.form-dialog :open="$showForm" close="closeForm" id="meal-plan-editor" module="meals"
                      :title="($editingId ? 'Editar' : 'Planear').' '.mb_strtolower($mealTypes[$formMealType] ?? $formMealType)"
                      icon="bi-egg-fried" submit-action="save" :submit="$editingId ? 'Actualizar' : 'Guardar'"
                      :sections="[
                          'composition' => ['label' => 'Composición', 'icon' => 'bi-basket', 'error' => $errors->has('formItems') || $errors->has('formItems.*')],
                          'schedule' => ['label' => 'Horario', 'icon' => 'bi-clock', 'error' => $errors->has('formTime') || $errors->has('formDuration')],
                          'nutrition' => ['label' => 'Resumen nutricional', 'icon' => 'bi-heart-pulse', 'error' => $errors->has('formCalories')],
                          'notes' => ['label' => 'Notas', 'icon' => 'bi-journal-text', 'error' => $errors->has('formNotes')],
                      ]">
        <x-ui.form-dialog-section name="composition" title="Composición"
                                  :description="$formDate ? ucfirst(\Carbon\Carbon::parse($formDate)->translatedFormat('l j \d\e F')) : null">
            @if ($errors->any())
                <div role="alert" class="md-form-notice">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
            @endif

            <div class="meal-status-row">
                <div class="meal-status-row__label">
                    <span class="md-label-large">Estado de la comida</span>
                    <span class="meal-status-chip meal-status-chip--lg" data-state="{{ $formState }}">{{ $stateLabels[$formState] }}</span>
                </div>
                @if ($formStatus === 'consumed')
                    <x-ui.action variant="outlined" icon="bi-arrow-counterclockwise" wire:click="revertConsumption">Volver a planeada</x-ui.action>
                @else
                    <x-ui.row-actions label="Más formas de registrar el consumo">
                        <x-slot:primary wire:click="consume('home')" wire:loading.attr="disabled">Marcar como consumida</x-slot:primary>
                        <x-ui.menu-item icon="bi-shop" wire:click="$set('showOutside', true)">Registrar comida por fuera</x-ui.menu-item>
                    </x-ui.row-actions>
                @endif
            </div>

            @if ($formStatus === 'consumed')
                <p class="md-body-small">
                    {{ $consumption['calories'] ?? 'Sin registrar' }} kcal{{ ! empty($consumption['notes']) ? ' · '.$consumption['notes'] : '' }}.
                    El plan original se conserva; vuelve a planeada para editarlo.
                </p>
                @foreach ($consumption['warnings'] ?? [] as $warning)<p class="md-body-small" role="status">{{ $warning }}</p>@endforeach
            @elseif ($showOutside)
                <div class="meal-outside">
                    <div class="md-field-pair">
                        <x-ui.field id="outside-notes" name="outsideNotes" label="Notas de comida por fuera" help="Ej. Almuerzo en un restaurante" wire:model="outsideNotes" />
                        <x-ui.field id="outside-calories" name="outsideCalories" label="Calorías consumidas (opcional)" type="number" min="0" wire:model="outsideCalories" />
                    </div>
                    <p class="md-body-small mb-0">El plan original se conserva. Puedes registrarla sin añadir elementos.</p>
                    <div class="meal-outside__actions">
                        <x-ui.action variant="text" wire:click="$set('showOutside', false)">Cancelar</x-ui.action>
                        <x-ui.action variant="tonal" icon="bi-shop" wire:click="consume('outside')" wire:loading.attr="disabled">Registrar por fuera</x-ui.action>
                    </div>
                </div>
            @endif

            <h4 class="md-title-small meal-composer__title">Composición de la comida</h4>
            <fieldset class="meal-composer" @disabled($formStatus === 'consumed') aria-label="Elementos de la comida">
                @php
                    $kinds = \App\Livewire\Meal\MealWeekly::KINDS;
                    $ingredientUnits = \App\Livewire\Meal\MealWeekly::INGREDIENT_UNITS;
                    $usedRecipes = collect($formItems)->pluck('recipe_id')->filter();
                @endphp
                @forelse ($formItems as $index => $item)
                    @php $kind = $item['kind'] ?? ''; @endphp
                    <div @class(['meal-composer-row', 'meal-composer-row--free' => $kind === 'free']) wire:key="{{ $item['key'] }}">
                        <x-ui.select id="meal-kind-{{ $index }}" name="formItems.{{ $index }}.kind" label="Tipo" placeholder="Seleccionar"
                                     :options="$kinds" wire:model.live="formItems.{{ $index }}.kind" />

                        @if ($kind === 'recipe')
                            <x-ui.select id="meal-element-{{ $index }}" name="formItems.{{ $index }}.recipe_id" label="Receta" placeholder="Elegir receta" wire:model.live="formItems.{{ $index }}.recipe_id">
                                @foreach ($recipeOptions as $recipe)
                                    @continue($usedRecipes->contains($recipe->id) && $recipe->id !== $item['recipe_id'])
                                    <option value="{{ $recipe->id }}">{{ $recipe->favorite ? '♥ ' : '' }}{{ $recipe->name }}</option>
                                @endforeach
                            </x-ui.select>
                        @elseif ($kind === 'preparation')
                            <x-ui.select id="meal-element-{{ $index }}" name="formItems.{{ $index }}.preparation_id" label="Preparación" placeholder="Elegir preparación" wire:model.live="formItems.{{ $index }}.preparation_id">
                                @foreach ($preparations as $prep)<option value="{{ $prep->id }}">{{ $prep->name }} · {{ $prep->remaining() - $prep->reserved() }} porciones libres</option>@endforeach
                            </x-ui.select>
                        @elseif ($kind === 'ingredient')
                            <x-ui.select id="meal-element-{{ $index }}" name="formItems.{{ $index }}.product_id" label="Ingrediente" placeholder="Elegir ingrediente" wire:model.live="formItems.{{ $index }}.product_id">
                                @foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                            </x-ui.select>
                        @elseif ($kind === 'free')
                            <x-ui.field id="meal-element-{{ $index }}" name="formItems.{{ $index }}.name" label="Elemento" wire:model="formItems.{{ $index }}.name" />
                        @else
                            <x-ui.select id="meal-element-{{ $index }}" name="formItems.{{ $index }}.element" label="Elemento" placeholder="Selecciona el tipo" :options="[]" :disabled="true" />
                        @endif

                        @if ($kind === 'free')
                            <x-ui.field id="meal-calories-{{ $index }}" name="formItems.{{ $index }}.calories" label="Calorías" type="number" min="0" wire:model.live.debounce.250ms="formItems.{{ $index }}.calories" />
                        @elseif ($kind === 'ingredient')
                            <x-ui.field id="meal-quantity-{{ $index }}" name="formItems.{{ $index }}.quantity" label="Cantidad" type="number" min="0.001" step="any" wire:model="formItems.{{ $index }}.quantity" />
                            <x-ui.select id="meal-unit-{{ $index }}" name="formItems.{{ $index }}.unit" label="Unidad" :options="$ingredientUnits" wire:model="formItems.{{ $index }}.unit" />
                        @else
                            <x-ui.field id="meal-quantity-{{ $index }}" name="formItems.{{ $index }}.portions" label="Cantidad" type="number" min="0.01" step="0.25"
                                        wire:model.live.debounce.250ms="formItems.{{ $index }}.portions" :disabled="$kind === ''" />
                            <x-ui.select id="meal-unit-{{ $index }}" name="formItems.{{ $index }}.portion_unit" label="Unidad" :options="['portion' => 'porción']" :disabled="true" />
                        @endif

                        <x-ui.icon-action icon="bi-trash" label="Quitar elemento" class="meal-composer-row__remove" wire:click="removeItem({{ $index }})" />

                        @if (in_array($kind, ['recipe', 'preparation'], true) && ($item['recipe_id'] || $item['preparation_id']))
                            <p class="meal-composer-row__meta">{{ $item['recipe_calories'] !== null ? round($item['recipe_calories'] * ((float) $item['portions'] ?: 0)).' kcal' : 'Calorías sin registrar' }}{{ $kind === 'preparation' ? ' · ingredientes ya descontados' : '' }}</p>
                        @elseif ($kind === 'free' && ! empty($item['ingredients']))
                            <p class="meal-composer-row__meta">{{ count($item['ingredients']) }} ingredientes enlazados</p>
                        @endif
                    </div>
                @empty
                    <div class="md-form-empty"><i class="bi bi-basket2"></i><p>Agrega recetas, preparaciones, ingredientes o elementos libres para armar esta comida.</p></div>
                @endforelse
                <x-ui.action variant="tonal" icon="bi-plus-lg" wire:click="addRow">Agregar</x-ui.action>
            </fieldset>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="schedule" title="Horario de esta comida">
            <p>Perfil: {{ $effectiveSchedule['time'] ?? '' }} · {{ $effectiveSchedule['duration'] ?? '' }} minutos. Deja un campo vacío para usar su valor del perfil.</p>
            <x-ui.field type="time" name="formTime" label="Hora puntual" wire:model="formTime" :help="'Hora del perfil: '.($effectiveSchedule['time'] ?? '')" />
            <x-ui.field type="number" name="formDuration" label="Duración puntual (minutos)" wire:model="formDuration" min="1" max="1440" :help="'Duración del perfil: '.($effectiveSchedule['duration'] ?? '')" />
            <button type="button" class="md-btn md-btn-text" wire:click="resetSchedule">Volver al perfil</button>
            @if ($editingId)<button type="button" class="md-btn md-btn-outlined" wire:click="saveSchedule" wire:loading.attr="disabled">Guardar solo horario</button>@endif
            @if ($scheduleMessage)<p role="status">{{ $scheduleMessage }}</p>@endif
        </x-ui.form-dialog-section>
        <x-ui.form-dialog-section name="nutrition" title="Resumen nutricional">
            <div class="meal-calorie-summary">
                <div><span>Total efectivo</span><strong>{{ $formCalories ?? $this->calculatedFormCalories() }} kcal</strong></div>
                <div><span>Cálculo por componentes</span><strong>{{ $this->calculatedFormCalories() }} kcal</strong></div>
            </div>
            @if ($this->formHasIncompleteCalories())
                <p class="md-form-notice"><i class="bi bi-exclamation-circle"></i> El cálculo es parcial: alguna receta no tiene calorías registradas.</p>
            @endif
            <x-ui.field name="formCalories" id="meal-calories" label="Calorías manuales (kcal)" type="number" min="0"
                        help="Vacío = usar el cálculo de la composición" wire:model="formCalories" :disabled="$formStatus === 'consumed'" />
            @if ($formCalories !== null && $formStatus !== 'consumed')
                <x-ui.action variant="text" icon="bi-arrow-counterclockwise" wire:click="useCalculatedCalories">Usar cálculo</x-ui.action>
            @endif
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="notes" title="Notas de preparación">
            <x-ui.textarea name="formNotes" id="meal-notes" label="Preparación, acompañamientos o recordatorios" rows="8"
                           wire:model="formNotes" :disabled="$formStatus === 'consumed'" />
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>
</x-module-shell>
