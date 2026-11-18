@php
    $createAction = ['label' => 'Cocinar preparación', 'icon' => 'bi-egg-fried', 'action' => 'openForm'];
@endphp

<x-module-shell module="meals">
    <x-slot:actions><x-module-actions :primary="$createAction" /></x-slot:actions>
    @if ($errors->any())<div role="alert" class="md-form-notice">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <x-ui.management-card title="Preparaciones" icon="bi-egg-fried" id="meal-preparations" :create-actions="[$createAction]" search="search" :paginator="$preparations" :count="$preparations->total()" noun="preparaciones">
        <x-ui.list label="Preparaciones disponibles">
                    @forelse ($preparations as $preparation)
                        @php $summary = $preparation->summary(); @endphp
                        <x-ui.list-item :headline="$preparation->name" wire:key="preparation-{{ $preparation->id }}">
                            <p>Cocinada: {{ $preparation->cooked_at->format('d/m/Y H:i') }} · Fecha límite: {{ $preparation->consume_by?->format('d/m/Y') ?? 'Sin fecha' }}</p>
                            <p>Producidas: {{ $summary['produced'] }} · Consumidas: {{ $summary['consumed'] }} · Reservadas: {{ $summary['reserved'] }} · Disponibles: {{ $summary['available'] }}</p>
                            <details><summary>Ingredientes y reservas</summary>
                                @foreach ($preparation->ingredients as $ingredient)<p>{{ $ingredient['name'] }}: {{ $ingredient['quantity'] }} {{ $ingredient['unit'] }}</p>@endforeach
                                @foreach ($preparation->plannedItems()->with('mealPlanEntry')->get() as $planned)<p>{{ $planned->mealPlanEntry->date->format('d/m/Y') }} · {{ $planned->mealPlanEntry->meal_type }}: {{ $planned->portions }} porciones</p>@endforeach
                            </details>
                            <button type="button" class="md-btn-text" wire:click="cancel('{{ $preparation->id }}')" wire:confirm="¿Cancelar la preparación y devolver sus ingredientes?" wire:loading.attr="disabled" @disabled($summary['consumed'] > 0 || $summary['reserved'] > 0)>Cancelar preparación</button>
                        </x-ui.list-item>
                    @empty<li><p>Sin preparaciones. Cocina una receta para disponer de porciones durante varios días.</p></li>@endforelse
        </x-ui.list>
    </x-ui.management-card>
    <x-ui.form-dialog :open="$showForm" title="Cocinar preparación" icon="bi-egg-fried" close="closeForm" submit-action="save" id="cook-preparation" module="meals">
        @if ($errors->any())<div role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
        <p>Los ingredientes se descontarán una sola vez. Planificar estas porciones reserva el alimento ya preparado.</p>
        <x-ui.select label="Receta" name="recipeId" wire:model="recipeId" :options="$recipes" placeholder="Elegir receta" />
        <x-ui.field label="Porciones producidas" name="portions" type="number" min="0.01" step="0.01" wire:model="portions" />
        <x-ui.field label="Fecha de cocinado" name="cookedAt" type="datetime-local" wire:model="cookedAt" />
        <x-ui.field label="Fecha límite (opcional)" name="consumeBy" type="date" wire:model="consumeBy" />
    </x-ui.form-dialog>
</x-module-shell>
